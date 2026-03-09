<?php

/**
 * Tests for ushahidi:json:import Artisan command
 *
 * @author     Ushahidi Team <team@ushahidi.com>
 * @copyright  2024 Ushahidi
 * @license    https://www.gnu.org/licenses/agpl-3.0.html GNU Affero General Public License Version 3 (AGPL3)
 */

namespace Ushahidi\Tests\Unit\JsonIO;

use Illuminate\Console\Application as Artisan;
use Mockery as M;
use Ushahidi\Tests\TestCase;
use Ushahidi\Addons\JsonIO\Console\ImportCommand;
use Ushahidi\Addons\JsonIO\Import\JsonImporter;

/**
 * @backupGlobals disabled
 * @preserveGlobalState disabled
 */
class ImportCommandTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpDir = sys_get_temp_dir() . '/ushahidi_json_cmd_import_test_' . uniqid();
        mkdir($this->tmpDir . '/snap', 0755, true);

        // Write a minimal valid fixture
        file_put_contents($this->tmpDir . '/snap/layers.json', json_encode([
            'meta' => ['resource' => 'layers', 'exported_at' => '2024-01-01T00:00:00+00:00', 'count' => 1],
            'data' => [['id' => 1, 'name' => 'L', 'type' => 'geojson', 'active' => 1]],
        ]));

        // Register command with a mocked importer
        $importer = M::mock(JsonImporter::class);
        $importer->shouldReceive('import')
            ->withAnyArgs()
            ->andReturn(1);

        $this->app->instance(JsonImporter::class, $importer);

        $command = new ImportCommand();
        Artisan::starting(function ($artisan) use ($command) {
            $artisan->add($command);
        });
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '/snap/*.json') as $file) {
            unlink($file);
        }
        if (is_dir($this->tmpDir . '/snap')) {
            rmdir($this->tmpDir . '/snap');
        }
        if (is_dir($this->tmpDir)) {
            rmdir($this->tmpDir);
        }
        M::close();
        parent::tearDown();
    }

    public function testImportCommandSucceeds(): void
    {
        $exitCode = $this->artisan('ushahidi:json:import', [
            '--input'     => $this->tmpDir,
            '--snapshot'  => 'snap',
            '--resources' => 'layers',
        ]);

        $this->assertSame(0, $exitCode);
    }

    public function testImportCommandFailsOnNonExistentDirectory(): void
    {
        $exitCode = $this->artisan('ushahidi:json:import', [
            '--input'     => '/nonexistent/path',
            '--snapshot'  => 'snap',
            '--resources' => 'layers',
        ]);

        $this->assertSame(1, $exitCode);
    }

    public function testImportCommandFailsOnUnknownResource(): void
    {
        $exitCode = $this->artisan('ushahidi:json:import', [
            '--input'     => $this->tmpDir,
            '--snapshot'  => 'snap',
            '--resources' => 'badresource',
        ]);

        $this->assertSame(1, $exitCode);
    }

    public function testImportCommandWarnsMissingFile(): void
    {
        // Only 'posts' file is missing — command should warn and return failure
        $exitCode = $this->artisan('ushahidi:json:import', [
            '--input'     => $this->tmpDir,
            '--snapshot'  => 'snap',
            '--resources' => 'posts',
        ]);

        // posts.json doesn't exist in the snapshot, so nothing is imported → FAILURE
        $this->assertSame(1, $exitCode);
    }
}
