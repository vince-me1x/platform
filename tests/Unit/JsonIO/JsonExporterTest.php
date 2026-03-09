<?php

/**
 * Tests for JsonExporter
 *
 * @author     Ushahidi Team <team@ushahidi.com>
 * @copyright  2024 Ushahidi
 * @license    https://www.gnu.org/licenses/agpl-3.0.html GNU Affero General Public License Version 3 (AGPL3)
 */

namespace Ushahidi\Tests\Unit\JsonIO;

use Illuminate\Support\Facades\DB;
use Mockery as M;
use Ushahidi\Tests\TestCase;
use Ushahidi\Addons\JsonIO\Export\JsonExporter;

/**
 * @backupGlobals disabled
 * @preserveGlobalState disabled
 */
class JsonExporterTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/ushahidi_json_export_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        // Clean up temp files
        foreach (glob($this->tmpDir . '/*.json') as $file) {
            unlink($file);
        }
        rmdir($this->tmpDir);
        parent::tearDown();
    }

    public function testExportLayersCreatesJsonFile(): void
    {
        DB::shouldReceive('table')
            ->with('layers')
            ->andReturnSelf();
        DB::shouldReceive('orderBy')
            ->andReturnSelf();
        DB::shouldReceive('chunk')
            ->andReturnUsing(function ($size, $callback) {
                $callback(collect([
                    (object) [
                        'id'              => 1,
                        'name'            => 'Test Layer',
                        'type'            => 'geojson',
                        'data_url'        => 'https://example.com/data.geojson',
                        'options'         => '{"color":"#ff0000"}',
                        'active'          => 1,
                        'visible_by_default' => 0,
                        'media_id'        => null,
                        'created'         => 1704067200,
                        'updated'         => 1704067200,
                    ],
                ]));
            });

        $exporter = new JsonExporter();
        $count = $exporter->export('layers', $this->tmpDir);

        $this->assertSame(1, $count);

        $outputFile = $this->tmpDir . '/layers.json';
        $this->assertFileExists($outputFile);

        $decoded = json_decode(file_get_contents($outputFile), true);
        $this->assertSame('layers', $decoded['meta']['resource']);
        $this->assertSame(1, $decoded['meta']['count']);
        $this->assertCount(1, $decoded['data']);
        $this->assertSame('Test Layer', $decoded['data'][0]['name']);
    }

    public function testExportTagsCreatesJsonFile(): void
    {
        DB::shouldReceive('table')
            ->with('tags')
            ->andReturnSelf();
        DB::shouldReceive('where')
            ->with('type', 'category')
            ->andReturnSelf();
        DB::shouldReceive('orderBy')
            ->andReturnSelf();
        DB::shouldReceive('chunk')
            ->andReturnUsing(function ($size, $callback) {
                $callback(collect([
                    (object) ['id' => 1, 'tag' => 'Security', 'type' => 'category', 'slug' => 'security'],
                    (object) ['id' => 2, 'tag' => 'Health',   'type' => 'category', 'slug' => 'health'],
                ]));
            });

        $exporter = new JsonExporter();
        $count = $exporter->export('tags', $this->tmpDir);

        $this->assertSame(2, $count);
        $outputFile = $this->tmpDir . '/tags.json';
        $this->assertFileExists($outputFile);

        $decoded = json_decode(file_get_contents($outputFile), true);
        $this->assertSame(2, $decoded['meta']['count']);
        $this->assertCount(2, $decoded['data']);
    }

    public function testExportThrowsOnUnsupportedResource(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Unsupported resource/');

        $exporter = new JsonExporter();
        $exporter->export('unsupported_thing', $this->tmpDir);
    }

    public function testExportedJsonIsValidAndPrettyPrinted(): void
    {
        DB::shouldReceive('table')->with('layers')->andReturnSelf();
        DB::shouldReceive('orderBy')->andReturnSelf();
        DB::shouldReceive('chunk')->andReturnUsing(function ($size, $callback) {
            $callback(collect([]));
        });

        $exporter = new JsonExporter();
        $exporter->export('layers', $this->tmpDir);

        $raw = file_get_contents($this->tmpDir . '/layers.json');
        $this->assertNotFalse(strpos($raw, "\n"), 'Output should be pretty-printed');

        $decoded = json_decode($raw, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('meta', $decoded);
        $this->assertArrayHasKey('data', $decoded);
    }
}
