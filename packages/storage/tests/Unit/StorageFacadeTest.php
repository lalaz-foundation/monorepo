<?php declare(strict_types=1);

namespace Lalaz\Storage\Tests\Unit;

use Lalaz\Storage\Adapters\MemoryStorageAdapter;
use Lalaz\Storage\Contracts\StorageInterface;
use Lalaz\Storage\Storage;
use Lalaz\Storage\StorageManager;
use Lalaz\Storage\Tests\Common\StorageUnitTestCase;
use PHPUnit\Framework\Attributes\Test;

class StorageFacadeTest extends StorageUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::reset();
        $this->tempDir = $this->createTempDirectory();
    }

    protected function tearDown(): void
    {
        Storage::reset();
        $this->cleanupTempDirectory($this->tempDir);
        parent::tearDown();
    }

    #[Test]
    public function setManager_sets_manager_instance(): void
    {
        $manager = new StorageManager([
            'default' => 'memory',
            'disks' => [
                'memory' => [
                    'driver' => 'memory',
                ],
            ],
        ]);

        Storage::setManager($manager);

        $this->assertSame($manager, Storage::getManager());
    }

    #[Test]
    public function getManager_returns_manager_instance(): void
    {
        $manager = Storage::getManager();

        $this->assertInstanceOf(StorageManager::class, $manager);
    }

    #[Test]
    public function getManager_creates_new_manager_if_not_set(): void
    {
        $manager1 = Storage::getManager();
        $manager2 = Storage::getManager();

        $this->assertSame($manager1, $manager2);
    }

    #[Test]
    public function disk_returns_storage_interface(): void
    {
        $manager = new StorageManager([
            'default' => 'memory',
            'disks' => [
                'memory' => [
                    'driver' => 'memory',
                ],
            ],
        ]);
        Storage::setManager($manager);

        $disk = Storage::disk();

        $this->assertInstanceOf(StorageInterface::class, $disk);
    }

    #[Test]
    public function disk_returns_specific_disk(): void
    {
        $manager = new StorageManager([
            'default' => 'local',
            'disks' => [
                'local' => [
                    'driver' => 'local',
                    'path' => $this->tempDir,
                ],
                'memory' => [
                    'driver' => 'memory',
                ],
            ],
        ]);
        Storage::setManager($manager);

        $disk = Storage::disk('memory');

        $this->assertInstanceOf(MemoryStorageAdapter::class, $disk);
    }

    #[Test]
    public function driver_returns_default_driver(): void
    {
        $manager = new StorageManager([
            'default' => 'memory',
            'disks' => [
                'memory' => [
                    'driver' => 'memory',
                ],
            ],
        ]);
        Storage::setManager($manager);

        $driver = Storage::driver();

        $this->assertInstanceOf(MemoryStorageAdapter::class, $driver);
    }

    #[Test]
    public function reset_clears_manager(): void
    {
        $manager = new StorageManager();
        Storage::setManager($manager);

        Storage::reset();

        $this->assertNotSame($manager, Storage::getManager());
    }

    #[Test]
    public function static_call_delegates_to_driver(): void
    {
        $manager = new StorageManager([
            'default' => 'memory',
            'disks' => [
                'memory' => [
                    'driver' => 'memory',
                ],
            ],
        ]);
        Storage::setManager($manager);

        Storage::put('test.txt', 'Hello World');

        $this->assertTrue(Storage::exists('test.txt'));
        $this->assertEquals('Hello World', Storage::get('test.txt'));
    }

    #[Test]
    public function static_put_writes_file(): void
    {
        $manager = new StorageManager([
            'default' => 'memory',
            'disks' => [
                'memory' => [
                    'driver' => 'memory',
                ],
            ],
        ]);
        Storage::setManager($manager);

        $result = Storage::put('file.txt', 'content');

        $this->assertTrue($result);
    }

    #[Test]
    public function static_get_reads_file(): void
    {
        $manager = new StorageManager([
            'default' => 'memory',
            'disks' => [
                'memory' => [
                    'driver' => 'memory',
                ],
            ],
        ]);
        Storage::setManager($manager);
        Storage::put('file.txt', 'content');

        $content = Storage::get('file.txt');

        $this->assertEquals('content', $content);
    }

    #[Test]
    public function static_delete_removes_file(): void
    {
        $manager = new StorageManager([
            'default' => 'memory',
            'disks' => [
                'memory' => [
                    'driver' => 'memory',
                ],
            ],
        ]);
        Storage::setManager($manager);
        Storage::put('file.txt', 'content');

        $result = Storage::delete('file.txt');

        $this->assertTrue($result);
        $this->assertFalse(Storage::exists('file.txt'));
    }

    #[Test]
    public function static_exists_checks_file_existence(): void
    {
        $manager = new StorageManager([
            'default' => 'memory',
            'disks' => [
                'memory' => [
                    'driver' => 'memory',
                ],
            ],
        ]);
        Storage::setManager($manager);

        $this->assertFalse(Storage::exists('nonexistent.txt'));

        Storage::put('exists.txt', 'content');

        $this->assertTrue(Storage::exists('exists.txt'));
    }
}
