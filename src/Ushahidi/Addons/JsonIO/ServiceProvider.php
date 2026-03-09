<?php

/**
 * JsonIO Addon Service Provider
 *
 * Registers the Artisan commands for JSON-based export and import of
 * Ushahidi map data (posts, forms, tags, layers).
 *
 * @author     Ushahidi Team <team@ushahidi.com>
 * @package    Ushahidi\Addons\JsonIO
 * @copyright  2024 Ushahidi
 * @license    https://www.gnu.org/licenses/agpl-3.0.html GNU Affero General Public License Version 3 (AGPL3)
 */

namespace Ushahidi\Addons\JsonIO;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Ushahidi\Addons\JsonIO\Console\ExportCommand;
use Ushahidi\Addons\JsonIO\Console\ImportCommand;
use Ushahidi\Addons\JsonIO\Export\JsonExporter;
use Ushahidi\Addons\JsonIO\Import\JsonImporter;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(JsonExporter::class);
        $this->app->singleton(JsonImporter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ExportCommand::class,
                ImportCommand::class,
            ]);
        }
    }
}
