<?php

declare(strict_types=1);

namespace Lalaz\Database;

use Lalaz\Database\Contracts\ConnectionInterface;
use Lalaz\Database\Contracts\ConnectionManagerInterface;
use Lalaz\Database\Query\Grammar;
use Lalaz\Database\Query\Grammars\MySqlGrammar;
use Lalaz\Database\Query\Grammars\PostgresGrammar;
use Lalaz\Database\Query\Grammars\SqliteGrammar;
use Lalaz\Database\Query\QueryBuilder;
use PDO;
use PDOStatement;

final class Connection implements ConnectionInterface
{
    private bool $ownsTransaction = false;
    private Grammar $grammar;
    private bool $hasReadReplica = false;
    private bool $stickyReads = true;
    private bool $forceWriteForReads = false;

    public function __construct(
        private ConnectionManagerInterface $manager,
        private ?PDO $pdo = null,
        ?Grammar $grammar = null,
    ) {
        $this->pdo ??= $this->manager->acquire();
        $this->grammar =
            $grammar ?? $this->resolveGrammar($this->manager->driver());
        $readConfig = $this->manager->config('read', []);
        $this->stickyReads = is_array($readConfig)
            ? (bool) ($readConfig['sticky'] ?? true)
            : true;
        $this->hasReadReplica =
            is_array($readConfig) &&
            ($readConfig['enabled'] ?? false) &&
            isset($readConfig['connections']) &&
            is_array($readConfig['connections']) &&
            $readConfig['connections'] !== [];
    }

    public function __destruct()
    {
        if ($this->ownsTransaction && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }

        if ($this->pdo !== null) {
            $this->manager->release($this->pdo);
        }
    }

    public function query(string $sql, array $bindings = []): PDOStatement
    {
        $this->forceWriteForReads = true;

        return $this->runOperation(
            'write',
            'statement',
            $sql,
            $bindings,
            function (PDO $pdo) use ($sql, $bindings): PDOStatement {
                return $this->prepareAndExecute($pdo, $sql, $bindings);
            },
        );
    }

    public function select(string $sql, array $bindings = []): array
    {
        return $this->runOperation('read', 'select', $sql, $bindings, function (
            PDO $pdo,
        ) use ($sql, $bindings): array {
            return $this->prepareAndExecute($pdo, $sql, $bindings)->fetchAll();
        });
    }

    public function insert(string $sql, array $bindings = []): bool
    {
        $this->forceWriteForReads = true;

        return $this->runOperation(
            'write',
            'insert',
            $sql,
            $bindings,
            function (PDO $pdo) use ($sql, $bindings): bool {
                return $this->prepareAndExecute($pdo, $sql, $bindings) !==
                    false;
            },
        );
    }

    public function update(string $sql, array $bindings = []): int
    {
        $this->forceWriteForReads = true;

        return $this->runOperation(
            'write',
            'update',
            $sql,
            $bindings,
            function (PDO $pdo) use ($sql, $bindings): int {
                return $this->prepareAndExecute(
                    $pdo,
                    $sql,
                    $bindings,
                )->rowCount();
            },
        );
    }

