<?php declare(strict_types=1);

namespace Lalaz\Storage\Tests\Common;

use PHPUnit\Framework\TestCase;
use Lalaz\Storage\Storage;
use Lalaz\Storage\StorageManager;
use Lalaz\Storage\Adapters\LocalStorageAdapter;
use Lalaz\Storage\Adapters\MemoryStorageAdapter;
use Lalaz\Storage\Contracts\StorageInterface;
use Lalaz\Storage\Exceptions\StorageException;

/**
 * Base test case for Storage package integration tests.
 *
 * Provides utilities for testing complete storage workflows
 * including facade usage, disk switching, and file operations.
 *
 * @package lalaz/storage
 */
abstract class StorageIntegrationTestCase extends TestCase
{
    /**
     * Temporary directory for tests.
     */
    protected ?string $tempDir = null;

    /**
     * Storage manager instance.
     */
    protected ?StorageManager $manager = null;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        Storage::reset();
        $this->tempDir = sys_get_temp_dir() . '/lalaz_storage_integration_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    /**
     * Clean up the test environment.
     */
    protected function tearDown(): void
    {
        Storage::reset();
        $this->manager = null;

        if ($this->tempDir !== null && is_dir($this->tempDir)) {
            $this->deleteDirectoryRecursive($this->tempDir);
            $this->tempDir = null;
        }

        parent::tearDown();
    }

    // =========================================================================
    // Factory Methods
    // =========================================================================

    /**
     * Create and configure a StorageManager with common disks.
     */
    protected function createConfiguredManager(): StorageManager
    {
        $this->manager = new StorageManager([
            'default' => 'local',
            'disks' => [
                'local' => [
                    'driver' => 'local',
                    'path' => $this->tempDir,
                    'public_url' => 'https://example.com/storage',
                ],
                'uploads' => [
                    'driver' => 'local',
                    'path' => $this->tempDir . '/uploads',
                    'public_url' => 'https://example.com/uploads',
                ],
                'memory' => [
                    'driver' => 'memory',
                    'public_url' => 'https://memory.example.com',
                ],
            ],
        ]);

        Storage::setManager($this->manager);

        return $this->manager;
    }

    /**
     * Create a LocalStorageAdapter for testing.
     */
    protected function createLocalAdapter(): LocalStorageAdapter
    {
        return new LocalStorageAdapter([
            'path' => $this->tempDir,
            'public_url' => 'https://example.com/storage',
        ]);
    }

    /**
     * Create a MemoryStorageAdapter for testing.
     */
    protected function createMemoryAdapter(): MemoryStorageAdapter
    {
        return new MemoryStorageAdapter([
            'public_url' => 'https://example.com/storage',
        ]);
    }

