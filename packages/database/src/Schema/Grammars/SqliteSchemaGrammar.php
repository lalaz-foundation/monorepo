<?php

declare(strict_types=1);

namespace Lalaz\Database\Schema\Grammars;

use Lalaz\Database\Schema\ColumnDefinition;

final class SqliteSchemaGrammar extends SchemaGrammar
{
    protected function typeToSql(ColumnDefinition $column): string
    {
        return match ($column->type) {
            'increments' => 'integer primary key autoincrement',
            'integer' => 'integer',
            'bigInteger' => 'integer',
            'string' => 'varchar(' . ($column->length ?? 255) . ')',
            'text' => 'text',
            'boolean' => 'integer',
            'timestamp' => 'text',
            'uuid' => 'varchar(' . ($column->length ?? 36) . ')',
            'json' => 'text',
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
        // SQLite has limited ALTER support; attempt using RENAME COLUMN pattern is not reliable for type changes.
        // Leave unimplemented to avoid false sense of support.
        return null;
    }

    protected function compileForeignClause(
        \Lalaz\Database\Schema\ForeignDefinition $foreign,
    ): string {
        $sql =
            'FOREIGN KEY (' .
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
        // SQLite cannot drop foreign constraints without table rebuild; skip.
        return null;
    }
}
