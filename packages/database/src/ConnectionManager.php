<?php

declare(strict_types=1);

namespace Lalaz\Database;

use Lalaz\Database\Contracts\ConnectionManagerInterface;
use Lalaz\Database\Contracts\ConnectorInterface;
use Lalaz\Database\Drivers\MySqlConnector;
use Lalaz\Database\Drivers\PostgresConnector;
use Lalaz\Database\Drivers\SqliteConnector;
use PDO;
use Psr\Log\LoggerInterface;

final class ConnectionManager implements ConnectionManagerInterface
{
    private string $driver;

    /** @var array<string, mixed> */
    private array $configuration;

    /** @var array<string, ConnectorInterface> */
    private array $connectors = [];

    /** @var \SplQueue<PDO> */
    private \SplQueue $pool;

    /** @var \SplQueue<PDO> */
    private \SplQueue $readPool;

    private int $maxConnections;

    private int $minConnections;

    private int $totalConnections = 0;

    private int $readMaxConnections = 0;

    private int $readMinConnections = 0;

    private int $readTotalConnections = 0;

    private ?LoggerInterface $logger;
    /** @var array<int, callable> */
    private array $queryListeners = [];
    private int $acquireTimeoutMs;
    private int $readAcquireTimeoutMs;
    private bool $readEnabled = false;
    private string $readDriver;
    /** @var array<int, array<string, mixed>> */
    private array $readConnections = [];
    private int $readCursor = 0;
    private bool $stickyReads = true;

    public function __construct(
        array $config,
        ?LoggerInterface $logger = null,
        array $connectors = [],
    ) {
        $this->configuration = $config;
        $this->driver = $config['driver'] ?? 'sqlite';
        $this->connectors = $connectors + [
            'sqlite' => new SqliteConnector(),
            'mysql' => new MySqlConnector(),
            'postgres' => new PostgresConnector(),
        ];
        $this->pool = new \SplQueue();
        $this->readPool = new \SplQueue();
        $this->logger = $logger;
        $poolConfig = $config['pool'] ?? [];
        $this->maxConnections = (int) ($poolConfig['max'] ?? 5);
        $this->minConnections = (int) ($poolConfig['min'] ?? 0);
        $this->acquireTimeoutMs = (int) ($poolConfig['timeout_ms'] ?? 5000);
        $this->configureReadSettings($config);

        if ($this->logger !== null) {
            $this->listenQuery(function (array $event): void {
                $sql = $event['sql'] ?? '';
                $bindings = $event['bindings'] ?? [];
                $duration = number_format(
                    (float) ($event['duration_ms'] ?? 0),
                    2,
                );
                $type = $event['type'] ?? 'query';
                $driver = $event['driver'] ?? $this->driver;
                $role = $event['role'] ?? 'write';

                $this->logger->debug(
                    "[db:{$driver}][{$role}] {$duration}ms {$type} {$sql}",
                    ['bindings' => $bindings],
                );
            });
        }
    }

    public function acquire(): PDO
    {
        return $this->acquireWithTimeout($this->acquireTimeoutMs);
    }

    public function acquireRead(): PDO
    {
        if (!$this->readEnabled) {
            return $this->acquire();
        }

        return $this->acquireReadWithTimeout($this->readAcquireTimeoutMs);
    }

    public function acquireWithTimeout(?int $timeoutMs = null): PDO
    {
        $timeoutMs ??= $this->acquireTimeoutMs;
        $deadline = microtime(true) + $timeoutMs / 1000;

        while ($this->pool->isEmpty()) {
            if ($this->totalConnections < $this->maxConnections) {
                $pdo = $this->connector($this->driver)->connect(
                    $this->configuration['connections'][$this->driver] ?? [],
                );

                $this->configureConnection($pdo);
                $this->totalConnections++;

                return $pdo;
            }

            if ($timeoutMs <= 0 || microtime(true) >= $deadline) {
                throw new \RuntimeException('Connection pool exhausted.');
            }

            usleep(50_000);
        }

        if (!$this->pool->isEmpty()) {
            return $this->pool->dequeue();
        }

        return $this->pool->dequeue();
    }

