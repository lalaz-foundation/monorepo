<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Support\Cache;

use Lalaz\Support\Cache\PhpArrayCache;
use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(PhpArrayCache::class)]
/**
 * Tests for the PhpArrayCache class.
 */
final class PhpArrayCacheTest extends FrameworkUnitTestCase
{
    private string $cachePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cachePath = sys_get_temp_dir() . '/test_php_array_cache.php';
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (file_exists($this->cachePath)) {
            unlink($this->cachePath);
        }
    }

    public function testcanSaveAndLoadAnArrayFromAFile(): void
    {
        $cache = new PhpArrayCache();
        $dataToSave = [
            'name' => 'Lalaz',
            'type' => 'Framework',
            'version' => 1.0,
            'features' => ['DI', 'Routing', 'Events'],
        ];

        // Save the data
        $result = $cache->save($this->cachePath, $dataToSave);
        $this->assertTrue($result);
        $this->assertFileExists($this->cachePath);

        // Load the data back
        $loadedData = $cache->load($this->cachePath);
        $this->assertSame($dataToSave, $loadedData);
    }

    public function testreturnsNullWhenLoadingANonExistentFile(): void
    {
        $cache = new PhpArrayCache();

        // Ensure the file does not exist before loading
        $this->assertFileDoesNotExist($this->cachePath);

        $loadedData = $cache->load($this->cachePath);
        $this->assertNull($loadedData);
    }

    public function testoverwritesAnExistingCacheFileWhenSavingAgain(): void
    {
        $cache = new PhpArrayCache();
        $initialData = ['status' => 'initial'];
        $newData = ['status' => 'overwritten', 'timestamp' => time()];

        // First save
        $cache->save($this->cachePath, $initialData);
        $loadedData1 = $cache->load($this->cachePath);
        $this->assertSame($initialData, $loadedData1);

        // Second save to the same file
        $cache->save($this->cachePath, $newData);
        $loadedData2 = $cache->load($this->cachePath);
        $this->assertSame($newData, $loadedData2);
        $this->assertNotSame($initialData, $loadedData2);
    }

    public function testcreatesDirectoryIfItDoesNotExist(): void
    {
        $cache = new PhpArrayCache();
        $nestedPath = sys_get_temp_dir() . '/lalaz_test_' . uniqid() . '/nested/cache.php';

        $result = $cache->save($nestedPath, ['test' => true]);

        $this->assertTrue($result);
        $this->assertFileExists($nestedPath);

        // Cleanup
        unlink($nestedPath);
        rmdir(dirname($nestedPath));
        rmdir(dirname(dirname($nestedPath)));
    }

    public function testdoesNotLeaveTempFilesOnSuccessfulSave(): void
    {
        $cache = new PhpArrayCache();
        $directory = dirname($this->cachePath);

        // Count temp files before
        $tempFilesBefore = glob($directory . '/.cache_*.tmp') ?: [];

        $cache->save($this->cachePath, ['data' => 'test']);

        // Count temp files after
        $tempFilesAfter = glob($directory . '/.cache_*.tmp') ?: [];

        $this->assertCount(count($tempFilesBefore), $tempFilesAfter);
    }

    public function testsetsProperFilePermissions(): void
    {
        $cache = new PhpArrayCache();

        $cache->save($this->cachePath, ['data' => 'test']);

        $perms = fileperms($this->cachePath) & 0777;
        $this->assertSame(0644, $perms);
    }
}