    public function delete(string $sql, array $bindings = []): int
    {
        $this->forceWriteForReads = true;

        return $this->runOperation(
            'write',
            'delete',
            $sql,
            $bindings,
            function (PDO $pdo) use ($sql, $bindings): int {
                return $this->prepareAndExecute(
                    $pdo,
                    $sql,
                    $bindings,
                )->rowCount();
            },
        );
    }

    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public function table(string $table): QueryBuilder
    {
        $builder = new QueryBuilder($this, $this->grammar);
        $builder->from($table);
        return $builder;
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function grammar(): Grammar
    {
        return $this->grammar;
    }

    /**
     * Get the database driver name.
     */
    public function getDriverName(): string
    {
        return $this->manager->driver();
    }

    /**
     * Prepare a SQL statement for execution.
     * Compatibility method for legacy code.
     */
    public function prepare(string $sql): PDOStatement
    {
        return $this->pdo->prepare($sql);
    }

    /**
     * Execute a raw SQL statement.
     * Compatibility method for legacy code.
     */
    public function exec(string $sql): int|false
    {
        $this->forceWriteForReads = true;
        return $this->pdo->exec($sql);
    }

    /**
     * Begin a database transaction.
     */
    public function beginTransaction(): bool
    {
        $this->forceWriteForReads = true;
        if (!$this->pdo->inTransaction()) {
            $result = $this->pdo->beginTransaction();
            $this->ownsTransaction = true;
            return $result;
        }
        return true;
    }

    /**
     * Commit the current transaction.
     */
    public function commit(): bool
    {
        if ($this->ownsTransaction && $this->pdo->inTransaction()) {
            $result = $this->pdo->commit();
            $this->ownsTransaction = false;
            return $result;
        }
        return true;
    }

    /**
     * Roll back the current transaction.
     */
    public function rollBack(): bool
    {
        if ($this->ownsTransaction && $this->pdo->inTransaction()) {
            $result = $this->pdo->rollBack();
            $this->ownsTransaction = false;
            return $result;
        }
        return true;
    }

    private function resolveGrammar(string $driver): Grammar
    {
        return match ($driver) {
            'mysql' => new MySqlGrammar(),
            'postgres' => new PostgresGrammar(),
            default => new SqliteGrammar(),
        };
    }

    /**
     * @template T
     * @param callable(PDO):T $operation
     * @return T
     */
    private function runOperation(
        string $role,
        string $type,
        string $sql,
        array $bindings,
        callable $operation,
    ): mixed {
        return $this->withConnection($role, function (
            PDO $pdo,
            string $driver,
            string $actualRole,
        ) use ($type, $sql, $bindings, $operation) {
            return $this->runWithRetry(
                $type,
                $sql,
                $bindings,
                fn () => $this->runWithProfile(
                    $actualRole,
                    $type,
                    $sql,
                    $bindings,
                    fn () => $operation($pdo),
                    $driver,
                ),
            );
        });
    }

    /**
     * @template T
     * @param callable(PDO, string, string):T $callback
     * @return T
     */
    private function withConnection(
        string $requestedRole,
        callable $callback,
    ): mixed {
        $useWrite =
            $requestedRole === 'write' || $this->shouldUseWriteForReads();

        $pdo = $useWrite ? $this->pdo : $this->manager->acquireRead();
        $driver = $useWrite
            ? $this->manager->driver()
            : $this->manager->readDriver();
        $role = $useWrite ? 'write' : 'read';

        try {
            return $callback($pdo, $driver, $role);
        } finally {
            if (!$useWrite) {
                $this->manager->releaseRead($pdo);
            }
        }
    }

    private function shouldUseWriteForReads(): bool
    {
        if (!$this->hasReadReplica) {
            return true;
        }

        if ($this->ownsTransaction || $this->pdo->inTransaction()) {
            return true;
        }

        if ($this->stickyReads && $this->forceWriteForReads) {
            return true;
        }

        return false;
    }

    private function prepareAndExecute(
        PDO $pdo,
        string $sql,
        array $bindings,
    ): PDOStatement {
        $statement = $pdo->prepare($sql);
        $index = 1;
        foreach (array_values($bindings) as $value) {
            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };

            $statement->bindValue($index, $value, $type);
            $index++;
        }

        $statement->execute();
        return $statement;
    }

    /**
     * @template T
     * @param callable():T $callback
     * @return T
     */
    private function runWithProfile(
        string $role,
        string $type,
        string $sql,
        array $bindings,
        callable $callback,
        string $driver,
    ): mixed {
        $start = microtime(true);
        try {
            return $callback();
        } finally {
            $duration = (microtime(true) - $start) * 1000;
            $this->manager->dispatchQueryEvent([
                'type' => $type,
                'role' => $role,
                'sql' => $sql,
                'bindings' => $bindings,
                'duration_ms' => $duration,
                'driver' => $driver,
            ]);
        }
    }

    /**
     * @template T
     * @param callable():T $callback
     * @return T
     */
    private function runWithRetry(
        string $type,
        string $sql,
        array $bindings,
        callable $callback,
    ): mixed {
        $config = $this->manager->config('retry', []);
        $attempts = (int) ($config['attempts'] ?? 1);
        $delayMs = (int) ($config['delay_ms'] ?? 0);

        $exceptions = $config['retry_on'] ?? [];
        $attempt = 0;

        beginning:
        try {
            $attempt++;
            return $callback();
        } catch (\PDOException $e) {
            $code = $e->getCode();
            if (
                $attempt < max(1, $attempts) &&
                ($exceptions === [] || in_array($code, $exceptions, true))
            ) {
                if ($delayMs > 0) {
                    usleep($delayMs * 1000);
                }
                goto beginning;
            }

            throw $e;
        }
    }
}
