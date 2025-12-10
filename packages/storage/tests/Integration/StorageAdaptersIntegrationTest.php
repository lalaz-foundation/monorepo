<?php declare(strict_types=1);

namespace Lalaz\Storage\Tests\Integration;

use Lalaz\Storage\Tests\Common\StorageIntegrationTestCase;
use Lalaz\Storage\Adapters\LocalStorageAdapter;
use Lalaz\Storage\Adapters\MemoryStorageAdapter;
use Lalaz\Storage\Exceptions\StorageException;

/**
 * Integration tests for storage adapters.
 *
 * Tests both LocalStorageAdapter and MemoryStorageAdapter
 * with complete operation flows.
 *
 * @package lalaz/storage
 */
class StorageAdaptersIntegrationTest extends StorageIntegrationTestCase
{
    // =========================================================================
    // Local Adapter Integration Tests
    // =========================================================================

    public function test_local_adapter_complete_crud_operations(): void
    {
        $adapter = $this->createLocalAdapter();

        // Create
        $adapter->put('crud/create.txt', 'Created content');
        $this->assertStorageFileExists($adapter, 'crud/create.txt');

        // Read
        $content = $adapter->get('crud/create.txt');
        $this->assertEquals('Created content', $content);

        // Update
        $adapter->put('crud/create.txt', 'Updated content');
        $this->assertStorageFileContains($adapter, 'crud/create.txt', 'Updated content');

        // Delete
        $adapter->delete('crud/create.txt');
        $this->assertStorageFileMissing($adapter, 'crud/create.txt');
    }

    public function test_local_adapter_file_copy_preserves_content(): void
    {
        $adapter = $this->createLocalAdapter();

        $originalContent = $this->generateTextContent(50);
        $adapter->put('original.txt', $originalContent);

        $adapter->copy('original.txt', 'copied.txt');

        $this->assertStorageFileExists($adapter, 'original.txt');
        $this->assertStorageFileExists($adapter, 'copied.txt');
        $this->assertStorageFileContains($adapter, 'copied.txt', $originalContent);
    }

    public function test_local_adapter_file_move_transfers_content(): void
    {
        $adapter = $this->createLocalAdapter();

        $content = 'Content to move';
        $adapter->put('source.txt', $content);

        $adapter->move('source.txt', 'destination.txt');

        $this->assertStorageFileMissing($adapter, 'source.txt');
        $this->assertStorageFileExists($adapter, 'destination.txt');
        $this->assertStorageFileContains($adapter, 'destination.txt', $content);
    }

    public function test_local_adapter_append_and_prepend(): void
    {
        $adapter = $this->createLocalAdapter();

        $adapter->put('content.txt', 'Middle');

        $adapter->prepend('content.txt', 'Start-');
        $adapter->append('content.txt', '-End');

        $this->assertStorageFileContains($adapter, 'content.txt', 'Start-Middle-End');
    }

    public function test_local_adapter_upload_generates_unique_filename(): void
    {
        $adapter = $this->createLocalAdapter();

        $tempFile = $this->createTempFile('Upload content', 'txt');

        $url1 = $adapter->upload('document.txt', $tempFile);
        $url2 = $adapter->upload('document.txt', $tempFile);

        // URLs should be different (unique filenames)
        $this->assertNotEquals($url1, $url2);

        @unlink($tempFile);
    }

    public function test_local_adapter_directory_listing(): void
    {
        $adapter = $this->createLocalAdapter();

        // Create structure
        $adapter->put('root.txt', 'root');
        $adapter->put('dir1/file1.txt', 'file1');
        $adapter->put('dir1/file2.txt', 'file2');
        $adapter->put('dir2/file3.txt', 'file3');
        $adapter->put('dir1/subdir/file4.txt', 'file4');

        // Non-recursive files in root
        $rootFiles = $adapter->files();
        $this->assertContains('root.txt', $rootFiles);
        $this->assertNotContains('dir1/file1.txt', $rootFiles);

        // Recursive files from root
        $allFiles = $adapter->files('', true);
        $this->assertContains('root.txt', $allFiles);
        $this->assertContains('dir1/file1.txt', $allFiles);
        $this->assertContains('dir1/subdir/file4.txt', $allFiles);

        // Files in specific directory
        $dir1Files = $adapter->files('dir1');
        $this->assertContains('dir1/file1.txt', $dir1Files);
        $this->assertContains('dir1/file2.txt', $dir1Files);
        $this->assertNotContains('dir1/subdir/file4.txt', $dir1Files);

        // Directories
        $dirs = $adapter->directories();
        $this->assertContains('dir1', $dirs);
        $this->assertContains('dir2', $dirs);
    }

