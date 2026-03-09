<?php

/**
 * JSON Import Artisan Command
 *
 * Imports Ushahidi map data (posts, forms, tags, layers) from JSON files.
 *
 * Usage:
 *   php artisan ushahidi:json:import --snapshot=my-backup
 *   php artisan ushahidi:json:import --resources=forms,tags --snapshot=2024-01-01
 *   php artisan ushahidi:json:import --snapshot=my-backup --force
 *   php artisan ushahidi:json:import --input=/tmp/exports --snapshot=my-backup
 *
 * @author     Ushahidi Team <team@ushahidi.com>
 * @package    Ushahidi\Addons\JsonIO
 * @copyright  2024 Ushahidi
 * @license    https://www.gnu.org/licenses/agpl-3.0.html GNU Affero General Public License Version 3 (AGPL3)
 */

namespace Ushahidi\Addons\JsonIO\Console;

use Illuminate\Console\Command;
use Ushahidi\Addons\JsonIO\Import\JsonImporter;

class ImportCommand extends Command
{
    protected $signature = 'ushahidi:json:import
        {--resources= : Comma-separated resources to import: posts,forms,tags,layers (default: all)}
        {--snapshot= : Snapshot name/identifier (subdirectory) to import from}
        {--input= : Base input directory (default: storage/app/ushahidi-json/)}
        {--force : Overwrite existing records instead of skipping them}';

    protected $description = 'Import Ushahidi map data (posts, forms, tags, layers) from JSON files';

    /** Supported resource types */
    private const SUPPORTED = ['posts', 'forms', 'tags', 'layers'];

    /**
     * Import order respects foreign key dependencies:
     *   forms must exist before posts reference them,
     *   tags must exist before posts reference them via posts_tags.
     */
    private const IMPORT_ORDER = ['forms', 'tags', 'posts', 'layers'];

    public function handle(JsonImporter $importer): int
    {
        $snapshot  = $this->option('snapshot');
        $inputBase = rtrim($this->option('input') ?: storage_path('app/ushahidi-json'), '/');
        $inputDir  = $snapshot ? $inputBase . '/' . $snapshot : $inputBase;
        $force     = (bool) $this->option('force');

        $resources = $this->parseResources($this->option('resources'));

        if ($resources === null) {
            return self::FAILURE;
        }

        if (!is_dir($inputDir)) {
            $this->error("Input directory does not exist: {$inputDir}");
            return self::FAILURE;
        }

        // Order resources to satisfy FK dependencies (forms/tags before posts)
        $ordered = array_filter(self::IMPORT_ORDER, fn ($r) => in_array($r, $resources, true));

        $anyImported = false;

        foreach ($ordered as $resource) {
            $file = $inputDir . '/' . $resource . '.json';

            if (!file_exists($file)) {
                $this->warn("File not found, skipping <comment>{$resource}</comment>: {$file}");
                continue;
            }

            $this->line("Importing <info>{$resource}</info> from {$file}...");

            try {
                $count = $importer->import($resource, $file, $force);
            } catch (\Throwable $e) {
                $this->error("Failed to import {$resource}: " . $e->getMessage());
                return self::FAILURE;
            }

            $this->line(sprintf('  → Imported/updated %d %s.', $count, $resource));
            $anyImported = true;
        }

        if (!$anyImported) {
            $this->warn('No resources were imported.');
            return self::FAILURE;
        }

        $this->info('Import complete.');
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
