<?php declare(strict_types=1);

namespace Lalaz\Storage\Tests\Integration;

use Lalaz\Storage\Tests\Common\StorageIntegrationTestCase;
use Lalaz\Storage\Storage;
use Lalaz\Storage\StorageManager;
use Lalaz\Storage\Adapters\LocalStorageAdapter;
use Lalaz\Storage\Adapters\MemoryStorageAdapter;
use Lalaz\Storage\Exceptions\StorageException;

/**
 * Integration tests for complete storage workflows.
 *
 * Tests end-to-end storage operations including facade usage,
 * disk management, and file operations across different adapters.
 *
 * @package lalaz/storage
 */
class StorageFlowIntegrationTest extends StorageIntegrationTestCase
{
    // =========================================================================
    // Facade Usage Tests
    // =========================================================================

    public function test_facade_provides_static_access_to_storage(): void
    {
        $this->createConfiguredManager();

        Storage::put('test.txt', 'Hello World');

        $this->assertTrue(Storage::exists('test.txt'));
        $this->assertEquals('Hello World', Storage::get('test.txt'));
    }

    public function test_facade_can_switch_disks(): void
    {
        $this->createConfiguredManager();

        // Write to default disk
        Storage::put('default.txt', 'Default content');

        // Write to memory disk
        Storage::disk('memory')->put('memory.txt', 'Memory content');

        // Verify files are on correct disks
        $this->assertTrue(Storage::exists('default.txt'));
        $this->assertFalse(Storage::exists('memory.txt'));

        $this->assertTrue(Storage::disk('memory')->exists('memory.txt'));
        $this->assertFalse(Storage::disk('memory')->exists('default.txt'));
    }

    public function test_facade_reset_clears_manager(): void
    {
        $manager1 = $this->createConfiguredManager();
        Storage::setManager($manager1);

        Storage::reset();

        // Should create a new manager
        $manager2 = Storage::getManager();
        $this->assertNotSame($manager1, $manager2);
    }

    // =========================================================================
    // Complete File Lifecycle Tests
    // =========================================================================

    public function test_complete_file_lifecycle(): void
    {
        $adapter = $this->createLocalAdapter();

        // 1. Create file
        $adapter->put('lifecycle.txt', 'Initial content');
        $this->assertStorageFileExists($adapter, 'lifecycle.txt');
        $this->assertStorageFileContains($adapter, 'lifecycle.txt', 'Initial content');

        // 2. Update file (overwrite)
        $adapter->put('lifecycle.txt', 'Updated content');
        $this->assertStorageFileContains($adapter, 'lifecycle.txt', 'Updated content');

        // 3. Append to file
        $adapter->append('lifecycle.txt', ' - appended');
        $this->assertStorageFileContains($adapter, 'lifecycle.txt', 'Updated content - appended');

        // 4. Prepend to file
        $adapter->prepend('lifecycle.txt', 'Prefix: ');
        $this->assertStorageFileContains($adapter, 'lifecycle.txt', 'Prefix: Updated content - appended');

        // 5. Copy file
        $adapter->copy('lifecycle.txt', 'lifecycle_copy.txt');
        $this->assertStorageFileExists($adapter, 'lifecycle.txt');
        $this->assertStorageFileExists($adapter, 'lifecycle_copy.txt');

        // 6. Move file
        $adapter->move('lifecycle_copy.txt', 'lifecycle_moved.txt');
        $this->assertStorageFileMissing($adapter, 'lifecycle_copy.txt');
        $this->assertStorageFileExists($adapter, 'lifecycle_moved.txt');

        // 7. Delete files
        $adapter->delete('lifecycle.txt');
        $adapter->delete('lifecycle_moved.txt');
        $this->assertStorageFileMissing($adapter, 'lifecycle.txt');
        $this->assertStorageFileMissing($adapter, 'lifecycle_moved.txt');
    }

