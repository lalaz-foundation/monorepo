<?php

declare(strict_types=1);

namespace Lalaz\Database\Schema;

final class Blueprint
{
    /**
     * @var array<int, ColumnDefinition>
     */
    private array $columns = [];

    /**
     * @var array<int, array{from:string,to:string}>
     */
    private array $renames = [];

    /**
     * @var array<int, array{columns:array<int,string>,name:string,unique:bool}>
     */
    private array $indexes = [];

    /**
     * @var array<int, ForeignDefinition>
     */
    private array $foreigns = [];

    /**
     * @var array<int, string>
     */
    private array $dropIndexes = [];

    /**
     * @var array<int, string>
     */
    private array $dropForeigns = [];

    private bool $creating = false;

    public function __construct(private string $table)
    {
    }

    public function creating(): void
    {
        $this->creating = true;
    }

    public function isCreating(): bool
    {
        return $this->creating;
    }

    public function table(): string
    {
        return $this->table;
    }

    public function increments(string $name): ColumnDefinition
    {
        $column = new ColumnDefinition(
            $name,
            'increments',
            nullable: false,
            autoIncrement: true,
            primary: true,
            unsigned: true,
        );
        $this->columns[] = $column;
        return $column;
    }

    public function integer(string $name): ColumnDefinition
    {
        $column = new ColumnDefinition($name, 'integer');
        $this->columns[] = $column;
        return $column;
    }

    public function bigInteger(string $name): ColumnDefinition
    {
        $column = new ColumnDefinition($name, 'bigInteger');
        $this->columns[] = $column;
        return $column;
    }

    public function string(string $name, int $length = 255): ColumnDefinition
    {
        $column = new ColumnDefinition($name, 'string', $length);
        $this->columns[] = $column;
        return $column;
    }

    public function text(string $name): ColumnDefinition
    {
        $column = new ColumnDefinition($name, 'text');
        $this->columns[] = $column;
        return $column;
    }

    public function boolean(string $name): ColumnDefinition
    {
        $column = new ColumnDefinition($name, 'boolean');
        $this->columns[] = $column;
        return $column;
    }

    public function timestamp(string $name): ColumnDefinition
    {
        $column = new ColumnDefinition($name, 'timestamp');
        $this->columns[] = $column;
        return $column;
    }

    public function timestamps(): void
    {
        $this->timestamp('created_at')->nullable();
        $this->timestamp('updated_at')->nullable();
    }

    public function softDeletes(string $column = 'deleted_at'): void
    {
        $this->timestamp($column)->nullable();
    }

    public function uuid(string $name): ColumnDefinition
    {
        $column = new ColumnDefinition($name, 'uuid', 36);
        $this->columns[] = $column;
        return $column;
    }

    public function json(string $name): ColumnDefinition
    {
        $column = new ColumnDefinition($name, 'json');
        $this->columns[] = $column;
        return $column;
    }

    public function index(string|array $columns, ?string $name = null): void
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $name ??= $this->indexName($columns);

        $this->indexes[] = [
            'columns' => $columns,
            'name' => $name,
            'unique' => false,
        ];
    }

    public function unique(string|array $columns, ?string $name = null): void
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $name ??= $this->indexName($columns, 'unique');

        $this->indexes[] = [
            'columns' => $columns,
            'name' => $name,
            'unique' => true,
        ];
    }

    public function renameColumn(string $from, string $to): void
    {
        $this->renames[] = ['from' => $from, 'to' => $to];
    }

    public function foreign(
        string $column,
        string $references,
        string $on,
        ?string $name = null,
    ): ForeignDefinition {
        $definition = new ForeignDefinition($column, $references, $on, $name);
        $this->foreigns[] = $definition;
        return $definition;
    }

    public function dropIndex(string|array $index): void
    {
        $this->dropIndexes[] = is_array($index)
            ? $this->indexName($index)
            : $index;
    }

    public function dropUnique(string|array $index): void
    {
        $this->dropIndexes[] = is_array($index)
            ? $this->indexName((array) $index, 'unique')
            : $index;
    }

    public function dropForeign(string $name): void
    {
        $this->dropForeigns[] = $name;
    }

    /**
     * @return array<int, ColumnDefinition>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * @return array<int, ColumnDefinition>
     */
    public function getAddedColumns(): array
    {
        return array_filter(
            $this->columns,
            static fn (ColumnDefinition $column): bool => $column->change ===
                false,
        );
    }

    /**
     * @return array<int, ColumnDefinition>
     */
    public function getModifiedColumns(): array
    {
        return array_filter(
            $this->columns,
            static fn (ColumnDefinition $column): bool => $column->change ===
                true,
        );
    }

    /**
     * @return array<int, array{columns:array<int,string>,name:string,unique:bool}>
     */
    public function getIndexes(): array
    {
        return $this->indexes;
    }

    /**
     * @return array<int, ForeignDefinition>
     */
    public function getForeigns(): array
    {
        return $this->foreigns;
    }

    /**
     * @return array<int, array{from:string,to:string}>
     */
    public function getRenames(): array
    {
        return $this->renames;
    }

    /**
     * @return array<int, string>
     */
    public function getDropIndexes(): array
    {
        return $this->dropIndexes;
    }

    /**
     * @return array<int, string>
     */
    public function getDropForeigns(): array
    {
        return $this->dropForeigns;
    }

    /**
     * @return array<int, string>
     */
    public function toSql(
        Grammars\SchemaGrammar $grammar,
        bool $ifNotExists = false,
    ): array {
        $statements = [];

        if ($this->creating) {
            $statements[] = $grammar->compileCreate($this, $ifNotExists);
            $statements = array_merge(
                $statements,
                $grammar->compileIndexes($this),
            );
            return $statements;
        }

        foreach ($this->columns as $column) {
            if ($column->unique) {
                $this->unique($column->name);
                $column->unique = false;
            }
        }

        $statements = array_merge(
            $grammar->compileAdd($this),
            $grammar->compileModify($this),
            $grammar->compileRenames($this),
            $grammar->compileForeigns($this),
            $grammar->compileIndexes($this),
            $grammar->compileDropIndexes($this),
            $grammar->compileDropForeigns($this),
        );

        return $statements;
    }

    private function indexName(array $columns, string $suffix = 'index'): string
    {
        return $this->table . '_' . implode('_', $columns) . '_' . $suffix;
    }
}