    public function test_local_adapter_handles_binary_files(): void
    {
        $adapter = $this->createLocalAdapter();

        $binaryContent = $this->generateBinaryContent(2048);
        $adapter->put('binary.bin', $binaryContent);

        $retrieved = $adapter->get('binary.bin');
        $this->assertEquals($binaryContent, $retrieved);
        $this->assertEquals(2048, $adapter->size('binary.bin'));
    }

    public function test_local_adapter_mime_type_detection(): void
    {
        $adapter = $this->createLocalAdapter();

        // Text file
        $adapter->put('text.txt', 'Hello World');
        $this->assertEquals('text/plain', $adapter->mimeType('text.txt'));

        // JSON file
        $adapter->put('data.json', '{"key": "value"}');
        $mimeType = $adapter->mimeType('data.json');
        $this->assertContains($mimeType, ['application/json', 'text/plain']); // Depends on system

        // HTML file
        $adapter->put('page.html', '<html><body>Test</body></html>');
        $mimeType = $adapter->mimeType('page.html');
        $this->assertContains($mimeType, ['text/html', 'text/plain']);
    }

    // =========================================================================
    // Memory Adapter Integration Tests
    // =========================================================================

    public function test_memory_adapter_complete_crud_operations(): void
    {
        $adapter = $this->createMemoryAdapter();

        // Create
        $adapter->put('crud/create.txt', 'Created content');
        $this->assertStorageFileExists($adapter, 'crud/create.txt');

        // Read
        $content = $adapter->get('crud/create.txt');
        $this->assertEquals('Created content', $content);

        // Update
        $adapter->put('crud/create.txt', 'Updated content');
        $this->assertStorageFileContains($adapter, 'crud/create.txt', 'Updated content');

        // Delete
        $adapter->delete('crud/create.txt');
        $this->assertStorageFileMissing($adapter, 'crud/create.txt');
    }

    public function test_memory_adapter_file_copy_and_move(): void
    {
        $adapter = $this->createMemoryAdapter();

        $adapter->put('original.txt', 'Original content');

        // Copy
        $adapter->copy('original.txt', 'copied.txt');
        $this->assertStorageFileExists($adapter, 'original.txt');
        $this->assertStorageFileExists($adapter, 'copied.txt');
        $this->assertStorageFileContains($adapter, 'copied.txt', 'Original content');

        // Move
        $adapter->move('copied.txt', 'moved.txt');
        $this->assertStorageFileMissing($adapter, 'copied.txt');
        $this->assertStorageFileExists($adapter, 'moved.txt');
    }

    public function test_memory_adapter_append_and_prepend(): void
    {
        $adapter = $this->createMemoryAdapter();

        $adapter->put('content.txt', 'Middle');

        $adapter->prepend('content.txt', 'Start-');
        $adapter->append('content.txt', '-End');

        $this->assertStorageFileContains($adapter, 'content.txt', 'Start-Middle-End');
    }

    public function test_memory_adapter_directory_operations(): void
    {
        $adapter = $this->createMemoryAdapter();

        // Create directories
        $adapter->makeDirectory('dir1');
        $adapter->makeDirectory('dir1/subdir');

        // Add files
        $adapter->put('dir1/file1.txt', 'content1');
        $adapter->put('dir1/subdir/file2.txt', 'content2');

        // List directories
        $dirs = $adapter->directories();
        $this->assertContains('dir1', $dirs);

        // List files
        $files = $adapter->files('dir1', true);
        $this->assertContains('dir1/file1.txt', $files);
        $this->assertContains('dir1/subdir/file2.txt', $files);

        // Delete directory recursively
        $result = $adapter->deleteDirectory('dir1', true);
        $this->assertTrue($result);
        $this->assertStorageFileMissing($adapter, 'dir1/file1.txt');
    }

