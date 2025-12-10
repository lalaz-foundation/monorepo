<?php

declare(strict_types=1);

namespace Lalaz\Database\Contracts;

use PDO;

interface ConnectionManagerInterface
{
    public function acquire(): PDO;

    public function release(PDO $connection): void;

    /**
     * Acquire a connection targeting read replicas when configured.
     */
    public function acquireRead(): PDO;

    /**
     * Release a read connection back to the pool.
     */
    public function releaseRead(PDO $connection): void;

    public function driver(): string;

    public function readDriver(): string;

    public function config(string $key, mixed $default = null): mixed;

    public function acquireWithTimeout(?int $timeoutMs = null): \PDO;

    /** @return array{total:int, pooled:int, max:int, min:int} */
    public function poolStatus(): array;

    /**
     * Register a query listener callback.
     *
     * @param callable(array<string, mixed>): void $listener
     */
    public function listenQuery(callable $listener): void;

    /**
     * Dispatch a query event to registered listeners.
     *
     * @param array<string, mixed> $event
     */
    public function dispatchQueryEvent(array $event): void;
}
