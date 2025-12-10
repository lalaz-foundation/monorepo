<?php declare(strict_types=1);

namespace Lalaz\Cache\Tests\Common;

use PHPUnit\Framework\TestCase;

/**
 * Base test case for Cache package integration tests.
 *
 * Provides setup for tests that require actual cache stores
 * like Redis or APCu, or tests that need database connections.
 *
 * @package lalaz/cache
 */
abstract class CacheIntegrationTestCase extends TestCase
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
            'setUpIntegration',
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
            'tearDownIntegration',
        ];
    }

    // =========================================================================
    // Directory Helpers
    // =========================================================================

    /**
     * Create a temporary directory for testing.
     */
    protected function createTempDir(string $prefix = 'lalaz_cache_int_'): string
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
    // Skip Helpers
    // =========================================================================

    /**
     * Skip test if Redis extension is not available.
     */
    protected function skipIfRedisNotAvailable(): void
    {
        if (!extension_loaded('redis') && !class_exists(\Predis\Client::class)) {
            $this->markTestSkipped('Redis extension or Predis not available.');
        }

        try {
            if (extension_loaded('redis')) {
                $redis = new \Redis();
                if (!@$redis->connect('127.0.0.1', 6379, 1)) {
                    throw new \Exception('Connection failed');
                }
            } elseif (class_exists(\Predis\Client::class)) {
                $client = new \Predis\Client([
                    'host' => '127.0.0.1',
                    'port' => 6379,
                    'timeout' => 1.0,
                ]);
                $client->connect();
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('Redis server not available: ' . $e->getMessage());
        }
    }

    /**
     * Skip test if APCu extension is not available.
     */
    protected function skipIfApcuNotAvailable(): void
    {
        if (!extension_loaded('apcu') || !function_exists('apcu_enabled') || !apcu_enabled()) {
            $this->markTestSkipped('APCu extension not available or not enabled.');
        }
    }
}
