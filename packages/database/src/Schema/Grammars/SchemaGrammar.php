<?php

declare(strict_types=1);

namespace Lalaz\Database\Schema\Grammars;

use Lalaz\Database\Schema\Blueprint;
use Lalaz\Database\Schema\ColumnDefinition;
use Lalaz\Database\Schema\ForeignDefinition;

abstract class SchemaGrammar
{
    public function compileCreate(
        Blueprint $blueprint,
        bool $ifNotExists = false,
    ): string {
        $columns = $this->columnize($blueprint->getColumns());
        $foreign = $this->inlineForeigns($blueprint);

        $table = $this->wrapTable($blueprint->table());
        $clause = $ifNotExists ? ' IF NOT EXISTS' : '';
        $definitions = $columns;
        if ($foreign !== '') {
            $definitions .= ', ' . $foreign;
        }
        return "CREATE TABLE{$clause} {$table} ({$definitions})";
    }

    /**
     * @return array<int, string>
     */
    public function compileAdd(Blueprint $blueprint): array
    {
        $sql = [];
        foreach ($blueprint->getAddedColumns() as $column) {
            $definition = $this->wrapColumn($column);
            $sql[] =
                'ALTER TABLE ' .
                $this->wrapTable($blueprint->table()) .
                " ADD COLUMN {$definition}";
        }
        return $sql;
    }

    /**
     * @return array<int, string>
     */
    public function compileModify(Blueprint $blueprint): array
    {
        $sql = [];
        foreach ($blueprint->getModifiedColumns() as $column) {
            $sql[] = $this->compileModifyColumn($blueprint, $column);
        }
        return array_filter($sql);
    }

    /**
     * @return array<int, string>
     */
    public function compileRenames(Blueprint $blueprint): array
    {
        $sql = [];
        foreach ($blueprint->getRenames() as $rename) {
            $sql[] = $this->compileRenameColumn(
                $blueprint,
                $rename['from'],
                $rename['to'],
            );
        }

        return array_filter($sql);
    }

    /**
     * @return array<int, string>
     */
    public function compileIndexes(Blueprint $blueprint): array
    {
        $sql = [];
        foreach ($blueprint->getIndexes() as $index) {
            $sql[] = $this->compileIndex(
                $blueprint,
                $index['columns'],
                $index['name'],
                $index['unique'],
            );
        }
        return array_filter($sql);
    }

    /**
     * @return array<int, string>
     */
    public function compileForeigns(Blueprint $blueprint): array
    {
        $sql = [];
        foreach ($blueprint->getForeigns() as $foreign) {
            $sql[] = $this->compileForeign($blueprint, $foreign);
        }

        return array_filter($sql);
    }

    /**
     * @return array<int, string>
     */
    public function compileDropIndexes(Blueprint $blueprint): array
    {
        $sql = [];
        foreach ($blueprint->getDropIndexes() as $index) {
            $sql[] = $this->compileDropIndex($blueprint, $index);
        }
        return array_filter($sql);
    }

    /**
     * @return array<int, string>
     */
    public function compileDropForeigns(Blueprint $blueprint): array
    {
        $sql = [];
        foreach ($blueprint->getDropForeigns() as $name) {
            $sql[] = $this->compileDropForeign($blueprint, $name);
        }
        return array_filter($sql);
    }

    public function compileDrop(string $table): string
    {
        return 'DROP TABLE ' . $this->wrapTable($table);
    }

    public function compileDropIfExists(string $table): string
    {
        return 'DROP TABLE IF EXISTS ' . $this->wrapTable($table);
    }

    protected function columnize(array $columns): string
    {
        return implode(
            ', ',
            array_map(fn ($col) => $this->wrapColumn($col), $columns),
        );
    }

    protected function wrapColumn(ColumnDefinition $column): string
    {
        $sql = $this->wrap($column->name) . ' ' . $this->typeToSql($column);

        if (!$column->nullable) {
            $sql .= ' NOT NULL';
        }

        if ($column->unique) {
            $sql .= ' UNIQUE';
        }

        if ($column->primary && !$column->autoIncrement) {
            $sql .= ' PRIMARY KEY';
        }

        if ($column->default !== null) {
            $sql .= ' DEFAULT ' . $this->defaultValue($column->default);
        }

        return $sql;
    }

    protected function inlineForeigns(Blueprint $blueprint): string
    {
        $inline = [];
        foreach ($blueprint->getForeigns() as $foreign) {
            $inline[] = $this->compileForeignClause($foreign);
        }

        return implode(', ', $inline);
    }

    protected function defaultValue(mixed $value): string
    {
        return match (true) {
            $value === null => 'NULL',
            is_bool($value) => $value ? '1' : '0',
            is_int($value), is_float($value) => (string) $value,
            $value instanceof \DateTimeInterface => "'" .
                $value->format('Y-m-d H:i:s') .
                "'",
            default => "'" . str_replace("'", "''", (string) $value) . "'",
        };
    }

    public function wrapTable(string $table): string
    {
        return $this->wrap($table);
    }

    public function wrap(string $value): string
    {
        return '"' . str_replace('"', '""', $value) . '"';
    }

    abstract protected function typeToSql(ColumnDefinition $column): string;

    public function compileType(ColumnDefinition $column): string
    {
        return $this->typeToSql($column);
    }

    protected function compileModifyColumn(
        Blueprint $blueprint,
        ColumnDefinition $column,
    ): ?string {
        return null;
    }

    protected function compileRenameColumn(
        Blueprint $blueprint,
        string $from,
        string $to,
    ): ?string {
        return null;
    }

    /**
     * @param array<int, string> $columns
     */
    protected function compileIndex(
        Blueprint $blueprint,
        array $columns,
        string $name,
        bool $unique = false,
    ): ?string {
        $cols = implode(
            ', ',
            array_map(fn (string $col): string => $this->wrap($col), $columns),
        );
        $type = $unique ? 'UNIQUE ' : '';
        return "CREATE {$type}INDEX " .
            $this->wrap($name) .
            ' ON ' .
            $this->wrapTable($blueprint->table()) .
            " ({$cols})";
    }

    protected function compileForeign(
        Blueprint $blueprint,
        ForeignDefinition $foreign,
    ): ?string {
        $clause = $this->compileForeignClause($foreign);
        return 'ALTER TABLE ' .
            $this->wrapTable($blueprint->table()) .
            ' ADD ' .
            $clause;
    }

    protected function compileDropIndex(
        Blueprint $blueprint,
        string $name,
    ): ?string {
        return 'DROP INDEX ' . $this->wrap($name);
    }

    protected function compileDropForeign(
        Blueprint $blueprint,
        string $name,
    ): ?string {
        return null;
    }

    protected function compileForeignClause(ForeignDefinition $foreign): string
    {
        $name = $foreign->name ?? $foreign->column . '_foreign';
        $sql =
            'CONSTRAINT ' .
            $this->wrap($name) .
            ' FOREIGN KEY (' .
            $this->wrap($foreign->column) .
            ') REFERENCES ' .
            $this->wrap($foreign->on) .
            ' (' .
            $this->wrap($foreign->references) .
            ')';

        if ($foreign->onDelete !== null) {
            $sql .= ' ON DELETE ' . strtoupper($foreign->onDelete);
        }
        if ($foreign->onUpdate !== null) {
            $sql .= ' ON UPDATE ' . strtoupper($foreign->onUpdate);
        }

        return $sql;
    }
}