    public function test_upload_download_workflow(): void
    {
        $adapter = $this->createLocalAdapter();

        // Create a local file to upload
        $content = $this->generateTextContent(100);
        $localFile = $this->createTempFile($content, 'txt');

        // Upload
        $url = $adapter->upload('documents/report.txt', $localFile);
        $this->assertNotEmpty($url);
        $this->assertStringContainsString('.txt', $url);

        // The file should exist (with unique name)
        $files = $adapter->files('', true);
        $this->assertNotEmpty($files);

        // Clean up local file
        @unlink($localFile);
    }

    // =========================================================================
    // Directory Operations Tests
    // =========================================================================

    public function test_complete_directory_operations(): void
    {
        $adapter = $this->createLocalAdapter();

        // Create directory structure
        $adapter->makeDirectory('level1');
        $adapter->makeDirectory('level1/level2');
        $adapter->makeDirectory('level1/level2/level3');

        // Verify directories exist
        $dirs = $adapter->directories('', true);
        $this->assertContains('level1', $dirs);
        $this->assertContains('level1/level2', $dirs);
        $this->assertContains('level1/level2/level3', $dirs);

        // Add files to directories
        $adapter->put('level1/file1.txt', 'content1');
        $adapter->put('level1/level2/file2.txt', 'content2');
        $adapter->put('level1/level2/level3/file3.txt', 'content3');

        // List files recursively
        $files = $adapter->files('level1', true);
        $this->assertContains('level1/file1.txt', $files);
        $this->assertContains('level1/level2/file2.txt', $files);
        $this->assertContains('level1/level2/level3/file3.txt', $files);

        // Delete directory recursively
        $result = $adapter->deleteDirectory('level1', true);
        $this->assertTrue($result);
        $this->assertStorageFileMissing($adapter, 'level1/file1.txt');
    }

    public function test_nested_directory_creation_on_file_put(): void
    {
        $adapter = $this->createLocalAdapter();

        // Put should create parent directories automatically
        $adapter->put('deep/nested/path/file.txt', 'content');

        $this->assertStorageFileExists($adapter, 'deep/nested/path/file.txt');
        $this->assertStorageFileContains($adapter, 'deep/nested/path/file.txt', 'content');
    }

    // =========================================================================
    // File Metadata Tests
    // =========================================================================

    public function test_file_metadata_operations(): void
    {
        $adapter = $this->createLocalAdapter();

        $content = 'Hello World';
        $adapter->put('metadata.txt', $content);

        // Size
        $this->assertEquals(strlen($content), $adapter->size('metadata.txt'));

        // Last modified
        $before = time();
        $mtime = $adapter->lastModified('metadata.txt');
        $after = time();
        $this->assertGreaterThanOrEqual($before - 1, $mtime);
        $this->assertLessThanOrEqual($after + 1, $mtime);

        // MIME type
        $this->assertEquals('text/plain', $adapter->mimeType('metadata.txt'));

        // Public URL
        $url = $adapter->getPublicUrl('metadata.txt');
        $this->assertEquals('https://example.com/storage/metadata.txt', $url);
    }

    // =========================================================================
    // Multiple Disk Tests
    // =========================================================================

    public function test_multiple_disk_operations(): void
    {
        $manager = $this->createConfiguredManager();

        // Create uploads directory
        @mkdir($this->getTempDir() . '/uploads', 0755, true);

        // Write to different disks
        $manager->disk('local')->put('local.txt', 'Local content');
        $manager->disk('uploads')->put('upload.txt', 'Upload content');
        $manager->disk('memory')->put('memory.txt', 'Memory content');

        // Verify each disk has its own files
        $this->assertTrue($manager->disk('local')->exists('local.txt'));
        $this->assertFalse($manager->disk('local')->exists('upload.txt'));

        $this->assertTrue($manager->disk('uploads')->exists('upload.txt'));
        $this->assertFalse($manager->disk('uploads')->exists('local.txt'));

        $this->assertTrue($manager->disk('memory')->exists('memory.txt'));
        $this->assertFalse($manager->disk('memory')->exists('local.txt'));
    }

