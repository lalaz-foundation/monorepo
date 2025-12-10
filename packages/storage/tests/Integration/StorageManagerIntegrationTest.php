<?php declare(strict_types=1);

namespace Lalaz\Storage\Tests\Integration;

use Lalaz\Storage\Tests\Common\StorageIntegrationTestCase;
use Lalaz\Storage\Storage;
use Lalaz\Storage\StorageManager;
use Lalaz\Storage\Adapters\LocalStorageAdapter;
use Lalaz\Storage\Adapters\MemoryStorageAdapter;
use Lalaz\Storage\Contracts\StorageInterface;
use Lalaz\Storage\Exceptions\StorageException;

/**
 * Integration tests for StorageManager.
 *
 * Tests manager configuration, disk management, custom drivers,
 * and driver caching.
 *
 * @package lalaz/storage
 */
class StorageManagerIntegrationTest extends StorageIntegrationTestCase
{
    // =========================================================================
    // Basic Configuration Tests
    // =========================================================================

    public function test_manager_creates_with_default_configuration(): void
    {
        $manager = new StorageManager();

        $this->assertEquals('local', $manager->getDefaultDriver());
        $this->assertEmpty($manager->getDisks());
    }

    public function test_manager_accepts_configuration_array(): void
    {
        $manager = $this->createConfiguredManager();

        $this->assertEquals('local', $manager->getDefaultDriver());

        $disks = $manager->getDisks();
        $this->assertArrayHasKey('local', $disks);
        $this->assertArrayHasKey('uploads', $disks);
        $this->assertArrayHasKey('memory', $disks);
    }

    public function test_manager_can_change_default_driver(): void
    {
        $manager = $this->createConfiguredManager();

        $this->assertEquals('local', $manager->getDefaultDriver());

        $manager->setDefaultDriver('memory');

        $this->assertEquals('memory', $manager->getDefaultDriver());
    }

    // =========================================================================
    // Disk Management Tests
    // =========================================================================

    public function test_manager_resolves_local_driver(): void
    {
        $manager = $this->createConfiguredManager();

        $disk = $manager->disk('local');

        $this->assertInstanceOf(LocalStorageAdapter::class, $disk);
        $this->assertInstanceOf(StorageInterface::class, $disk);
    }

    public function test_manager_resolves_memory_driver(): void
    {
        $manager = $this->createConfiguredManager();

        $disk = $manager->disk('memory');

        $this->assertInstanceOf(MemoryStorageAdapter::class, $disk);
        $this->assertInstanceOf(StorageInterface::class, $disk);
    }

    public function test_manager_caches_resolved_drivers(): void
    {
        $manager = $this->createConfiguredManager();

        $disk1 = $manager->disk('local');
        $disk2 = $manager->disk('local');

        $this->assertSame($disk1, $disk2);
    }

    public function test_manager_returns_different_instances_for_different_disks(): void
    {
        $manager = $this->createConfiguredManager();

        $local = $manager->disk('local');
        $memory = $manager->disk('memory');

        $this->assertNotSame($local, $memory);
    }

    public function test_manager_default_disk_returns_correct_driver(): void
    {
        $manager = $this->createConfiguredManager();

        $driver = $manager->getDriver();

        $this->assertInstanceOf(LocalStorageAdapter::class, $driver);
    }

    public function test_manager_purge_clears_cached_drivers(): void
    {
        $manager = $this->createConfiguredManager();

        $disk1 = $manager->disk('local');
        $manager->purge();
        $disk2 = $manager->disk('local');

        $this->assertNotSame($disk1, $disk2);
    }

    // =========================================================================
    // Dynamic Disk Configuration Tests
    // =========================================================================

    public function test_manager_can_add_disk_dynamically(): void
    {
        $manager = $this->createConfiguredManager();

        $manager->addDisk('dynamic', [
            'driver' => 'memory',
            'public_url' => 'https://dynamic.example.com',
        ]);

        $disk = $manager->disk('dynamic');

        $this->assertInstanceOf(MemoryStorageAdapter::class, $disk);
    }

    public function test_adding_disk_clears_cached_driver(): void
    {
        $manager = $this->createConfiguredManager();

        // Get disk first
        $disk1 = $manager->disk('local');

        // Reconfigure disk
        @mkdir($this->getTempDir() . '/new_local', 0755, true);
        $manager->addDisk('local', [
            'driver' => 'local',
            'path' => $this->getTempDir() . '/new_local',
        ]);

        // Get disk again
        $disk2 = $manager->disk('local');

        $this->assertNotSame($disk1, $disk2);
    }

