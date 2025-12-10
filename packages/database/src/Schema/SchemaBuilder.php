<?php

declare(strict_types=1);

namespace Lalaz\Database\Schema;

use Closure;
use Lalaz\Database\Contracts\ConnectionInterface;
use Lalaz\Database\Contracts\ConnectionManagerInterface;
use Lalaz\Database\Schema\Grammars\MySqlSchemaGrammar;
use Lalaz\Database\Schema\Grammars\PostgresSchemaGrammar;
use Lalaz\Database\Schema\Grammars\SchemaGrammar;
use Lalaz\Database\Schema\Grammars\SqliteSchemaGrammar;

final class SchemaBuilder
{
    private SchemaGrammar $grammar;

    public function __construct(
        private ConnectionInterface $connection,
        private ConnectionManagerInterface $manager,
    ) {
        $this->grammar = $this->resolveGrammar($manager->driver());
    }

    public function create(string $table, Closure $callback): void
    {
        $blueprint = new Blueprint($table);
        $blueprint->creating();

        $callback($blueprint);
        $this->build($blueprint);
    }

    public function createIfNotExists(string $table, Closure $callback): void
    {
        $blueprint = new Blueprint($table);
        $blueprint->creating();

        $callback($blueprint);
        $this->build($blueprint, true, true);
    }

