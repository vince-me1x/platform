<?php

/**
 * JSON Importer
 *
 * Imports Ushahidi map data resources from JSON files produced by JsonExporter.
 * Uses direct DB queries (no Eloquent model events / observers) so that
 * the import is fast and side-effect-free.
 *
 * By default existing records (matched by primary key) are skipped.
 * Pass $force = true to overwrite them instead.
 *
 * @author     Ushahidi Team <team@ushahidi.com>
 * @package    Ushahidi\Addons\JsonIO
 * @copyright  2024 Ushahidi
 * @license    https://www.gnu.org/licenses/agpl-3.0.html GNU Affero General Public License Version 3 (AGPL3)
 */

namespace Ushahidi\Addons\JsonIO\Import;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class JsonImporter
{
    /**
     * Import the given resource from a JSON file.
     *
     * @param  string $resource  One of: posts, forms, tags, layers
     * @param  string $filePath  Absolute path to the JSON file
     * @param  bool   $force     When true, overwrite existing records
     * @return int               Number of records inserted or updated
     *
     * @throws RuntimeException  On JSON decode errors or unsupported resource
     */
    public function import(string $resource, string $filePath, bool $force = false): int
    {
        $contents = file_get_contents($filePath);

        if ($contents === false) {
            throw new RuntimeException("Cannot read file: {$filePath}");
        }

        $payload = json_decode($contents, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(
                "JSON decode error in {$filePath}: " . json_last_error_msg()
            );
        }

        if (!isset($payload['data']) || !is_array($payload['data'])) {
            throw new RuntimeException("Invalid JSON structure: missing 'data' key in {$filePath}");
        }

        return match ($resource) {
            'posts'  => $this->importPosts($payload['data'], $force),
            'forms'  => $this->importForms($payload['data'], $force),
            'tags'   => $this->importTags($payload['data'], $force),
            'layers' => $this->importLayers($payload['data'], $force),
            default  => throw new RuntimeException("Unsupported resource: {$resource}"),
        };
    }

    // -------------------------------------------------------------------------
    // Private per-resource import helpers
    // -------------------------------------------------------------------------

    /**
     * Import posts.
     *
     * Geometry and point values are skipped on import because they require
     * MySQL spatial functions (ST_GeomFromText) and re-inserting them would need
     * special handling.  Tag associations are restored via the posts_tags pivot.
     */
    private function importPosts(array $posts, bool $force): int
    {
        $count = 0;

        DB::transaction(function () use ($posts, $force, &$count) {
            foreach ($posts as $post) {
                $tagIds         = $post['tag_ids'] ?? [];
                $geometryValues = $post['geometry_values'] ?? [];
                $pointValues    = $post['point_values'] ?? [];

                // Strip non-column keys before DB operations
                unset($post['geometry_values'], $post['point_values'], $post['tag_ids']);

                $postId = $post['id'] ?? null;

                if (!$postId) {
                    continue;
                }

                $existing = DB::table('posts')->where('id', $postId)->exists();

                if ($existing && !$force) {
                    continue;
                }

                if ($existing) {
                    $updateData = $post;
                    unset($updateData['id']);
                    DB::table('posts')->where('id', $postId)->update($updateData);
                } else {
                    DB::table('posts')->insert($post);
                }

                // Restore tag associations (batch insert for efficiency)
                DB::table('posts_tags')->where('post_id', $postId)->delete();
                if (!empty($tagIds)) {
                    $tagRows = array_map(
                        fn ($tagId) => ['post_id' => $postId, 'tag_id' => $tagId],
                        $tagIds
                    );
                    DB::table('posts_tags')->insert($tagRows);
                }

                $count++;
            }
        });

        return $count;
    }

    /**
     * Import forms (surveys) along with their stages (tasks) and fields (attributes).
     */
    private function importForms(array $forms, bool $force): int
    {
        $count = 0;

        DB::transaction(function () use ($forms, $force, &$count) {
            foreach ($forms as $form) {
                $tasks = $form['tasks'] ?? [];
                unset($form['tasks']);

                $formId = $form['id'] ?? null;

                if (!$formId) {
                    continue;
                }

                $existing = DB::table('forms')->where('id', $formId)->exists();

                if ($existing && !$force) {
                    continue;
                }

                if ($existing) {
                    $updateData = $form;
                    unset($updateData['id']);
                    DB::table('forms')->where('id', $formId)->update($updateData);
                } else {
                    DB::table('forms')->insert($form);
                }

                // Import stages
                foreach ($tasks as $task) {
                    $fields = $task['fields'] ?? [];
                    unset($task['fields']);

                    $stageId = $task['id'] ?? null;

                    if (!$stageId) {
                        continue;
                    }

                    $existingStage = DB::table('form_stages')->where('id', $stageId)->exists();

                    if ($existingStage && !$force) {
                        // Skip the stage AND its fields to avoid inconsistent state
                        continue;
                    }

                    if ($existingStage) {
                        $updateData = $task;
                        unset($updateData['id']);
                        DB::table('form_stages')->where('id', $stageId)->update($updateData);
                    } else {
                        DB::table('form_stages')->insert($task);
                    }

                    // Import attributes (fields) — only reached when the stage was inserted/updated
                    foreach ($fields as $field) {
                        $fieldId = $field['id'] ?? null;

                        if (!$fieldId) {
                            continue;
                        }

                        $existingField = DB::table('form_attributes')->where('id', $fieldId)->exists();

                        if ($existingField && !$force) {
                            continue;
                        }

                        if ($existingField) {
                            $updateData = $field;
                            unset($updateData['id']);
                            DB::table('form_attributes')->where('id', $fieldId)->update($updateData);
                        } else {
                            DB::table('form_attributes')->insert($field);
                        }
                    }
                }

                $count++;
            }
        });

        return $count;
    }

    /**
     * Import tags/categories.
     *
     * Parent tags are expected to be present in the same JSON file.
     * Records without a matching parent_id are still inserted so that
     * a partial restore does not fail the entire import.
     */
    private function importTags(array $tags, bool $force): int
    {
        $count = 0;

        DB::transaction(function () use ($tags, $force, &$count) {
            foreach ($tags as $tag) {
                $tagId = $tag['id'] ?? null;

                if (!$tagId) {
                    continue;
                }

                $existing = DB::table('tags')->where('id', $tagId)->exists();

                if ($existing && !$force) {
                    continue;
                }

                if ($existing) {
                    $updateData = $tag;
                    unset($updateData['id']);
                    DB::table('tags')->where('id', $tagId)->update($updateData);
                } else {
                    DB::table('tags')->insert($tag);
                }

                $count++;
            }
        });

        return $count;
    }

    /**
     * Import map layers.
     */
    private function importLayers(array $layers, bool $force): int
    {
        $count = 0;

        DB::transaction(function () use ($layers, $force, &$count) {
            foreach ($layers as $layer) {
                $layerId = $layer['id'] ?? null;

                if (!$layerId) {
                    continue;
                }

                $existing = DB::table('layers')->where('id', $layerId)->exists();

                if ($existing && !$force) {
                    continue;
                }

                if ($existing) {
                    $updateData = $layer;
                    unset($updateData['id']);
                    DB::table('layers')->where('id', $layerId)->update($updateData);
                } else {
                    DB::table('layers')->insert($layer);
                }

                $count++;
            }
        });

        return $count;
    }
}
