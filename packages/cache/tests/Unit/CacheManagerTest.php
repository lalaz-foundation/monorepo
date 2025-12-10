<?php declare(strict_types=1);

namespace Lalaz\Cache\Tests\Unit;

use Lalaz\Cache\Tests\Common\CacheUnitTestCase;
use Lalaz\Cache\CacheManager;
use Lalaz\Cache\Stores\ArrayStore;
use Lalaz\Cache\Stores\FileStore;
use Lalaz\Cache\Stores\NullStore;

class CacheManagerTest extends CacheUnitTestCase
{
    private string $cacheDir;

    protected function setUpCache(): void
    {
        $this->cacheDir = $this->createTempDir();
    }

    protected function tearDownCache(): void
    {
        $this->cleanupTempDir($this->cacheDir);
    }

    // =========================================================================
    // Default Store
    // =========================================================================

    public function test_returns_array_store_by_default(): void
    {
        $manager = new CacheManager();
        $store = $manager->store();

        $this->assertInstanceOf(ArrayStore::class, $store);
    }

    public function test_returns_same_store_instance_on_multiple_calls(): void
    {
        $manager = new CacheManager();

        $store1 = $manager->store();
        $store2 = $manager->store();

        $this->assertSame($store1, $store2);
    }

    // =========================================================================
    // Disabled Cache
    // =========================================================================

    public function test_respects_disabled_flag(): void
    {
        $manager = new CacheManager(['enabled' => false]);
        $store = $manager->store();

        $this->assertInstanceOf(NullStore::class, $store);
    }

    public function test_disabled_cache_returns_null_store_regardless_of_driver(): void
    {
        $manager = new CacheManager([
            'enabled' => false,
            'driver' => 'array',
        ]);

        $this->assertInstanceOf(NullStore::class, $manager->store());
    }

    // =========================================================================
    // File Store
    // =========================================================================

    public function test_creates_file_store_when_configured(): void
    {
        $manager = new CacheManager([
            'driver' => 'file',
            'stores' => [
                'file' => ['driver' => 'file', 'path' => $this->cacheDir],
            ],
        ]);

        $store = $manager->store();

        $this->assertInstanceOf(FileStore::class, $store);
    }

    public function test_file_store_with_prefix(): void
    {
        $manager = new CacheManager([
            'driver' => 'file',
            'prefix' => 'app_',
            'stores' => [
                'file' => ['driver' => 'file', 'path' => $this->cacheDir],
            ],
        ]);

        $store = $manager->store();
        $this->assertInstanceOf(FileStore::class, $store);
    }

    // =========================================================================
    // Array Store
    // =========================================================================

    public function test_creates_array_store_when_configured(): void
    {
        $manager = new CacheManager([
            'driver' => 'array',
            'stores' => [
                'array' => ['driver' => 'array'],
            ],
        ]);

        $store = $manager->store();

        $this->assertInstanceOf(ArrayStore::class, $store);
    }

    public function test_array_store_with_prefix(): void
    {
        $manager = new CacheManager([
            'driver' => 'array',
            'prefix' => 'test_',
            'stores' => [
                'array' => ['driver' => 'array'],
            ],
        ]);

        $store = $manager->store();
        $this->assertInstanceOf(ArrayStore::class, $store);
    }

    // =========================================================================
    // Null Store (via disabled)
    // =========================================================================

    public function test_returns_null_store_when_cache_disabled(): void
    {
        $manager = new CacheManager([
            'enabled' => false,
        ]);

        $store = $manager->store();

        $this->assertInstanceOf(NullStore::class, $store);
    }

    // =========================================================================
    // Named Stores
    // =========================================================================

    public function test_can_retrieve_named_store(): void
    {
        $manager = new CacheManager([
            'driver' => 'array',
            'stores' => [
                'array' => ['driver' => 'array'],
                'file' => ['driver' => 'file', 'path' => $this->cacheDir],
            ],
        ]);

        $arrayStore = $manager->store('array');
        $fileStore = $manager->store('file');

        $this->assertInstanceOf(ArrayStore::class, $arrayStore);
        $this->assertInstanceOf(FileStore::class, $fileStore);
    }

    public function test_named_stores_are_cached(): void
    {
        $manager = new CacheManager([
            'driver' => 'array',
            'stores' => [
                'array' => ['driver' => 'array'],
                'file' => ['driver' => 'file', 'path' => $this->cacheDir],
            ],
        ]);

        $store1 = $manager->store('file');
        $store2 = $manager->store('file');

        $this->assertSame($store1, $store2);
    }

    // =========================================================================
    // Configuration Options
    // =========================================================================

    public function test_uses_default_ttl_from_config(): void
    {
        $manager = new CacheManager([
            'driver' => 'array',
            'ttl' => 3600,
            'stores' => [
                'array' => ['driver' => 'array'],
            ],
        ]);

        $store = $manager->store();
        $this->assertInstanceOf(ArrayStore::class, $store);
    }

    public function test_empty_config_uses_defaults(): void
    {
        $manager = new CacheManager([]);
        $store = $manager->store();

        $this->assertInstanceOf(ArrayStore::class, $store);
    }

    // =========================================================================
    // Store Operations
    // =========================================================================

    public function test_stores_data_through_manager(): void
    {
        $manager = new CacheManager(['driver' => 'array']);
        $store = $manager->store();

        $store->set('key', 'value');
        $this->assertSame('value', $store->get('key'));
    }

    public function test_different_stores_are_isolated(): void
    {
        $manager = new CacheManager([
            'driver' => 'array',
            'stores' => [
                'store1' => ['driver' => 'array'],
                'store2' => ['driver' => 'array'],
            ],
        ]);

        $store1 = $manager->store('store1');
        $store2 = $manager->store('store2');

        $store1->set('key', 'value1');
        $store2->set('key', 'value2');

        $this->assertSame('value1', $store1->get('key'));
        $this->assertSame('value2', $store2->get('key'));
    }
}
