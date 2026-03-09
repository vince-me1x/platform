<?php

/**
 * JSON Exporter
 *
 * Exports Ushahidi map data resources to JSON files using raw database queries
 * so that no permission-sensitive Eloquent accessors (hide_location, hide_author,
 * hide_time, etc.) are applied — giving a complete, authoritative data dump.
 *
 * @author     Ushahidi Team <team@ushahidi.com>
 * @package    Ushahidi\Addons\JsonIO
 * @copyright  2024 Ushahidi
 * @license    https://www.gnu.org/licenses/agpl-3.0.html GNU Affero General Public License Version 3 (AGPL3)
 */

namespace Ushahidi\Addons\JsonIO\Export;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class JsonExporter
{
    /**
     * Export the given resource to a JSON file in $outputDir.
     *
     * @param  string $resource   One of: posts, forms, tags, layers
     * @param  string $outputDir  Absolute path to the output directory (must already exist)
     * @return int                Number of records exported
     *
     * @throws RuntimeException   When an unsupported resource is requested
     */
    public function export(string $resource, string $outputDir): int
    {
        $data = match ($resource) {
            'posts'  => $this->exportPosts(),
            'forms'  => $this->exportForms(),
            'tags'   => $this->exportTags(),
            'layers' => $this->exportLayers(),
            default  => throw new RuntimeException("Unsupported resource: {$resource}"),
        };

        $payload = [
            'meta' => [
                'resource'    => $resource,
                'exported_at' => now()->toIso8601String(),
                'count'       => count($data),
            ],
            'data' => $data,
        ];

        $encoded = json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if ($encoded === false) {
            throw new RuntimeException("JSON encoding failed for resource '{$resource}'");
        }

        file_put_contents($outputDir . '/' . $resource . '.json', $encoded);

        return count($data);
    }

    // -------------------------------------------------------------------------
    // Private per-resource export helpers
    // -------------------------------------------------------------------------

    /**
     * Export posts together with their geometry/point values and tag IDs.
     *
     * Geometry is fetched as WKT text via ST_AsText() so that the output is
     * human-readable and portable across GIS tools.
     */
    private function exportPosts(): array
    {
        $posts = [];

        DB::table('posts')->orderBy('id')->chunk(500, function ($rows) use (&$posts) {
            foreach ($rows as $row) {
                $post = (array) $row;

                // Geometry values (polygons, lines, etc.)
                $post['geometry_values'] = DB::table('post_geometry')
                    ->selectRaw(
                        'id, post_id, form_attribute_id, ST_AsText(`value`) AS `value`'
                    )
                    ->where('post_id', $row->id)
                    ->get()
                    ->map(fn ($r) => (array) $r)
                    ->toArray();

                // Point values (lat/lon)
                $post['point_values'] = DB::table('post_point')
                    ->selectRaw(
                        'id, post_id, form_attribute_id, ST_AsText(`value`) AS `value`'
                    )
                    ->where('post_id', $row->id)
                    ->get()
                    ->map(fn ($r) => (array) $r)
                    ->toArray();

                // Tag (category) IDs associated with the post
                $post['tag_ids'] = DB::table('posts_tags')
                    ->where('post_id', $row->id)
                    ->pluck('tag_id')
                    ->toArray();

                $posts[] = $post;
            }
        });

        return $posts;
    }

    /**
     * Export forms (surveys) with their stages (tasks) and attributes (fields).
     */
    private function exportForms(): array
    {
        $forms = [];

        DB::table('forms')->orderBy('id')->chunk(200, function ($rows) use (&$forms) {
            foreach ($rows as $row) {
                $form = (array) $row;

                $stages = DB::table('form_stages')
                    ->where('form_id', $row->id)
                    ->orderBy('priority')
                    ->orderBy('id')
                    ->get();

                $form['tasks'] = $stages->map(function ($stage) {
                    $stageArr = (array) $stage;
                    $stageArr['fields'] = DB::table('form_attributes')
                        ->where('form_stage_id', $stage->id)
                        ->orderBy('priority')
                        ->orderBy('id')
                        ->get()
                        ->map(fn ($r) => (array) $r)
                        ->toArray();
                    return $stageArr;
                })->toArray();

                $forms[] = $form;
            }
        });

        return $forms;
    }

    /**
     * Export tags/categories (rows in the `tags` table where type = 'category').
     *
     * The full row is exported so that parent_id hierarchy is preserved and
     * can be reconstructed on import.
     */
    private function exportTags(): array
    {
        $tags = [];

        DB::table('tags')
            ->where('type', 'category')
            ->orderBy('id')
            ->chunk(500, function ($rows) use (&$tags) {
                foreach ($rows as $row) {
                    $tags[] = (array) $row;
                }
            });

        return $tags;
    }

    /**
     * Export map layers.
     */
    private function exportLayers(): array
    {
        $layers = [];

        DB::table('layers')->orderBy('id')->chunk(200, function ($rows) use (&$layers) {
            foreach ($rows as $row) {
                $layers[] = (array) $row;
            }
        });

        return $layers;
    }
}