    private function acquireReadWithTimeout(?int $timeoutMs = null): PDO
    {
        $timeoutMs ??= $this->readAcquireTimeoutMs;
        $deadline = microtime(true) + $timeoutMs / 1000;

        while ($this->readPool->isEmpty()) {
            if ($this->readTotalConnections < $this->readMaxConnections) {
                $config = $this->nextReadConnection();
                $pdo = $this->connector($this->readDriver)->connect($config);

                $this->configureConnection($pdo, $this->readDriver);
                $this->readTotalConnections++;

                return $pdo;
            }

            if ($timeoutMs <= 0 || microtime(true) >= $deadline) {
                throw new \RuntimeException('Read connection pool exhausted.');
            }

            usleep(50_000);
        }

        if (!$this->readPool->isEmpty()) {
            return $this->readPool->dequeue();
        }

        return $this->readPool->dequeue();
    }

    public function release(PDO $connection): void
    {
        if ($this->pool->count() >= $this->maxConnections) {
            $this->totalConnections--;
            return;
        }

        $this->pool->enqueue($connection);
    }

    public function releaseRead(PDO $connection): void
    {
        if (!$this->readEnabled) {
            $this->release($connection);
            return;
        }

        if ($this->readPool->count() >= $this->readMaxConnections) {
            $this->readTotalConnections--;
            return;
        }

        $this->readPool->enqueue($connection);
    }

    public function driver(): string
    {
        return $this->driver;
    }

    public function readDriver(): string
    {
        return $this->readDriver;
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return $this->configuration[$key] ?? $default;
    }

    public function poolStatus(): array
    {
        return [
            'total' => $this->totalConnections,
            'pooled' => $this->pool->count(),
            'max' => $this->maxConnections,
            'min' => $this->minConnections,
        ];
    }

    public function listenQuery(callable $listener): void
    {
        $this->queryListeners[] = $listener;
    }

    public function dispatchQueryEvent(array $event): void
    {
        foreach ($this->queryListeners as $listener) {
            $listener($event);
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function configureReadSettings(array $config): void
    {
        $read = $config['read'] ?? [];
        $readConfig = is_array($read) ? $read : [];
        $enabled = (bool) ($readConfig['enabled'] ?? false);

        $this->readEnabled = $enabled;
        $this->readDriver = strtolower(
            (string) ($readConfig['driver'] ?? $this->driver),
        );

        $pool = $readConfig['pool'] ?? [];
        $pool = is_array($pool) ? $pool : [];

        $this->readAcquireTimeoutMs =
            (int) ($pool['timeout_ms'] ?? $this->acquireTimeoutMs);
        $this->readMaxConnections =
            (int) ($pool['max'] ?? $this->maxConnections);
        $this->readMinConnections = (int) ($pool['min'] ?? 0);
        $this->stickyReads = (bool) ($readConfig['sticky'] ?? true);

        $connections = $readConfig['connections'] ?? [];
        if (is_array($connections)) {
            foreach ($connections as $connection) {
                if (is_array($connection)) {
                    $this->readConnections[] = $connection;
                }
            }
        }

        if ($this->readConnections === []) {
            $this->readEnabled = false;
            $this->readDriver = $this->driver;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function nextReadConnection(): array
    {
        if ($this->readConnections === []) {
            return $this->configuration['connections'][$this->driver] ?? [];
        }

        $config =
            $this->readConnections[
                $this->readCursor % count($this->readConnections)
            ];
        $this->readCursor++;

        return $config;
    }

    private function connector(string $name): ConnectorInterface
    {
        if (!isset($this->connectors[$name])) {
            throw new \InvalidArgumentException(
                "Database driver [{$name}] not supported.",
            );
        }

        return $this->connectors[$name];
    }

    private function configureConnection(PDO $pdo, ?string $driver = null): void
    {
        $driver ??= $this->driver;

        if ($driver === 'sqlite') {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
    }
}