    // =========================================================================
    // Custom Driver Tests
    // =========================================================================

    public function test_manager_can_register_custom_driver(): void
    {
        $manager = $this->createConfiguredManager();

        $manager->extend('custom', function (array $config) {
            return new MemoryStorageAdapter($config);
        });

        $manager->addDisk('custom_disk', [
            'driver' => 'custom',
            'public_url' => 'https://custom.example.com',
        ]);

        $disk = $manager->disk('custom_disk');

        $this->assertInstanceOf(MemoryStorageAdapter::class, $disk);
    }

    public function test_custom_driver_receives_config(): void
    {
        $manager = new StorageManager();
        $receivedConfig = null;

        $manager->extend('custom', function (array $config) use (&$receivedConfig) {
            $receivedConfig = $config;
            return new MemoryStorageAdapter($config);
        });

        $manager->addDisk('test', [
            'driver' => 'custom',
            'public_url' => 'https://test.example.com',
            'custom_option' => 'custom_value',
        ]);

        $manager->disk('test');

        $this->assertIsArray($receivedConfig);
        $this->assertEquals('custom', $receivedConfig['driver']);
        $this->assertEquals('custom_value', $receivedConfig['custom_option']);
    }

    public function test_custom_driver_takes_precedence_over_built_in(): void
    {
        $manager = new StorageManager();

        // Override 'local' driver
        $manager->extend('local', function (array $config) {
            return new MemoryStorageAdapter($config);
        });

        $manager->addDisk('test', [
            'driver' => 'local',
        ]);

        $disk = $manager->disk('test');

        // Should be memory adapter because we overrode 'local'
        $this->assertInstanceOf(MemoryStorageAdapter::class, $disk);
    }

    // =========================================================================
    // Magic Method Proxy Tests
    // =========================================================================

    public function test_manager_proxies_method_calls_to_default_driver(): void
    {
        $manager = $this->createConfiguredManager();

        // These should be proxied to the default (local) driver
        $manager->put('proxied.txt', 'Proxied content');

        $this->assertTrue($manager->exists('proxied.txt'));
        $this->assertEquals('Proxied content', $manager->get('proxied.txt'));
    }

    // =========================================================================
    // Error Handling Tests
    // =========================================================================

    public function test_manager_throws_on_missing_disk_configuration(): void
    {
        $manager = new StorageManager();

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('disks.nonexistent');

        $manager->disk('nonexistent');
    }

    public function test_manager_throws_on_unknown_driver(): void
    {
        $manager = new StorageManager([
            'default' => 'test',
            'disks' => [
                'test' => [
                    'driver' => 'unknown_driver_type',
                ],
            ],
        ]);

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('Unknown storage driver');

        $manager->disk('test');
    }

    // =========================================================================
    // Integration with Facade Tests
    // =========================================================================

    public function test_manager_integrates_with_facade(): void
    {
        $manager = $this->createConfiguredManager();
        Storage::setManager($manager);

        Storage::put('facade.txt', 'Facade content');

        $this->assertTrue(Storage::exists('facade.txt'));
        $this->assertEquals('Facade content', Storage::get('facade.txt'));
    }

    public function test_facade_disk_returns_same_instance_as_manager(): void
    {
        $manager = $this->createConfiguredManager();
        Storage::setManager($manager);

        $managerDisk = $manager->disk('memory');
        $facadeDisk = Storage::disk('memory');

        $this->assertSame($managerDisk, $facadeDisk);
    }

    // =========================================================================
    // Multi-Disk Workflow Tests
    // =========================================================================

    public function test_complete_multi_disk_workflow(): void
    {
        $manager = $this->createConfiguredManager();
        @mkdir($this->getTempDir() . '/uploads', 0755, true);

        // Write to local disk
        $manager->disk('local')->put('local.txt', 'Local content');

        // Write to uploads disk
        $manager->disk('uploads')->put('upload.txt', 'Upload content');

        // Write to memory disk
        $manager->disk('memory')->put('memory.txt', 'Memory content');

        // Verify isolation
        $this->assertTrue($manager->disk('local')->exists('local.txt'));
        $this->assertFalse($manager->disk('local')->exists('upload.txt'));
        $this->assertFalse($manager->disk('local')->exists('memory.txt'));

        $this->assertTrue($manager->disk('uploads')->exists('upload.txt'));
        $this->assertFalse($manager->disk('uploads')->exists('local.txt'));

        $this->assertTrue($manager->disk('memory')->exists('memory.txt'));
        $this->assertFalse($manager->disk('memory')->exists('local.txt'));
    }
}
