<?php

/**
 * Tests for JsonImporter
 *
 * @author     Ushahidi Team <team@ushahidi.com>
 * @copyright  2024 Ushahidi
 * @license    https://www.gnu.org/licenses/agpl-3.0.html GNU Affero General Public License Version 3 (AGPL3)
 */

namespace Ushahidi\Tests\Unit\JsonIO;

use Illuminate\Support\Facades\DB;
use Ushahidi\Tests\TestCase;
use Ushahidi\Addons\JsonIO\Import\JsonImporter;

/**
 * @backupGlobals disabled
 * @preserveGlobalState disabled
 */
class JsonImporterTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/ushahidi_json_import_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '/*.json') as $file) {
            unlink($file);
        }
        rmdir($this->tmpDir);
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function writeFixture(string $resource, array $data): string
    {
        $file = $this->tmpDir . '/' . $resource . '.json';
        file_put_contents($file, json_encode([
            'meta' => ['resource' => $resource, 'exported_at' => '2024-01-01T00:00:00+00:00', 'count' => count($data)],
            'data' => $data,
        ]));
        return $file;
    }

    // -----------------------------------------------------------------------
    // Tests
    // -----------------------------------------------------------------------

    public function testImportLayersInsertsNewRecords(): void
    {
        $file = $this->writeFixture('layers', [
            ['id' => 10, 'name' => 'Layer A', 'type' => 'geojson', 'active' => 1],
        ]);

        DB::shouldReceive('transaction')->andReturnUsing(function ($callback) {
            return $callback();
        });
        DB::shouldReceive('table')->with('layers')->andReturnSelf();
        DB::shouldReceive('where')->with('id', 10)->andReturnSelf();
        DB::shouldReceive('exists')->once()->andReturn(false);
        DB::shouldReceive('insert')->once()->andReturn(true);

        $importer = new JsonImporter();
        $count = $importer->import('layers', $file, false);

        $this->assertSame(1, $count);
    }

    public function testImportLayersSkipsExistingRecordsWithoutForce(): void
    {
        $file = $this->writeFixture('layers', [
            ['id' => 10, 'name' => 'Layer A', 'type' => 'geojson', 'active' => 1],
        ]);

        DB::shouldReceive('transaction')->andReturnUsing(fn ($cb) => $cb());
        DB::shouldReceive('table')->with('layers')->andReturnSelf();
        DB::shouldReceive('where')->with('id', 10)->andReturnSelf();
        DB::shouldReceive('exists')->once()->andReturn(true);
        // insert() must NOT be called
        DB::shouldReceive('insert')->never();
        DB::shouldReceive('update')->never();

        $importer = new JsonImporter();
        $count = $importer->import('layers', $file, false);

        $this->assertSame(0, $count);
    }

    public function testImportLayersUpdatesExistingRecordsWithForce(): void
    {
        $file = $this->writeFixture('layers', [
            ['id' => 10, 'name' => 'Layer Updated', 'type' => 'geojson', 'active' => 1],
        ]);

        DB::shouldReceive('transaction')->andReturnUsing(fn ($cb) => $cb());
        DB::shouldReceive('table')->with('layers')->andReturnSelf();
        DB::shouldReceive('where')->with('id', 10)->andReturnSelf();
        DB::shouldReceive('exists')->once()->andReturn(true);
        DB::shouldReceive('update')->once()->andReturn(1);
        DB::shouldReceive('insert')->never();

        $importer = new JsonImporter();
        $count = $importer->import('layers', $file, true);

        $this->assertSame(1, $count);
    }

    public function testImportThrowsOnMissingDataKey(): void
    {
        $file = $this->tmpDir . '/bad.json';
        file_put_contents($file, json_encode(['meta' => []]));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches("/missing 'data' key/");

        $importer = new JsonImporter();
        $importer->import('layers', $file);
    }

    public function testImportThrowsOnInvalidJson(): void
    {
        $file = $this->tmpDir . '/invalid.json';
        file_put_contents($file, 'not-valid-json');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/JSON decode error/');

        $importer = new JsonImporter();
        $importer->import('layers', $file);
    }

    public function testImportThrowsOnUnsupportedResource(): void
    {
        $file = $this->writeFixture('unknown', []);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Unsupported resource/');

        $importer = new JsonImporter();
        $importer->import('unknown', $file);
    }

    public function testImportTagsInsertsNewRecords(): void
    {
        $file = $this->writeFixture('tags', [
            ['id' => 5, 'tag' => 'Security', 'type' => 'category', 'slug' => 'security'],
        ]);

        DB::shouldReceive('transaction')->andReturnUsing(fn ($cb) => $cb());
        DB::shouldReceive('table')->with('tags')->andReturnSelf();
        DB::shouldReceive('where')->with('id', 5)->andReturnSelf();
        DB::shouldReceive('exists')->once()->andReturn(false);
        DB::shouldReceive('insert')->once()->andReturn(true);

        $importer = new JsonImporter();
        $count = $importer->import('tags', $file, false);

        $this->assertSame(1, $count);
    }
}
