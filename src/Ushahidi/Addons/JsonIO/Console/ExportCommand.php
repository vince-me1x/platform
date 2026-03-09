<?php

/**
 * JSON Export Artisan Command
 *
 * Exports Ushahidi map data (posts, forms, tags, layers) to JSON files.
 *
 * Usage:
 *   php artisan ushahidi:json:export
 *   php artisan ushahidi:json:export --resources=posts,layers
 *   php artisan ushahidi:json:export --snapshot=my-backup
 *   php artisan ushahidi:json:export --output=/tmp/exports --snapshot=2024-01-01
 *
 * @author     Ushahidi Team <team@ushahidi.com>
 * @package    Ushahidi\Addons\JsonIO
 * @copyright  2024 Ushahidi
 * @license    https://www.gnu.org/licenses/agpl-3.0.html GNU Affero General Public License Version 3 (AGPL3)
 */

namespace Ushahidi\Addons\JsonIO\Console;

use Illuminate\Console\Command;
use Ushahidi\Addons\JsonIO\Export\JsonExporter;

class ExportCommand extends Command
{
    protected $signature = 'ushahidi:json:export
        {--resources= : Comma-separated resources to export: posts,forms,tags,layers (default: all)}
        {--snapshot= : Snapshot name/identifier used as a subdirectory (default: current timestamp)}
        {--output= : Base output directory (default: storage/app/ushahidi-json/)}';

    protected $description = 'Export Ushahidi map data (posts, forms, tags, layers) to JSON files';

    /** Supported resource types */
    private const SUPPORTED = ['posts', 'forms', 'tags', 'layers'];

    public function handle(JsonExporter $exporter): int
    {
        $snapshot  = $this->option('snapshot') ?: date('Y-m-d_His');
        $outputDir = rtrim(
            $this->option('output') ?: storage_path('app/ushahidi-json'),
            '/'
        ) . '/' . $snapshot;

        $resources = $this->parseResources($this->option('resources'));

        if ($resources === null) {
            // parseResources already printed the error
            return self::FAILURE;
        }

        if (!is_dir($outputDir) && !mkdir($outputDir, 0755, true) && !is_dir($outputDir)) {
            $this->error("Unable to create output directory: {$outputDir}");
            return self::FAILURE;
        }

        foreach ($resources as $resource) {
            $this->line("Exporting <info>{$resource}</info>...");
            $count = $exporter->export($resource, $outputDir);
            $this->line(
                sprintf('  → Exported %d %s to %s/%s.json', $count, $resource, $outputDir, $resource)
            );
        }

        $this->info("Export complete. Snapshot: <comment>{$snapshot}</comment>");
        $this->line("Output directory: {$outputDir}");

        return self::SUCCESS;
    }

    /**
     * Parse and validate the --resources option.
     *
     * @return string[]|null  Returns the list of resources, or null on error.
     */
    private function parseResources(?string $input): ?array
    {
        if (empty($input)) {
            return self::SUPPORTED;
        }

        $requested = array_values(array_filter(array_map('trim', explode(',', $input))));
        $invalid   = array_diff($requested, self::SUPPORTED);

        if (!empty($invalid)) {
            $this->error('Unknown resource(s): ' . implode(', ', $invalid));
            $this->line('Supported resources: ' . implode(', ', self::SUPPORTED));
            return null;
        }

        return $requested;
    }
}
