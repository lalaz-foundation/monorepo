<?php declare(strict_types=1);

namespace Lalaz\Cache\Tests\Common;

use PHPUnit\Framework\TestCase;

/**
 * Base test case for Cache package unit tests.
 *
 * Provides common utilities and helper methods for testing
 * cache stores, managers, and related functionality.
 *
 * @package lalaz/cache
 */
abstract class CacheUnitTestCase extends TestCase
{
    /**
     * Temporary directory for file-based tests.
     */
    protected ?string $tempDir = null;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        foreach ($this->getSetUpMethods() as $method) {
            if (method_exists($this, $method)) {
                $this->{$method}();
            }
        }
    }

    /**
     * Clean up the test environment.
     */
    protected function tearDown(): void
    {
        foreach (array_reverse($this->getTearDownMethods()) as $method) {
            if (method_exists($this, $method)) {
                $this->{$method}();
            }
        }

        $this->cleanupTempDir();

        parent::tearDown();
    }

    /**
     * Get the list of setup methods to call.
     *
     * @return array<int, string>
     */
    protected function getSetUpMethods(): array
    {
        return [
            'setUpCache',
        ];
    }

    /**
     * Get the list of teardown methods to call.
     *
     * @return array<int, string>
     */
    protected function getTearDownMethods(): array
    {
        return [
            'tearDownCache',
        ];
    }

    // =========================================================================
    // Directory Helpers
    // =========================================================================

    /**
     * Create a temporary directory for testing.
     */
    protected function createTempDir(string $prefix = 'lalaz_cache_test_'): string
    {
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . uniqid();

        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }

        return $this->tempDir;
    }

    /**
     * Clean up the temporary directory.
     */
    protected function cleanupTempDir(): void
    {
        if ($this->tempDir !== null && is_dir($this->tempDir)) {
            $this->recursiveDelete($this->tempDir);
            $this->tempDir = null;
        }
    }

    /**
     * Recursively delete a directory.
     */
    protected function recursiveDelete(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir) ?: [], ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;

            if (is_dir($path)) {
                $this->recursiveDelete($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }

    // =========================================================================
    // Cache Store Factory Methods
    // =========================================================================

    /**
     * Create an ArrayStore for testing.
     */
    protected function createArrayStore(string $prefix = 'test_'): \Lalaz\Cache\Stores\ArrayStore
    {
        return new \Lalaz\Cache\Stores\ArrayStore($prefix);
    }

    /**
     * Create a FileStore for testing.
     */
    protected function createFileStore(?string $dir = null, string $prefix = 'test_'): \Lalaz\Cache\Stores\FileStore
    {
        $dir = $dir ?? $this->createTempDir();
        return new \Lalaz\Cache\Stores\FileStore($dir, $prefix);
    }

    /**
     * Create a NullStore for testing.
     */
    protected function createNullStore(): \Lalaz\Cache\Stores\NullStore
    {
        return new \Lalaz\Cache\Stores\NullStore();
    }

    /**
     * Create a CacheManager for testing.
     */
    protected function createCacheManager(array $config = []): \Lalaz\Cache\CacheManager
    {
        return new \Lalaz\Cache\CacheManager($config);
    }

    /**
     * Create a PerRequestCache for testing.
     */
    protected function createPerRequestCache(): \Lalaz\Cache\PerRequestCache
    {
        return new \Lalaz\Cache\PerRequestCache();
    }

    // =========================================================================
    // Assertions
    // =========================================================================

    /**
     * Assert that a cache store has a key.
     */
    protected function assertCacheHas(
        \Lalaz\Cache\Contracts\CacheStoreInterface $store,
        string $key,
        string $message = '',
    ): void {
        $this->assertTrue(
            $store->has($key),
            $message ?: "Cache should have key '{$key}'",
        );
    }

    /**
     * Assert that a cache store does not have a key.
     */
    protected function assertCacheMissing(
        \Lalaz\Cache\Contracts\CacheStoreInterface $store,
        string $key,
        string $message = '',
    ): void {
        $this->assertFalse(
            $store->has($key),
            $message ?: "Cache should not have key '{$key}'",
        );
    }

    /**
     * Assert that a cache value equals expected.
     */
    protected function assertCacheEquals(
        \Lalaz\Cache\Contracts\CacheStoreInterface $store,
        string $key,
        mixed $expected,
        string $message = '',
    ): void {
        $this->assertSame(
            $expected,
            $store->get($key),
            $message ?: "Cache value for '{$key}' should match expected",
        );
    }

    /**
     * Assert that cache stats have expected values.
     */
    protected function assertCacheStats(
        \Lalaz\Cache\PerRequestCache $cache,
        int $expectedHits,
        int $expectedMisses,
        string $message = '',
    ): void {
        $stats = $cache->stats();
        $this->assertSame(
            $expectedHits,
            $stats['total_hits'],
            $message ?: "Expected {$expectedHits} cache hits",
        );
        $this->assertSame(
            $expectedMisses,
            $stats['total_misses'],
            $message ?: "Expected {$expectedMisses} cache misses",
        );
    }
}
