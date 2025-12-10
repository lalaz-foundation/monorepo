<?php

declare(strict_types=1);

namespace Lalaz\Database\Tests\Common;

use PDO;
use PHPUnit\Framework\TestCase;
use Lalaz\Database\Connection;
use Lalaz\Database\ConnectionManager;
use Lalaz\Database\Schema\SchemaBuilder;
use Lalaz\Database\Contracts\ConnectorInterface;
use Lalaz\Database\Query\Grammar;
use Lalaz\Database\Query\Grammars\MySqlGrammar;
use Lalaz\Database\Query\Grammars\PostgresGrammar;
use Lalaz\Database\Query\Grammars\SqliteGrammar;
use Lalaz\Database\Schema\Blueprint;
use Lalaz\Database\Schema\ColumnDefinition;

/**
 * Base test case for Database unit tests.
 *
 * Provides factory methods and utilities for creating database
 * components in isolation without requiring actual database connections.
 */
abstract class DatabaseUnitTestCase extends TestCase
{
    /**
     * Create an in-memory SQLite connection manager.
     */
    protected function createSqliteManager(array $options = []): ConnectionManager
    {
        $config = array_merge([
            'driver' => 'sqlite',
            'connections' => [
                'sqlite' => ['path' => ':memory:'],
            ],
            'pool' => ['max' => 5, 'min' => 0],
        ], $options);

        return new ConnectionManager($config);
    }

    /**
     * Create an in-memory SQLite connection.
     */
    protected function createSqliteConnection(array $options = []): Connection
    {
        return new Connection($this->createSqliteManager($options));
    }

    /**
     * Create a schema builder with an in-memory SQLite connection.
     *
     * @return array{schema: SchemaBuilder, connection: Connection, manager: ConnectionManager}
     */
    protected function createSqliteComponents(): array
    {
        $manager = $this->createSqliteManager();
        $connection = new Connection($manager);
        $schema = new SchemaBuilder($connection, $manager);

        return [
            'manager' => $manager,
            'connection' => $connection,
            'schema' => $schema,
        ];
    }

    /**
     * Create a MySQL manager using an in-memory connector for testing.
     */
    protected function createMysqlManagerWithInMemory(): ConnectionManager
    {
        return new ConnectionManager(
            [
                'driver' => 'mysql',
                'connections' => ['mysql' => []],
            ],
            connectors: ['mysql' => new InMemoryConnector()]
        );
    }

    /**
     * Create a Postgres manager using an in-memory connector for testing.
     */
    protected function createPostgresManagerWithInMemory(): ConnectionManager
    {
        return new ConnectionManager(
            [
                'driver' => 'postgres',
                'connections' => ['postgres' => []],
            ],
            connectors: ['postgres' => new InMemoryConnector()]
        );
    }

    /**
     * Create a grammar instance for the given driver.
     */
    protected function createGrammar(string $driver): Grammar
    {
        return match ($driver) {
            'mysql' => new MySqlGrammar(),
            'postgres' => new PostgresGrammar(),
            default => new SqliteGrammar(),
        };
    }

    /**
     * Create a Blueprint for testing schema operations.
     */
    protected function createBlueprint(string $table): Blueprint
    {
        return new Blueprint($table);
    }

    /**
     * Create a ColumnDefinition for testing.
     */
    protected function createColumnDefinition(string $name, string $type = 'string'): ColumnDefinition
    {
        return new ColumnDefinition($name, $type);
    }

    /**
     * Execute a callback with a fresh in-memory database.
     *
     * @param callable(Connection, SchemaBuilder): mixed $callback
     * @return mixed
     */
    protected function withFreshDatabase(callable $callback): mixed
    {
        $components = $this->createSqliteComponents();
        return $callback($components['connection'], $components['schema']);
    }

    /**
     * Create a test table with common columns.
     */
    protected function createTestTable(Connection $connection, string $table = 'test_table'): void
    {
        $pdo = $connection->getPdo();
        $pdo->exec("CREATE TABLE {$table} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT,
            email TEXT,
            created_at TEXT,
            updated_at TEXT
        )");
    }

    /**
     * Insert test data into a table.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    protected function seedTestTable(Connection $connection, string $table, array $rows): void
    {
        foreach ($rows as $row) {
            $connection->table($table)->insert($row);
        }
    }

    /**
     * Assert that a table exists in the database.
     */
    protected function assertTableExists(Connection $connection, string $table): void
    {
        $result = $connection->select(
            "SELECT name FROM sqlite_master WHERE type='table' AND name=?",
            [$table]
        );
        $this->assertNotEmpty($result, "Table '{$table}' should exist");
    }

    /**
     * Assert that a table does not exist in the database.
     */
    protected function assertTableNotExists(Connection $connection, string $table): void
    {
        $result = $connection->select(
            "SELECT name FROM sqlite_master WHERE type='table' AND name=?",
            [$table]
        );
        $this->assertEmpty($result, "Table '{$table}' should not exist");
    }

    /**
     * Assert that a column exists in a table.
     */
    protected function assertColumnExists(Connection $connection, string $table, string $column): void
    {
        $columns = $connection->select("PRAGMA table_info('{$table}')");
        $names = array_map(fn(array $row): string => $row['name'], $columns);
        $this->assertContains($column, $names, "Column '{$column}' should exist in table '{$table}'");
    }

    /**
     * Assert that a column does not exist in a table.
     */
    protected function assertColumnNotExists(Connection $connection, string $table, string $column): void
    {
        $columns = $connection->select("PRAGMA table_info('{$table}')");
        $names = array_map(fn(array $row): string => $row['name'], $columns);
        $this->assertNotContains($column, $names, "Column '{$column}' should not exist in table '{$table}'");
    }

    /**
     * Assert that an index exists on a table.
     */
    protected function assertIndexExists(Connection $connection, string $table, string $indexName): void
    {
        $indexes = $connection->select("PRAGMA index_list('{$table}')");
        $names = array_map(fn(array $row): string => $row['name'], $indexes);
        $this->assertContains($indexName, $names, "Index '{$indexName}' should exist on table '{$table}'");
    }

    /**
     * Get query events captured during execution.
     *
     * @param callable $callback
     * @return array<int, array<string, mixed>>
     */
    protected function captureQueryEvents(ConnectionManager $manager, callable $callback): array
    {
        $events = [];
        $manager->listenQuery(function (array $event) use (&$events): void {
            $events[] = $event;
        });

        $callback();

        return $events;
    }
}

/**
 * In-memory SQLite connector for testing other driver grammars.
 */
final class InMemoryConnector implements ConnectorInterface
{
    public function connect(array $config): PDO
    {
        return new PDO('sqlite::memory:');
    }
}
