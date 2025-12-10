<?php declare(strict_types=1);

namespace Lalaz\Storage\Tests\Unit;

use Lalaz\Storage\Adapters\LocalStorageAdapter;
use Lalaz\Storage\Adapters\MemoryStorageAdapter;
use Lalaz\Storage\Contracts\StorageInterface;
use Lalaz\Storage\Exceptions\StorageException;
use Lalaz\Storage\StorageManager;
use Lalaz\Storage\Tests\Common\StorageUnitTestCase;
use PHPUnit\Framework\Attributes\Test;

class StorageManagerTest extends StorageUnitTestCase
{
    private StorageManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = $this->createTempDirectory();
        $this->manager = $this->createManager($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->cleanupTempDirectory($this->tempDir);
        parent::tearDown();
    }

    #[Test]
    public function disk_returns_default_driver(): void
    {
        $driver = $this->manager->disk();

        $this->assertInstanceOf(StorageInterface::class, $driver);
        $this->assertInstanceOf(LocalStorageAdapter::class, $driver);
    }

    #[Test]
    public function disk_returns_specific_driver(): void
    {
        $driver = $this->manager->disk('memory');

        $this->assertInstanceOf(StorageInterface::class, $driver);
        $this->assertInstanceOf(MemoryStorageAdapter::class, $driver);
    }

    #[Test]
    public function disk_caches_driver_instances(): void
    {
        $driver1 = $this->manager->disk('local');
        $driver2 = $this->manager->disk('local');

        $this->assertSame($driver1, $driver2);
    }

    #[Test]
    public function getDriver_returns_default_driver(): void
    {
        $driver = $this->manager->getDriver();

        $this->assertInstanceOf(LocalStorageAdapter::class, $driver);
    }

    #[Test]
    public function getDefaultDriver_returns_default_driver_name(): void
    {
        $this->assertEquals('local', $this->manager->getDefaultDriver());
    }

    #[Test]
    public function setDefaultDriver_changes_default_driver(): void
    {
        $this->manager->setDefaultDriver('memory');

        $this->assertEquals('memory', $this->manager->getDefaultDriver());
        $this->assertInstanceOf(MemoryStorageAdapter::class, $this->manager->getDriver());
    }

    #[Test]
    public function disk_throws_exception_for_unknown_disk(): void
    {
        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('disks.nonexistent');

        $this->manager->disk('nonexistent');
    }

    #[Test]
    public function disk_throws_exception_for_unknown_driver(): void
    {
        $manager = new StorageManager([
            'default' => 'test',
            'disks' => [
                'test' => [
                    'driver' => 'unknown',
                ],
            ],
        ]);

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('Unknown storage driver');

        $manager->disk('test');
    }

    #[Test]
    public function extend_registers_custom_driver(): void
    {
        $this->manager->extend('custom', function ($config) {
            return new MemoryStorageAdapter($config);
        });

        $this->manager->addDisk('custom_disk', [
            'driver' => 'custom',
            'public_url' => 'https://custom.example.com',
        ]);

        $driver = $this->manager->disk('custom_disk');

        $this->assertInstanceOf(MemoryStorageAdapter::class, $driver);
    }

    #[Test]
    public function addDisk_adds_new_disk_configuration(): void
    {
        $this->manager->addDisk('new_disk', [
            'driver' => 'memory',
            'public_url' => 'https://new.example.com',
        ]);

        $disks = $this->manager->getDisks();

        $this->assertArrayHasKey('new_disk', $disks);
        $this->assertEquals('memory', $disks['new_disk']['driver']);
    }

    #[Test]
    public function addDisk_clears_cached_driver(): void
    {
        $driver1 = $this->manager->disk('local');

        $this->manager->addDisk('local', [
            'driver' => 'memory',
            'public_url' => 'https://changed.example.com',
        ]);

        $driver2 = $this->manager->disk('local');

        $this->assertNotSame($driver1, $driver2);
        $this->assertInstanceOf(MemoryStorageAdapter::class, $driver2);
    }

    #[Test]
    public function purge_clears_all_cached_drivers(): void
    {
        $driver1 = $this->manager->disk('local');
        $driver2 = $this->manager->disk('memory');

        $this->manager->purge();

        $driver3 = $this->manager->disk('local');
        $driver4 = $this->manager->disk('memory');

        $this->assertNotSame($driver1, $driver3);
        $this->assertNotSame($driver2, $driver4);
    }

    #[Test]
    public function magic_call_delegates_to_default_driver(): void
    {
        $this->manager->put('test.txt', 'Hello World');

        $this->assertTrue($this->manager->exists('test.txt'));
        $this->assertEquals('Hello World', $this->manager->get('test.txt'));
    }

    #[Test]
    public function getDisks_returns_all_disk_configurations(): void
    {
        $disks = $this->manager->getDisks();

        $this->assertArrayHasKey('local', $disks);
        $this->assertArrayHasKey('memory', $disks);
    }

    #[Test]
    public function constructor_with_empty_config_uses_defaults(): void
    {
        $manager = new StorageManager();

        $this->assertEquals('local', $manager->getDefaultDriver());
        $this->assertEmpty($manager->getDisks());
    }

    #[Test]
    public function constructor_accepts_default_driver_config(): void
    {
        $manager = new StorageManager([
            'default' => 'memory',
        ]);

        $this->assertEquals('memory', $manager->getDefaultDriver());
    }
}
