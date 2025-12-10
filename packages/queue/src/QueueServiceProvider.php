<?php

declare(strict_types=1);

namespace Lalaz\Queue;

use Lalaz\Config\Config;
use Lalaz\Container\ServiceProvider;
use Lalaz\Database\Connection;
use Lalaz\Queue\Contracts\QueueDriverInterface;
use Lalaz\Queue\Drivers\InMemoryQueueDriver;
use Lalaz\Queue\Drivers\MySQLQueueDriver;
use Lalaz\Queue\Drivers\PostgresQueueDriver;
use Lalaz\Queue\Drivers\SQLiteQueueDriver;

/**
 * Service provider for the Queue package.
 *
 * Registers the queue driver and manager in the container.
 */
final class QueueServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->singleton(QueueDriverInterface::class, function () {
            return $this->createDriver();
        });

        $this->singleton(QueueManager::class, function () {
            /** @var QueueDriverInterface $driver */
            $driver = $this->container->resolve(QueueDriverInterface::class);
            return new QueueManager($driver);
        });
    }

    private function createDriver(): QueueDriverInterface
    {
        $driverName = Config::get('queue.driver', 'memory');

        return match ($driverName) {
            'database', 'mysql', 'pgsql', 'sqlite' => $this->createDatabaseDriver($driverName),
            'memory', 'sync' => new InMemoryQueueDriver(),
            default => new InMemoryQueueDriver(),
        };
    }

    private function createDatabaseDriver(string $driverName): QueueDriverInterface
    {
        /** @var Connection $connection */
        $connection = $this->container->resolve(Connection::class);
        $dbDriver = $connection->getDriverName();

        // Use specific driver based on database type
        return match ($dbDriver) {
            'mysql' => new MySQLQueueDriver($connection),
            'pgsql', 'postgres' => new PostgresQueueDriver($connection),
            'sqlite' => new SQLiteQueueDriver($connection),
            default => new SQLiteQueueDriver($connection),
        };
    }
}
