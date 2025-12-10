<?php

declare(strict_types=1);

namespace Lalaz\Database\Contracts;

use Lalaz\Database\Query\QueryBuilder;
use PDO;
use PDOStatement;

interface ConnectionInterface
{
    public function query(string $sql, array $bindings = []): PDOStatement;

    public function select(string $sql, array $bindings = []): array;

    public function insert(string $sql, array $bindings = []): bool;

    public function update(string $sql, array $bindings = []): int;

    public function delete(string $sql, array $bindings = []): int;

    public function table(string $table): QueryBuilder;

    public function transaction(callable $callback): mixed;

    /**
     * Get the underlying PDO instance.
     */
    public function getPdo(): PDO;

    /**
     * Get the database driver name.
     */
    public function getDriverName(): string;

    /**
     * Prepare a SQL statement.
     */
    public function prepare(string $sql): PDOStatement;

    /**
     * Execute a raw SQL statement.
     */
    public function exec(string $sql): int|false;

    /**
     * Begin a database transaction.
     */
    public function beginTransaction(): bool;

    /**
     * Commit the current transaction.
     */
    public function commit(): bool;

    /**
     * Roll back the current transaction.
     */
    public function rollBack(): bool;
}