    /**
     * Create a temporary file with content.
     */
    protected function createTempFile(string $content = 'test content', string $extension = ''): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'lalaz_test_');

        if ($extension !== '') {
            $newPath = $tempFile . '.' . $extension;
            rename($tempFile, $newPath);
            $tempFile = $newPath;
        }

        file_put_contents($tempFile, $content);
        return $tempFile;
    }

    /**
     * Create a file directly in storage.
     */
    protected function createStorageFile(string $path, string $content = 'test content'): void
    {
        $fullPath = $this->tempDir . '/' . ltrim($path, '/');
        $dir = dirname($fullPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($fullPath, $content);
    }

    /**
     * Create a directory in storage.
     */
    protected function createStorageDirectory(string $path): void
    {
        $fullPath = $this->tempDir . '/' . ltrim($path, '/');

        if (!is_dir($fullPath)) {
            mkdir($fullPath, 0755, true);
        }
    }

    // =========================================================================
    // Test Data Generators
    // =========================================================================

    /**
     * Generate sample text file content.
     */
    protected function generateTextContent(int $lines = 10): string
    {
        $content = [];
        for ($i = 1; $i <= $lines; $i++) {
            $content[] = "Line {$i}: " . bin2hex(random_bytes(16));
        }
        return implode("\n", $content);
    }

    /**
     * Generate sample JSON content.
     */
    protected function generateJsonContent(): string
    {
        return json_encode([
            'id' => uniqid(),
            'name' => 'Test File',
            'created_at' => date('Y-m-d H:i:s'),
            'data' => ['key1' => 'value1', 'key2' => 'value2'],
        ], JSON_PRETTY_PRINT);
    }

    /**
     * Generate sample binary content.
     */
    protected function generateBinaryContent(int $bytes = 1024): string
    {
        return random_bytes($bytes);
    }

    // =========================================================================
    // Storage Assertions
    // =========================================================================

    /**
     * Assert that a file exists in storage.
     */
    protected function assertStorageFileExists(StorageInterface $storage, string $path, string $message = ''): void
    {
        $this->assertTrue(
            $storage->exists($path),
            $message ?: "File '{$path}' should exist in storage"
        );
    }

    /**
     * Assert that a file does not exist in storage.
     */
    protected function assertStorageFileMissing(StorageInterface $storage, string $path, string $message = ''): void
    {
        $this->assertFalse(
            $storage->exists($path),
            $message ?: "File '{$path}' should not exist in storage"
        );
    }

    /**
     * Assert that a file has specific content.
     */
    protected function assertStorageFileContains(
        StorageInterface $storage,
        string $path,
        string $expected,
        string $message = ''
    ): void {
        $content = $storage->get($path);
        $this->assertEquals(
            $expected,
            $content,
            $message ?: "File '{$path}' should contain expected content"
        );
    }

    /**
     * Assert that file content contains a substring.
     */
    protected function assertStorageFileContainsString(
        StorageInterface $storage,
        string $path,
        string $needle,
        string $message = ''
    ): void {
        $content = $storage->get($path);
        $this->assertStringContainsString(
            $needle,
            $content,
            $message ?: "File '{$path}' should contain '{$needle}'"
        );
    }

    /**
     * Assert that a file has specific size.
     */
    protected function assertStorageFileSize(
        StorageInterface $storage,
        string $path,
        int $expectedSize,
        string $message = ''
    ): void {
        $size = $storage->size($path);
        $this->assertEquals(
            $expectedSize,
            $size,
            $message ?: "File '{$path}' should have size {$expectedSize}"
        );
    }

    /**
     * Assert that a file has specific MIME type.
     */
    protected function assertStorageFileMimeType(
        StorageInterface $storage,
        string $path,
        string $expectedMimeType,
        string $message = ''
    ): void {
        $mimeType = $storage->mimeType($path);
        $this->assertEquals(
            $expectedMimeType,
            $mimeType,
            $message ?: "File '{$path}' should have MIME type '{$expectedMimeType}'"
        );
    }

    /**
     * Assert that files are listed correctly.
     *
     * @param array<string> $expectedFiles
     */
    protected function assertStorageFiles(
        StorageInterface $storage,
        array $expectedFiles,
        string $directory = '',
        bool $recursive = false,
        string $message = ''
    ): void {
        $files = $storage->files($directory, $recursive);

        foreach ($expectedFiles as $expected) {
            $this->assertContains(
                $expected,
                $files,
                $message ?: "File '{$expected}' should be listed in storage"
            );
        }
    }

    /**
     * Assert that directories are listed correctly.
     *
     * @param array<string> $expectedDirs
     */
    protected function assertStorageDirectories(
        StorageInterface $storage,
        array $expectedDirs,
        string $directory = '',
        bool $recursive = false,
        string $message = ''
    ): void {
        $dirs = $storage->directories($directory, $recursive);

        foreach ($expectedDirs as $expected) {
            $this->assertContains(
                $expected,
                $dirs,
                $message ?: "Directory '{$expected}' should be listed in storage"
            );
        }
    }

    /**
     * Assert that a public URL is generated correctly.
     */
    protected function assertPublicUrl(
        StorageInterface $storage,
        string $path,
        string $expectedUrl,
        string $message = ''
    ): void {
        $url = $storage->getPublicUrl($path);
        $this->assertEquals(
            $expectedUrl,
            $url,
            $message ?: "Public URL for '{$path}' should be '{$expectedUrl}'"
        );
    }

    /**
     * Assert that StorageException is thrown.
     */
    protected function assertStorageExceptionThrown(callable $callback, string $expectedMessage = ''): StorageException
    {
        try {
            $callback();
            $this->fail('Expected StorageException to be thrown');
        } catch (StorageException $e) {
            if ($expectedMessage !== '') {
                $this->assertStringContainsString($expectedMessage, $e->getMessage());
            }
            return $e;
        }
    }

    // =========================================================================
    // File Operation Assertions
    // =========================================================================

    /**
     * Assert that a file can be written and read back.
     */
    protected function assertFileRoundtrip(StorageInterface $storage, string $path, string $content): void
    {
        $storage->put($path, $content);
        $this->assertStorageFileExists($storage, $path);
        $this->assertStorageFileContains($storage, $path, $content);
    }

    /**
     * Assert that a file can be uploaded from local path.
     */
    protected function assertFileUpload(StorageInterface $storage, string $localPath, string $remotePath): string
    {
        $url = $storage->upload($remotePath, $localPath);
        $this->assertNotEmpty($url);
        return $url;
    }

    /**
     * Assert that a file can be copied.
     */
    protected function assertFileCopy(StorageInterface $storage, string $from, string $to): void
    {
        $originalContent = $storage->get($from);
        $result = $storage->copy($from, $to);

        $this->assertTrue($result);
        $this->assertStorageFileExists($storage, $from);
        $this->assertStorageFileExists($storage, $to);
        $this->assertStorageFileContains($storage, $to, $originalContent);
    }

    /**
     * Assert that a file can be moved.
     */
    protected function assertFileMove(StorageInterface $storage, string $from, string $to): void
    {
        $originalContent = $storage->get($from);
        $result = $storage->move($from, $to);

        $this->assertTrue($result);
        $this->assertStorageFileMissing($storage, $from);
        $this->assertStorageFileExists($storage, $to);
        $this->assertStorageFileContains($storage, $to, $originalContent);
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Recursively delete a directory.
     */
    protected function deleteDirectoryRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }

        @rmdir($dir);
    }

    /**
     * Get the temp directory path.
     */
    protected function getTempDir(): string
    {
        return $this->tempDir;
    }
}