    public function table(string $table, Closure $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);
        $this->build($blueprint);
    }

    public function drop(string $table): void
    {
        $this->connection->query($this->grammar->compileDrop($table));
    }

    public function dropIfExists(string $table): void
    {
        $this->connection->query($this->grammar->compileDropIfExists($table));
    }

    private function build(
        Blueprint $blueprint,
        bool $ifNotExists = false,
        bool $allowExisting = false,
    ): void {
        if ($this->manager->driver() === 'sqlite') {
            $needsRebuild =
                $blueprint->getModifiedColumns() !== [] ||
                $blueprint->getDropForeigns() !== [] ||
                $blueprint->getRenames() !== [];

            if ($needsRebuild) {
                $this->sqliteRebuild($blueprint);
                return;
            }
        }

        foreach ($blueprint->toSql($this->grammar, $ifNotExists) as $sql) {
            try {
                $this->connection->query($sql);
            } catch (\Throwable $exception) {
                if (!$allowExisting) {
                    throw $exception;
                }
            }
        }
    }

    private function resolveGrammar(string $driver): SchemaGrammar
    {
        return match ($driver) {
            'mysql' => new MySqlSchemaGrammar(),
            'postgres' => new PostgresSchemaGrammar(),
            default => new SqliteSchemaGrammar(),
        };
    }

    /**
     * SQLite lacks robust ALTER support; rebuild the table when renames,
     * column modifications, or foreign key drops are requested.
     */
    private function sqliteRebuild(Blueprint $blueprint): void
    {
        $table = $blueprint->table();
        $tempTable = "{$table}_backup_" . bin2hex(random_bytes(4));

        $renameMap = [];
        foreach ($blueprint->getRenames() as $rename) {
            $renameMap[$rename['from']] = $rename['to'];
        }

        $columnsInfo = $this->connection->select(
            "PRAGMA table_info('{$table}')",
        );
        $foreignInfo = $this->connection->select(
            "PRAGMA foreign_key_list('{$table}')",
        );
        $indexList = $this->connection->select("PRAGMA index_list('{$table}')");

        $newColumns = [];
        foreach ($columnsInfo as $info) {
            $oldName = $info['name'];
            $name = $renameMap[$oldName] ?? $oldName;
            $newColumns[$name] = [
                'name' => $name,
                'source' => $oldName,
                'type' => $info['type'] ?: 'text',
                'nullable' => ($info['notnull'] ?? 0) === 0,
                'default' => $info['dflt_value'] ?? null,
                'primary' => ($info['pk'] ?? 0) === 1,
                'auto' =>
                    ($info['pk'] ?? 0) === 1 &&
                    stripos((string) $info['type'], 'int') !== false,
            ];
        }

        // Apply modifications
        foreach ($blueprint->getModifiedColumns() as $column) {
            $name = $column->name;
            if (!isset($newColumns[$name])) {
                continue;
            }
            $newColumns[$name]['type'] = $this->grammar->compileType($column);
            $newColumns[$name]['nullable'] = $column->nullable;
            $newColumns[$name]['default'] = $column->default;
            $newColumns[$name]['primary'] = $column->primary;
            $newColumns[$name]['auto'] = $column->autoIncrement;
        }

        // Add new columns
        foreach ($blueprint->getAddedColumns() as $column) {
            $newColumns[$column->name] = [
                'name' => $column->name,
                'source' => null,
                'type' => $this->grammar->compileType($column),
                'nullable' => $column->nullable,
                'default' => $column->default,
                'primary' => $column->primary,
                'auto' => $column->autoIncrement,
            ];
        }

        // Foreign keys
        $dropForeigns = $blueprint->getDropForeigns();
        $existingForeigns = [];
        foreach ($foreignInfo as $fk) {
            $from = $fk['from'];
            $name = "{$from}_foreign";
            if (in_array($name, $dropForeigns, true)) {
                continue;
            }
            $from = $renameMap[$from] ?? $from;
            $existingForeigns[] = [
                'from' => $from,
                'to' => $fk['to'],
                'table' => $fk['table'],
                'on_delete' => $fk['on_delete'] ?? null,
                'on_update' => $fk['on_update'] ?? null,
            ];
        }

        $newForeigns = [];
        foreach ($blueprint->getForeigns() as $foreign) {
            $newForeigns[] = [
                'from' => $foreign->column,
                'to' => $foreign->references,
                'table' => $foreign->on,
                'on_delete' => $foreign->onDelete,
                'on_update' => $foreign->onUpdate,
            ];
        }

        // Indexes
        $dropIndexes = $blueprint->getDropIndexes();
        $indexes = [];
        foreach ($indexList as $idx) {
            $name = $idx['name'];
            if (
                str_starts_with($name, 'sqlite_autoindex') ||
                in_array($name, $dropIndexes, true)
            ) {
                continue;
            }
            $info = $this->connection->select("PRAGMA index_info('{$name}')");
            $cols = array_map(
                fn (array $row): string => $renameMap[$row['name']] ??
                    $row['name'],
                $info,
            );
            $indexes[] = [
                'name' => $name,
                'columns' => $cols,
                'unique' => (bool) ($idx['unique'] ?? 0),
            ];
        }

        foreach ($blueprint->getIndexes() as $idx) {
            $indexes[] = [
                'name' => $idx['name'],
                'columns' => $idx['columns'],
                'unique' => $idx['unique'],
            ];
        }

        foreach (
            array_merge(
                $blueprint->getAddedColumns(),
                $blueprint->getModifiedColumns(),
            ) as $col
        ) {
            if ($col->unique) {
                $indexes[] = [
                    'name' => $this->indexName([$col->name], 'unique', $table),
                    'columns' => [$col->name],
                    'unique' => true,
                ];
            }
        }

        $foreignClauses = array_merge(
            array_map(
                fn (array $fk): string => $this->sqliteForeignClause($fk),
                $existingForeigns,
            ),
            array_map(
                fn (array $fk): string => $this->sqliteForeignClause($fk),
                $newForeigns,
            ),
        );

        $columnClauses = array_map(
            fn (array $col): string => $this->sqliteColumnClause($col),
            array_values($newColumns),
        );

        $columnsSql = implode(', ', $columnClauses);
        if ($foreignClauses !== []) {
            $columnsSql .= ', ' . implode(', ', $foreignClauses);
        }

        $createSql =
            'CREATE TABLE ' .
            $this->grammar->wrapTable($table) .
            " ({$columnsSql})";

        $this->connection->query(
            'ALTER TABLE ' .
                $this->grammar->wrapTable($table) .
                ' RENAME TO ' .
                $this->grammar->wrapTable($tempTable),
        );
        $this->connection->query($createSql);

        $sourceColumns = [];
        $targetColumns = [];
        foreach ($newColumns as $col) {
            if ($col['source'] !== null) {
                $sourceColumns[] = $this->grammar->wrap($col['source']);
                $targetColumns[] = $this->grammar->wrap($col['name']);
            }
        }

        if ($targetColumns !== []) {
            $this->connection->query(
                'INSERT INTO ' .
                    $this->grammar->wrapTable($table) .
                    ' (' .
                    implode(', ', $targetColumns) .
                    ') SELECT ' .
                    implode(', ', $sourceColumns) .
                    " FROM {$tempTable}",
            );
        }

        $this->connection->query(
            'DROP TABLE ' . $this->grammar->wrapTable($tempTable),
        );

        foreach ($indexes as $index) {
            $cols = implode(
                ', ',
                array_map([$this->grammar, 'wrap'], $index['columns']),
            );
            $type = $index['unique'] ? 'UNIQUE ' : '';
            $sql =
                "CREATE {$type}INDEX " .
                $this->grammar->wrap($index['name']) .
                ' ON ' .
                $this->grammar->wrapTable($table) .
                " ({$cols})";
            $this->connection->query($sql);
        }
    }

    private function indexName(
        array $columns,
        string $suffix,
        string $table,
    ): string {
        return $table . '_' . implode('_', $columns) . '_' . $suffix;
    }

    /**
     * @param array<string, mixed> $column
     */
    private function sqliteColumnClause(array $column): string
    {
        $sql = $this->grammar->wrap($column['name']) . ' ' . $column['type'];

        if (!$column['nullable']) {
            $sql .= ' NOT NULL';
        }

        if ($column['primary']) {
            $sql .= $column['auto']
                ? ' PRIMARY KEY AUTOINCREMENT'
                : ' PRIMARY KEY';
        }

        if ($column['default'] !== null) {
            $sql .= ' DEFAULT ' . $this->sqliteDefault($column['default']);
        }

        return $sql;
    }

    /**
     * @param array{from:string,to:string,table:string,on_delete:?string,on_update:?string} $fk
     */
    private function sqliteForeignClause(array $fk): string
    {
        $sql =
            'FOREIGN KEY (' .
            $this->grammar->wrap($fk['from']) .
            ') REFERENCES ' .
            $this->grammar->wrap($fk['table']) .
            ' (' .
            $this->grammar->wrap($fk['to']) .
            ')';

        if ($fk['on_delete']) {
            $sql .= ' ON DELETE ' . strtoupper($fk['on_delete']);
        }
        if ($fk['on_update']) {
            $sql .= ' ON UPDATE ' . strtoupper($fk['on_update']);
        }

        return $sql;
    }

    private function sqliteDefault(mixed $value): string
    {
        if (is_string($value) && str_starts_with($value, "'")) {
            return $value;
        }

        return match (true) {
            $value === null => 'NULL',
            is_bool($value) => $value ? '1' : '0',
            is_int($value), is_float($value) => (string) $value,
            default => "'" . str_replace("'", "''", (string) $value) . "'",
        };
    }
}
