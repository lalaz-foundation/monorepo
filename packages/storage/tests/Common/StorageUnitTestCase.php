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
 * Base test case for Storage package unit tests.
 *
 * Provides factory methods and assertions for testing
 * storage adapters and managers.
 *
 * @package lalaz/storage
 */
abstract class StorageUnitTestCase extends TestCase
{
    /**
     * Temporary directory for tests.
     */
    protected ?string $tempDir = null;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        Storage::reset();
    }

    /**
     * Clean up the test environment.
     */
    protected function tearDown(): void
    {
        Storage::reset();

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
     * Create a temporary directory for testing.
     */
    protected function createTempDirectory(): string
    {
        $this->tempDir = sys_get_temp_dir() . '/lalaz_storage_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        return $this->tempDir;
    }

    /**
     * Create a LocalStorageAdapter for testing.
     *
     * @param string|array<string, mixed>|null $pathOrConfig
     */
    protected function createLocalAdapter(string|array|null $pathOrConfig = null): LocalStorageAdapter
    {
        if (is_string($pathOrConfig)) {
            $config = ['path' => $pathOrConfig, 'public_url' => 'https://example.com/storage'];
        } elseif (is_array($pathOrConfig)) {
            $tempDir = $this->tempDir ?? $this->createTempDirectory();
            $config = array_merge([
                'path' => $tempDir,
                'public_url' => 'https://example.com/storage',
            ], $pathOrConfig);
        } else {
            $tempDir = $this->tempDir ?? $this->createTempDirectory();
            $config = ['path' => $tempDir, 'public_url' => 'https://example.com/storage'];
        }

        return new LocalStorageAdapter($config);
    }

    /**
     * Create a MemoryStorageAdapter for testing.
     *
     * @param array<string, mixed> $config
     */
    protected function createMemoryAdapter(array $config = []): MemoryStorageAdapter
    {
        return new MemoryStorageAdapter(array_merge([
            'public_url' => 'https://example.com/storage',
        ], $config));
    }

    /**
     * Create a StorageManager for testing.
     *
     * @param string|array<string, mixed>|null $pathOrConfig
     */
    protected function createStorageManager(string|array|null $pathOrConfig = null): StorageManager
    {
        if (is_string($pathOrConfig)) {
            $tempDir = $pathOrConfig;
        } else {
            $tempDir = $this->tempDir ?? $this->createTempDirectory();
        }

        $defaultConfig = [
            'default' => 'local',
            'disks' => [
                'local' => [
                    'driver' => 'local',
                    'path' => $tempDir,
                    'public_url' => 'https://example.com/storage',
                ],
                'memory' => [
                    'driver' => 'memory',
                    'public_url' => 'https://memory.example.com',
                ],
            ],
        ];

        if (is_array($pathOrConfig)) {
            return new StorageManager(array_merge($defaultConfig, $pathOrConfig));
        }

        return new StorageManager($defaultConfig);
    }

    /**
     * Alias for createStorageManager for backward compatibility.
     *
     * @param string|array<string, mixed>|null $pathOrConfig
     */
    protected function createManager(string|array|null $pathOrConfig = null): StorageManager
    {
        return $this->createStorageManager($pathOrConfig);
    }

    /**
     * Create a temporary file with content.
     */
    protected function createTempFile(string $content = 'test content'): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'lalaz_test_');
        file_put_contents($tempFile, $content);
        return $tempFile;
    }

    /**
     * Create a file in the storage directory.
     */
    protected function createStorageFile(string $path, string $content = 'test content'): string
    {
        $tempDir = $this->tempDir ?? $this->createTempDirectory();
        $fullPath = $tempDir . '/' . ltrim($path, '/');

        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($fullPath, $content);
        return $fullPath;
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
     * Assert that a directory exists in storage.
     */
    protected function assertStorageDirectoryExists(string $path, string $message = ''): void
    {
        $tempDir = $this->tempDir;
        $fullPath = $tempDir . '/' . ltrim($path, '/');
        $this->assertTrue(
            is_dir($fullPath),
            $message ?: "Directory '{$path}' should exist in storage"
        );
    }

    /**
     * Assert that a public URL is generated correctly.
     */
    protected function assertPublicUrl(StorageInterface $storage, string $path, string $expectedUrl, string $message = ''): void
    {
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
    protected function assertStorageExceptionThrown(callable $callback, string $message = ''): void
    {
        $this->expectException(StorageException::class);
        if ($message !== '') {
            $this->expectExceptionMessage($message);
        }
        $callback();
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
        return $this->tempDir ?? $this->createTempDirectory();
    }

    /**
     * Clean up a temporary directory.
     */
    protected function cleanupTempDirectory(?string $dir = null): void
    {
        $dirToClean = $dir ?? $this->tempDir;

        if ($dirToClean !== null && is_dir($dirToClean)) {
            $this->deleteDirectoryRecursive($dirToClean);
        }

        if ($dir === null || $dir === $this->tempDir) {
            $this->tempDir = null;
        }
    }
}