    public function test_switching_default_disk(): void
    {
        $manager = $this->createConfiguredManager();

        // Default is 'local'
        $this->assertEquals('local', $manager->getDefaultDriver());

        // Switch to memory
        $manager->setDefaultDriver('memory');
        $this->assertEquals('memory', $manager->getDefaultDriver());

        // Now default operations go to memory
        $manager->getDriver()->put('switched.txt', 'content');
        $this->assertTrue($manager->disk('memory')->exists('switched.txt'));
        $this->assertFalse($manager->disk('local')->exists('switched.txt'));
    }

    // =========================================================================
    // Custom Driver Tests
    // =========================================================================

    public function test_custom_driver_registration(): void
    {
        $manager = $this->createConfiguredManager();

        // Register custom driver
        $manager->extend('custom', function (array $config) {
            return new MemoryStorageAdapter($config);
        });

        // Add disk with custom driver
        $manager->addDisk('custom_disk', [
            'driver' => 'custom',
            'public_url' => 'https://custom.example.com',
        ]);

        // Use custom disk
        $disk = $manager->disk('custom_disk');
        $this->assertInstanceOf(MemoryStorageAdapter::class, $disk);

        $disk->put('custom.txt', 'Custom content');
        $this->assertTrue($disk->exists('custom.txt'));
    }

    // =========================================================================
    // Error Handling Tests
    // =========================================================================

    public function test_missing_disk_configuration_throws_exception(): void
    {
        $manager = $this->createConfiguredManager();

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('disks.nonexistent');

        $manager->disk('nonexistent');
    }

    public function test_unknown_driver_throws_exception(): void
    {
        $manager = new StorageManager([
            'default' => 'test',
            'disks' => [
                'test' => [
                    'driver' => 'unknown_driver',
                ],
            ],
        ]);

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('Unknown storage driver');

        $manager->disk('test');
    }

    public function test_path_traversal_prevention(): void
    {
        $adapter = $this->createLocalAdapter();

        $this->expectException(StorageException::class);

        $adapter->put('../outside.txt', 'malicious');
    }

    public function test_file_not_found_exception(): void
    {
        $adapter = $this->createLocalAdapter();

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('not found');

        $adapter->get('nonexistent.txt');
    }

    // =========================================================================
    // Performance Tests
    // =========================================================================

    public function test_handles_many_files_efficiently(): void
    {
        $adapter = $this->createLocalAdapter();

        // Create many files
        for ($i = 0; $i < 100; $i++) {
            $adapter->put("file_{$i}.txt", "Content {$i}");
        }

        // List all files
        $files = $adapter->files();
        $this->assertCount(100, $files);

        // Clean up
        foreach ($files as $file) {
            $adapter->delete($file);
        }

        $this->assertCount(0, $adapter->files());
    }

    public function test_handles_large_file_content(): void
    {
        $adapter = $this->createLocalAdapter();

        // Create a large file (1MB)
        $content = $this->generateBinaryContent(1024 * 1024);
        $adapter->put('large.bin', $content);

        $this->assertStorageFileExists($adapter, 'large.bin');
        $this->assertEquals(strlen($content), $adapter->size('large.bin'));

        // Read back
        $retrieved = $adapter->get('large.bin');
        $this->assertEquals($content, $retrieved);
    }

    // =========================================================================
    // Memory Adapter Specific Tests
    // =========================================================================

    public function test_memory_adapter_isolation(): void
    {
        $adapter1 = $this->createMemoryAdapter();
        $adapter2 = $this->createMemoryAdapter();

        $adapter1->put('file.txt', 'content1');

        $this->assertTrue($adapter1->exists('file.txt'));
        $this->assertFalse($adapter2->exists('file.txt'));
    }

    public function test_memory_adapter_clear(): void
    {
        $adapter = $this->createMemoryAdapter();

        $adapter->put('file1.txt', 'content1');
        $adapter->put('file2.txt', 'content2');
        $adapter->makeDirectory('dir1');

        $adapter->clear();

        $this->assertFalse($adapter->exists('file1.txt'));
        $this->assertFalse($adapter->exists('file2.txt'));
        $this->assertFalse($adapter->exists('dir1'));
    }
}
