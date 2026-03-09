<?php

/**
 * Tests for ushahidi:json:export Artisan command
 *
 * @author     Ushahidi Team <team@ushahidi.com>
 * @copyright  2024 Ushahidi
 * @license    https://www.gnu.org/licenses/agpl-3.0.html GNU Affero General Public License Version 3 (AGPL3)
 */

namespace Ushahidi\Tests\Unit\JsonIO;

use Illuminate\Console\Application as Artisan;
use Mockery as M;
use Ushahidi\Tests\TestCase;
use Ushahidi\Addons\JsonIO\Console\ExportCommand;
use Ushahidi\Addons\JsonIO\Export\JsonExporter;

/**
 * @backupGlobals disabled
 * @preserveGlobalState disabled
 */
class ExportCommandTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpDir = sys_get_temp_dir() . '/ushahidi_json_cmd_export_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);

        // Register the command with a mocked exporter
        $exporter = M::mock(JsonExporter::class);
        $exporter->shouldReceive('export')
            ->withAnyArgs()
            ->andReturn(5);

        $this->app->instance(JsonExporter::class, $exporter);

        $command = new ExportCommand();
        Artisan::starting(function ($artisan) use ($command) {
            $artisan->add($command);
        });
    }

    protected function tearDown(): void
    {
        // Recursively remove tmpDir
        foreach (glob($this->tmpDir . '/*') as $entry) {
            if (is_dir($entry)) {
                foreach (glob($entry . '/*.json') as $file) {
                    unlink($file);
                }
                rmdir($entry);
            } elseif (is_file($entry)) {
                unlink($entry);
            }
        }
        if (is_dir($this->tmpDir)) {
            rmdir($this->tmpDir);
        }
        M::close();
        parent::tearDown();
    }

    public function testExportCommandSucceeds(): void
    {
        $exitCode = $this->artisan('ushahidi:json:export', [
            '--output'    => $this->tmpDir,
            '--snapshot'  => 'test-snap',
            '--resources' => 'layers',
        ]);

        $this->assertSame(0, $exitCode);
    }

    public function testExportCommandFailsOnUnknownResource(): void
    {
        $exitCode = $this->artisan('ushahidi:json:export', [
            '--output'    => $this->tmpDir,
            '--snapshot'  => 'test-snap',
            '--resources' => 'badresource',
        ]);

        $this->assertSame(1, $exitCode);
    }

    public function testExportCommandUsesTimestampSnapshotByDefault(): void
    {
        // Should not throw — a timestamp snapshot directory will be created
        $exitCode = $this->artisan('ushahidi:json:export', [
            '--output'    => $this->tmpDir,
            '--resources' => 'layers',
        ]);

        $this->assertSame(0, $exitCode);
    }

    public function testExportCommandAcceptsMultipleResources(): void
    {
        $exitCode = $this->artisan('ushahidi:json:export', [
            '--output'    => $this->tmpDir,
            '--snapshot'  => 'multi',
            '--resources' => 'layers,tags',
        ]);

        $this->assertSame(0, $exitCode);
    }
}