    public function test_memory_adapter_metadata(): void
    {
        $adapter = $this->createMemoryAdapter();

        $content = 'Test content for metadata';
        $adapter->put('meta.txt', $content);

        // Size
        $this->assertEquals(strlen($content), $adapter->size('meta.txt'));

        // Last modified
        $mtime = $adapter->lastModified('meta.txt');
        $this->assertGreaterThan(0, $mtime);
        $this->assertLessThanOrEqual(time(), $mtime);

        // MIME type
        $mimeType = $adapter->mimeType('meta.txt');
        $this->assertEquals('text/plain', $mimeType);

        // Public URL
        $url = $adapter->getPublicUrl('meta.txt');
        $this->assertEquals('https://example.com/storage/meta.txt', $url);
    }

    public function test_memory_adapter_clear_removes_all(): void
    {
        $adapter = $this->createMemoryAdapter();

        $adapter->put('file1.txt', 'content1');
        $adapter->put('file2.txt', 'content2');
        $adapter->makeDirectory('dir1');

        $files = $adapter->getFiles();
        $dirs = $adapter->getDirectories();

        $this->assertNotEmpty($files);
        $this->assertNotEmpty($dirs);

        $adapter->clear();

        $this->assertEmpty($adapter->getFiles());
        $this->assertEmpty($adapter->getDirectories());
    }

    public function test_memory_adapter_upload_from_local_file(): void
    {
        $adapter = $this->createMemoryAdapter();

        $content = 'Uploaded content';
        $tempFile = $this->createTempFile($content);

        $url = $adapter->upload('uploaded.txt', $tempFile);

        $this->assertNotEmpty($url);

        // File should be in memory with unique name
        $files = array_keys($adapter->getFiles());
        $this->assertNotEmpty($files);

        @unlink($tempFile);
    }

    // =========================================================================
    // Adapter Comparison Tests
    // =========================================================================

    public function test_both_adapters_have_consistent_behavior(): void
    {
        $localAdapter = $this->createLocalAdapter();
        $memoryAdapter = $this->createMemoryAdapter();

        $content = 'Consistent content';

        // Put
        $localAdapter->put('test.txt', $content);
        $memoryAdapter->put('test.txt', $content);

        // Get
        $this->assertEquals($content, $localAdapter->get('test.txt'));
        $this->assertEquals($content, $memoryAdapter->get('test.txt'));

        // Exists
        $this->assertTrue($localAdapter->exists('test.txt'));
        $this->assertTrue($memoryAdapter->exists('test.txt'));

        // Size
        $this->assertEquals(strlen($content), $localAdapter->size('test.txt'));
        $this->assertEquals(strlen($content), $memoryAdapter->size('test.txt'));

        // Delete
        $this->assertTrue($localAdapter->delete('test.txt'));
        $this->assertTrue($memoryAdapter->delete('test.txt'));

        // After delete
        $this->assertFalse($localAdapter->exists('test.txt'));
        $this->assertFalse($memoryAdapter->exists('test.txt'));
    }

    // =========================================================================
    // Error Cases
    // =========================================================================

    public function test_local_adapter_throws_on_missing_file(): void
    {
        $adapter = $this->createLocalAdapter();

        $this->expectException(StorageException::class);
        $adapter->get('nonexistent.txt');
    }

    public function test_memory_adapter_throws_on_missing_file(): void
    {
        $adapter = $this->createMemoryAdapter();

        $this->expectException(StorageException::class);
        $adapter->get('nonexistent.txt');
    }

    public function test_local_adapter_copy_throws_on_missing_source(): void
    {
        $adapter = $this->createLocalAdapter();

        $this->expectException(StorageException::class);
        $adapter->copy('nonexistent.txt', 'destination.txt');
    }

    public function test_memory_adapter_copy_throws_on_missing_source(): void
    {
        $adapter = $this->createMemoryAdapter();

        $this->expectException(StorageException::class);
        $adapter->copy('nonexistent.txt', 'destination.txt');
    }
}
