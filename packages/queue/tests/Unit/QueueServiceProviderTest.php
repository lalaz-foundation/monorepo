<?php declare(strict_types=1);

namespace Lalaz\Queue\Tests\Unit;

use Lalaz\Queue\Tests\Common\QueueUnitTestCase;
use Lalaz\Queue\QueueManager;
use Lalaz\Queue\Contracts\QueueDriverInterface;
use Lalaz\Queue\Drivers\InMemoryQueueDriver;
use PHPUnit\Framework\Attributes\Test;

/**
 * Unit tests for queue service provider functionality.
 *
 * Tests the driver creation and manager setup logic in isolation.
 * Note: Full service provider tests require integration with the framework container.
 *
 * @package lalaz/queue
 */
class QueueServiceProviderTest extends QueueUnitTestCase
{
    // =========================================================================
    // Driver Factory Tests
    // =========================================================================

    #[Test]
    public function driver_factory_creates_in_memory_driver_for_memory_config(): void
    {
        $helper = new DriverFactoryHelper();

        $driver = $helper->createDriver('memory');

        $this->assertInstanceOf(InMemoryQueueDriver::class, $driver);
    }

    #[Test]
    public function driver_factory_creates_in_memory_driver_for_sync_config(): void
    {
        $helper = new DriverFactoryHelper();

        $driver = $helper->createDriver('sync');

        $this->assertInstanceOf(InMemoryQueueDriver::class, $driver);
    }

    #[Test]
    public function driver_factory_creates_in_memory_driver_for_unknown_config(): void
    {
        $helper = new DriverFactoryHelper();

        $driver = $helper->createDriver('unknown');

        $this->assertInstanceOf(InMemoryQueueDriver::class, $driver);
    }

    #[Test]
    public function driver_factory_creates_in_memory_driver_by_default(): void
    {
        $helper = new DriverFactoryHelper();

        $driver = $helper->createDriver(null);

        $this->assertInstanceOf(InMemoryQueueDriver::class, $driver);
    }

    // =========================================================================
    // Integration Tests (Using In-Memory Driver)
    // =========================================================================

    #[Test]
    public function driver_can_add_jobs(): void
    {
        $helper = new DriverFactoryHelper();
        $driver = $helper->createDriver('memory');

        $result = $driver->add('TestJob', ['data' => 'value']);

        $this->assertTrue($result);
        $stats = $driver->getStats();
        $this->assertSame(1, $stats['pending']);
    }

    #[Test]
    public function queue_manager_works_with_in_memory_driver(): void
    {
        $helper = new DriverFactoryHelper();
        $driver = $helper->createDriver('memory');
        $manager = new QueueManager($driver);

        $driver->add('TestJob', ['id' => 1]);
        $driver->add('TestJob', ['id' => 2], 'emails');

        $this->assertSame(2, $manager->getStats()['pending']);
        $this->assertSame(1, $manager->getStats('emails')['pending']);
    }

    #[Test]
    public function queue_manager_can_process_jobs(): void
    {
        $helper = new DriverFactoryHelper();
        $driver = $helper->createDriver('memory');
        $manager = new QueueManager($driver);

        // Should not throw
        $manager->processJobs();
        $this->assertTrue(true);
    }

    #[Test]
    public function driver_implements_queue_driver_interface(): void
    {
        $helper = new DriverFactoryHelper();
        $driver = $helper->createDriver('memory');

        $this->assertInstanceOf(QueueDriverInterface::class, $driver);
    }

    #[Test]
    public function manager_delegates_stats_to_driver(): void
    {
        $driver = $this->createInMemoryDriver();
        $manager = new QueueManager($driver);

        $driver->add('Job1', []);
        $driver->add('Job2', [], 'high');
        $driver->add('Job3', [], 'high');

        $stats = $manager->getStats();
        $this->assertSame(3, $stats['pending']);

        $highStats = $manager->getStats('high');
        $this->assertSame(2, $highStats['pending']);
    }

    #[Test]
    public function manager_delegates_process_to_driver(): void
    {
        $mockDriver = $this->createMockDriver();
        $manager = new QueueManager($mockDriver);

        $manager->processJobs('emails');

        $this->assertSame(1, $mockDriver->processCallCount);
        $this->assertSame('emails', $mockDriver->lastProcessedQueue);
    }
}

/**
 * Helper class that replicates QueueServiceProvider's driver creation logic.
 */
class DriverFactoryHelper
{
    public function createDriver(?string $driverName): QueueDriverInterface
    {
        return match ($driverName) {
            'memory', 'sync' => new InMemoryQueueDriver(),
            default => new InMemoryQueueDriver(),
        };
    }
}
