<?php

declare(strict_types=1);

namespace Lalaz\Database\Schema\Grammars;

use Lalaz\Database\Schema\ColumnDefinition;

final class PostgresSchemaGrammar extends SchemaGrammar
{
    public function wrap(string $value): string
    {
        return '"' . str_replace('"', '""', $value) . '"';
    }

    protected function typeToSql(ColumnDefinition $column): string
    {
        return match ($column->type) {
            'increments' => 'serial primary key',
            'integer' => 'integer',
            'bigInteger' => 'bigint',
            'string' => 'varchar(' . ($column->length ?? 255) . ')',
            'text' => 'text',
            'boolean' => 'boolean',
            'timestamp' => 'timestamp',
            'uuid' => 'uuid',
            'json' => 'jsonb',
            default => 'text',
        };
    }

    protected function compileRenameColumn(
        \Lalaz\Database\Schema\Blueprint $blueprint,
        string $from,
        string $to,
    ): ?string {
        return 'ALTER TABLE ' .
            $this->wrapTable($blueprint->table()) .
            ' RENAME COLUMN ' .
            $this->wrap($from) .
            ' TO ' .
            $this->wrap($to);
    }

    protected function compileModifyColumn(
        \Lalaz\Database\Schema\Blueprint $blueprint,
        ColumnDefinition $column,
    ): ?string {
        return 'ALTER TABLE ' .
            $this->wrapTable($blueprint->table()) .
            ' ALTER COLUMN ' .
            $this->wrap($column->name) .
            ' TYPE ' .
            $this->typeToSql($column);
    }

    protected function compileDropIndex(
        \Lalaz\Database\Schema\Blueprint $blueprint,
        string $name,
    ): ?string {
        return 'DROP INDEX IF EXISTS ' . $this->wrap($name);
    }

    protected function compileDropForeign(
        \Lalaz\Database\Schema\Blueprint $blueprint,
        string $name,
    ): ?string {
        return 'ALTER TABLE ' .
            $this->wrapTable($blueprint->table()) .
            ' DROP CONSTRAINT ' .
            $this->wrap($name);
    }
}
