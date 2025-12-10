<?php

declare(strict_types=1);

namespace Lalaz\Database\Schema\Grammars;

use Lalaz\Database\Schema\ColumnDefinition;

final class MySqlSchemaGrammar extends SchemaGrammar
{
    public function wrap(string $value): string
    {
        return '`' . str_replace('`', '``', $value) . '`';
    }

    protected function typeToSql(ColumnDefinition $column): string
    {
        return match ($column->type) {
            'increments' => 'int unsigned auto_increment primary key',
            'integer' => $column->unsigned ? 'int unsigned' : 'int',
            'bigInteger' => $column->unsigned ? 'bigint unsigned' : 'bigint',
            'string' => 'varchar(' . ($column->length ?? 255) . ')',
            'text' => 'text',
            'boolean' => 'tinyint(1)',
            'timestamp' => 'timestamp',
            'uuid' => 'char(' . ($column->length ?? 36) . ')',
            'json' => 'json',
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
            ' MODIFY ' .
            $this->wrapColumn($column);
    }

    protected function compileDropIndex(
        \Lalaz\Database\Schema\Blueprint $blueprint,
        string $name,
    ): ?string {
        return 'DROP INDEX ' .
            $this->wrap($name) .
            ' ON ' .
            $this->wrapTable($blueprint->table());
    }

    protected function compileDropForeign(
        \Lalaz\Database\Schema\Blueprint $blueprint,
        string $name,
    ): ?string {
        return 'ALTER TABLE ' .
            $this->wrapTable($blueprint->table()) .
            ' DROP FOREIGN KEY ' .
            $this->wrap($name);
    }
}
