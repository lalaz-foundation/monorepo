<?php

declare(strict_types=1);

namespace Lalaz\Database\Query;

use Closure;
use InvalidArgumentException;
use Lalaz\Database\Connection;

final class QueryBuilder
{
    private bool $distinct = false;

    /** @var array<int, string|Expression> */
    private array $columns = ['*'];

    private string|Expression|null $from = null;

    /** @var array<int, JoinClause> */
    private array $joins = [];

    /** @var array<int, array<string, mixed>> */
    private array $wheres = [];

    /** @var array<int, string|Expression> */
    private array $groups = [];

    /** @var array<int, array<string, mixed>> */
    private array $havings = [];

    /** @var array<int, array{type?:string,column?:string|Expression,direction?:string,sql?:Expression}> */
    private array $orders = [];

    private ?int $limit = null;

    private ?int $offset = null;

    /** @var array<int, array{query:string,type:string,all:bool,bindings:array<int,mixed>}> */
    private array $unions = [];

    private ?string $lock = null;

    /** @var array<string, array<int, mixed>> */
    private array $bindings = [
        'select' => [],
        'from' => [],
        'join' => [],
        'where' => [],
        'having' => [],
        'order' => [],
        'union' => [],
    ];

    public function __construct(
        private Connection $connection,
        private Grammar $grammar = new Grammar(),
    ) {
    }

    public function newQuery(): self
    {
        return new self($this->connection, $this->grammar);
    }

    public function connection(): Connection
    {
        return $this->connection;
    }

    public function grammar(): Grammar
    {
        return $this->grammar;
    }

    public function select(string|Expression ...$columns): self
    {
        if ($columns === []) {
            return $this;
        }

        $this->columns = $columns;
        return $this;
    }

    public function addSelect(string|Expression ...$columns): self
    {
        if ($columns === []) {
            return $this;
        }

        $this->columns = array_merge($this->columns, $columns);
        return $this;
    }

    public function selectSub(
        Closure|self|string $query,
        string $as,
        array $bindings = [],
    ): self {
        [$expression, $subBindings] = $this->compileSubSelect(
            $query,
            $as,
            $bindings,
        );
        $this->addSelect($expression);
        $this->addBinding($subBindings, 'select');
        return $this;
    }

    public function selectRaw(string $expression, array $bindings = []): self
    {
        if ($this->columns === ['*']) {
            $this->columns = [];
        }

        $this->addSelect(new Expression($expression));
        $this->addBinding($bindings, 'select');
        return $this;
    }

    public function distinct(bool $value = true): self
    {
        $this->distinct = $value;
        return $this;
    }

    public function from(string $table): self
    {
        $this->from = $table;
        return $this;
    }

    public function fromSub(
        Closure|self|string $query,
        string $as,
        array $bindings = [],
    ): self {
        [$expression, $subBindings] = $this->compileSubSelect(
            $query,
            $as,
            $bindings,
        );
        $this->from = $expression;
        $this->addBinding($subBindings, 'from');
        return $this;
    }

    public function table(string $table): self
    {
        return $this->from($table);
    }

    public function join(
        string|Expression $table,
        Closure|string $first,
        ?string $operator = null,
        ?string $second = null,
        string $type = 'inner',
        string $boolean = 'and',
    ): self {
        $join = new JoinClause($type, $table);

        if ($first instanceof Closure) {
            $first($join);
        } else {
            $join->on($first, $operator, $second, $boolean);
        }

        $this->joins[] = $join;
        return $this;
    }

    public function joinSub(
        Closure|self $query,
        string $as,
        Closure|string $first,
        ?string $operator = null,
        ?string $second = null,
        string $type = 'inner',
    ): self {
        [$expression, $bindings] = $this->compileSubSelect($query, $as);
        $this->addBinding($bindings, 'join');
        return $this->join($expression, $first, $operator, $second, $type);
    }

    public function leftJoin(
        string|Expression $table,
        Closure|string $first,
        ?string $operator = null,
        ?string $second = null,
    ): self {
        return $this->join($table, $first, $operator, $second, 'left');
    }

    public function leftJoinSub(
        Closure|self $query,
        string $as,
        Closure|string $first,
        ?string $operator = null,
        ?string $second = null,
    ): self {
        return $this->joinSub($query, $as, $first, $operator, $second, 'left');
    }

    public function rightJoin(
        string|Expression $table,
        Closure|string $first,
        ?string $operator = null,
        ?string $second = null,
    ): self {
        return $this->join($table, $first, $operator, $second, 'right');
    }

    public function rightJoinSub(
        Closure|self $query,
        string $as,
        Closure|string $first,
        ?string $operator = null,
        ?string $second = null,
    ): self {
        return $this->joinSub($query, $as, $first, $operator, $second, 'right');
    }

    public function crossJoin(string|Expression $table): self
    {
        return $this->join($table, static function (): void {}, type: 'cross');
    }

    public function where(
        Closure|string|array $column,
        mixed $operator = null,
        mixed $value = null,
        string $boolean = 'and',
    ): self {
        if (is_array($column)) {
            foreach ($column as $key => $val) {
                $this->where($key, '=', $val, $boolean);
            }
            return $this;
        }

        if ($column instanceof Closure) {
            $query = $this->forNestedWhere();
            $column($query);
            return $this->addNestedWhereQuery($query, $boolean);
        }

        if (func_num_args() === 2) {
            [$value, $operator] = [$operator, '='];
        }

        if ($value instanceof Expression) {
            $this->wheres[] = [
                'type' => 'Expression',
                'column' => $column,
                'operator' => $operator,
                'value' => $value,
                'boolean' => $boolean,
            ];

            return $this;
        }

        $this->wheres[] = [
            'type' => 'Basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => $boolean,
        ];

        $this->addBinding($value, 'where');

        return $this;
    }

    public function orWhere(
        Closure|string|array $column,
        mixed $operator = null,
        mixed $value = null,
    ): self {
        return $this->where($column, $operator, $value, 'or');
    }

    public function whereColumn(
        string|array $first,
        ?string $operator = null,
        ?string $second = null,
        string $boolean = 'and',
    ): self {
        if (is_array($first)) {
            foreach ($first as $comparison) {
                $this->whereColumn(...$comparison);
            }
            return $this;
        }

        if ($second === null) {
            $second = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'Column',
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
            'boolean' => $boolean,
        ];

        return $this;
    }

    public function orWhereColumn(
        string|array $first,
        ?string $operator = null,
        ?string $second = null,
    ): self {
        return $this->whereColumn($first, $operator, $second, 'or');
    }

    public function whereRaw(
        string $sql,
        array $bindings = [],
        string $boolean = 'and',
    ): self {
        $this->wheres[] = [
            'type' => 'Raw',
            'sql' => new Expression($sql),
            'boolean' => $boolean,
        ];

        $this->addBinding($bindings, 'where');
        return $this;
    }

    public function whereBetween(
        string $column,
        array $values,
        string $boolean = 'and',
        bool $not = false,
    ): self {
        $values = $this->assertTwoElementArray($values, 'whereBetween');
        $type = $not ? 'NotBetween' : 'Between';
        $this->wheres[] = [
            'type' => $type,
            'column' => $column,
            'values' => $values,
            'boolean' => $boolean,
        ];
        $this->addBinding($values, 'where');
        return $this;
    }

    public function orWhereBetween(string $column, array $values): self
    {
        return $this->whereBetween($column, $values, 'or');
    }

    public function whereNotBetween(
        string $column,
        array $values,
        string $boolean = 'and',
    ): self {
        return $this->whereBetween($column, $values, $boolean, true);
    }

    public function orWhereNotBetween(string $column, array $values): self
    {
        return $this->whereNotBetween($column, $values, 'or');
    }

    public function whereBetweenColumns(
        string $column,
        array $values,
        string $boolean = 'and',
        bool $not = false,
    ): self {
        $values = $this->assertTwoElementArray($values, 'whereBetweenColumns');
        $type = $not ? 'NotBetweenColumns' : 'BetweenColumns';
        $this->wheres[] = [
            'type' => $type,
            'column' => $column,
            'values' => $values,
            'boolean' => $boolean,
        ];

        return $this;
    }

    public function whereIn(
        string $column,
        array|Closure|self $values,
        string $boolean = 'and',
        bool $not = false,
    ): self {
        if ($values instanceof Closure || $values instanceof self) {
            $subQuery =
                $values instanceof Closure
                    ? $this->createSub($values)
                    : $values;
            $type = $not ? 'NotInSub' : 'InSub';
            $this->wheres[] = [
                'type' => $type,
                'column' => $column,
                'query' => $subQuery,
                'boolean' => $boolean,
            ];
            $this->addBinding($subQuery->bindings(), 'where');
            return $this;
        }

        if ($values === []) {
            return $not
                ? $this->whereRaw('1 = 1', [], $boolean)
                : $this->whereRaw('0 = 1', [], $boolean);
        }

        $type = $not ? 'NotIn' : 'In';
        $this->wheres[] = [
            'type' => $type,
            'column' => $column,
            'values' => array_values($values),
            'boolean' => $boolean,
        ];
        $this->addBinding($values, 'where');
        return $this;
    }

    public function orWhereIn(string $column, array|Closure|self $values): self
    {
        return $this->whereIn($column, $values, 'or');
    }

    public function whereNotIn(
        string $column,
        array|Closure|self $values,
        string $boolean = 'and',
    ): self {
        return $this->whereIn($column, $values, $boolean, true);
    }

    public function orWhereNotIn(
        string $column,
        array|Closure|self $values,
    ): self {
        return $this->whereNotIn($column, $values, 'or');
    }

    public function whereNull(
        string|array $column,
        string $boolean = 'and',
        bool $not = false,
    ): self {
        $columns = (array) $column;
        foreach ($columns as $col) {
            $this->wheres[] = [
                'type' => $not ? 'NotNull' : 'Null',
                'column' => $col,
                'boolean' => $boolean,
            ];
        }
        return $this;
    }

    public function orWhereNull(string|array $column): self
    {
        return $this->whereNull($column, 'or');
    }

    public function whereNotNull(
        string|array $column,
        string $boolean = 'and',
    ): self {
        return $this->whereNull($column, $boolean, true);
    }

    public function orWhereNotNull(string|array $column): self
    {
        return $this->whereNotNull($column, 'or');
    }

    public function whereExists(
        Closure|self $query,
        string $boolean = 'and',
        bool $not = false,
    ): self {
        $subQuery =
            $query instanceof Closure ? $this->createSub($query) : $query;
        $type = $not ? 'NotExists' : 'Exists';
        $this->wheres[] = [
            'type' => $type,
            'query' => $subQuery,
            'boolean' => $boolean,
        ];
        $this->addBinding($subQuery->bindings(), 'where');
        return $this;
    }

    public function orWhereExists(Closure|self $query): self
    {
        return $this->whereExists($query, 'or');
    }

    public function whereNotExists(
        Closure|self $query,
        string $boolean = 'and',
    ): self {
        return $this->whereExists($query, $boolean, true);
    }

    public function groupBy(string|Expression ...$columns): self
    {
        $this->groups = array_merge($this->groups, $columns);
        return $this;
    }

    public function groupByRaw(string $sql): self
    {
        $this->groups[] = new Expression($sql);
        return $this;
    }

    public function having(
        string|Expression $column,
        string $operator,
        mixed $value,
        string $boolean = 'and',
    ): self {
        $this->havings[] = [
            'type' => 'Basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => $boolean,
        ];
        $this->addBinding($value, 'having');
        return $this;
    }

    public function orHaving(
        string|Expression $column,
        string $operator,
        mixed $value,
    ): self {
        return $this->having($column, $operator, $value, 'or');
    }

    public function havingBetween(
        string $column,
        array $values,
        string $boolean = 'and',
        bool $not = false,
    ): self {
        $values = $this->assertTwoElementArray($values, 'havingBetween');
        $this->havings[] = [
            'type' => $not ? 'NotBetween' : 'Between',
            'column' => $column,
            'values' => $values,
            'boolean' => $boolean,
        ];
        $this->addBinding($values, 'having');
        return $this;
    }

    public function havingNull(
        string|array $columns,
        string $boolean = 'and',
        bool $not = false,
    ): self {
        foreach ((array) $columns as $column) {
            $this->havings[] = [
                'type' => $not ? 'NotNull' : 'Null',
                'column' => $column,
                'boolean' => $boolean,
            ];
        }
        return $this;
    }

    public function havingRaw(
        string $sql,
        array $bindings = [],
        string $boolean = 'and',
    ): self {
        $this->havings[] = [
            'type' => 'Raw',
            'sql' => new Expression($sql),
            'boolean' => $boolean,
        ];
        $this->addBinding($bindings, 'having');
        return $this;
    }

    public function orderBy(
        string|Expression $column,
        string $direction = 'asc',
    ): self {
        $this->orders[] = [
            'column' => $column,
            'direction' => strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC',
        ];
        return $this;
    }

    public function orderByDesc(string|Expression $column): self
    {
        return $this->orderBy($column, 'desc');
    }

    public function orderByRaw(string $sql, array $bindings = []): self
    {
        $this->orders[] = [
            'type' => 'raw',
            'sql' => new Expression($sql),
        ];
        $this->addBinding($bindings, 'order');
        return $this;
    }

    public function reorder(
        ?string $column = null,
        string $direction = 'asc',
    ): self {
        $this->orders = [];
        $this->bindings['order'] = [];

        if ($column !== null) {
            $this->orderBy($column, $direction);
        }

        return $this;
    }

    public function latest(string $column = 'created_at'): self
    {
        return $this->orderByDesc($column);
    }

    public function oldest(string $column = 'created_at'): self
    {
        return $this->orderBy($column, 'asc');
    }

    public function limit(int $value): self
    {
        $this->limit = max(0, $value);
        return $this;
    }

    public function offset(int $value): self
    {
        $this->offset = max(0, $value);
        return $this;
    }

    public function forPage(int $page, int $perPage = 15): self
    {
        return $this->offset(($page - 1) * $perPage)->limit($perPage);
    }

    public function lock(bool|string $value = true): self
    {
        $this->lock =
            $value === true ? 'for update' : ($value === false ? null : $value);
        return $this;
    }

    public function lockForUpdate(): self
    {
        return $this->lock(true);
    }

    public function sharedLock(): self
    {
        return $this->lock($this->grammar->compileShareLock());
    }

    public function union(Closure|self $query, bool $all = false): self
    {
        $builder =
            $query instanceof Closure ? $this->createSub($query) : $query;
        $this->unions[] = [
            'query' => $builder->toSql(),
            'bindings' => $builder->bindings(),
            'type' => 'union',
            'all' => $all,
        ];
        $this->addBinding($builder->bindings(), 'union');
        return $this;
    }

    public function unionAll(Closure|self $query): self
    {
        return $this->union($query, true);
    }

    public function get(array|string $columns = ['*']): array
    {
        if (!is_array($columns)) {
            $columns = func_get_args();
        }

        if ($columns !== [] && $columns !== ['*']) {
            $this->columns = $columns;
        }

        return $this->connection->select($this->toSql(), $this->bindings());
    }

    public function first(array|string $columns = ['*']): ?array
    {
        $this->limit ??= 1;
        $results = $this->get($columns);
        return $results[0] ?? null;
    }

    public function value(string $column): mixed
    {
        $row = $this->first([$column]);
        return $row[$column] ?? null;
    }

    public function pluck(string $column, ?string $key = null): array
    {
        $results = $this->get();

        if ($key === null) {
            return array_map(
                static fn ($row) => $row[$column] ?? null,
                $results,
            );
        }

        $values = [];
        foreach ($results as $row) {
            if (!array_key_exists($key, $row)) {
                continue;
            }
            $values[$row[$key]] = $row[$column] ?? null;
        }

        return $values;
    }

    public function exists(): bool
    {
        $clone = clone $this;
        $clone->orders = [];
        $clone->limit = 1;
        $clone->offset = null;
        $clone->columns = [new Expression('1 as exists_result')];
        $clone->bindings['select'] = [];
        $clone->unions = [];
        $clone->bindings['union'] = [];
        return $clone->connection->select(
            $clone->toSql(),
            $clone->bindings(),
        ) !== [];
    }

    public function doesntExist(): bool
    {
        return !$this->exists();
    }

    public function count(string $column = '*'): int
    {
        return (int) $this->aggregate('count', $column);
    }

    public function sum(string $column): float
    {
        return (float) $this->aggregate('sum', $column);
    }

    public function avg(string $column): float
    {
        return (float) $this->aggregate('avg', $column);
    }

    public function min(string $column): mixed
    {
        return $this->aggregate('min', $column);
    }

    public function max(string $column): mixed
    {
        return $this->aggregate('max', $column);
    }

    public function aggregate(string $function, string $column = '*'): mixed
    {
        $clone = clone $this;
        $clone->columns = [
            new Expression(
                strtoupper($function) . '(' . $column . ') as aggregate',
            ),
        ];
        $clone->bindings['select'] = [];
        $clone->orders = [];
        $clone->limit = null;
        $clone->offset = null;
        $clone->unions = [];
        $clone->bindings['union'] = [];
        $result = $clone->first();
        return $result['aggregate'] ?? null;
    }

    public function insert(array $values): bool
    {
        if ($values === []) {
            return true;
        }

        $values = array_is_list($values) ? $values : [$values];
        $sql = $this->grammar->compileInsert($this, $values);
        $bindings = [];
        foreach ($values as $record) {
            foreach ($record as $value) {
                $bindings[] = $value;
            }
        }
        return $this->connection->insert($sql, $bindings);
    }

    public function insertGetId(array $values, ?string $id = null): string
    {
        $this->insert($values);
        return $this->connection->getPdo()->lastInsertId($id);
    }

    public function insertMany(array $values): bool
    {
        return $this->insert($values);
    }

    /**
     * Perform an "upsert" operation.
     *
     * @param array<int, array<string, mixed>> $values
     * @param array<int, string>|string $uniqueBy
     * @param array<int, string>|null $updateColumns
     */
    public function upsert(
        array $values,
        array|string $uniqueBy,
        ?array $updateColumns = null,
    ): bool {
        if ($values === []) {
            return true;
        }

        $values = array_is_list($values) ? $values : [$values];
        $uniqueBy = is_array($uniqueBy) ? $uniqueBy : [$uniqueBy];

        $first = $values[0];
        $updateColumns ??= array_keys($first);

        $sql = $this->grammar->compileUpsert(
            $this,
            $values,
            $uniqueBy,
            $updateColumns,
        );

        $bindings = [];
        foreach ($values as $record) {
            foreach ($record as $value) {
                $bindings[] = $value;
            }
        }

        return $this->connection->insert($sql, $bindings);
    }

    /**
     * Update rows matching simple equality conditions.
     *
     * @param array<string, mixed> $conditions
     * @param array<string, mixed> $values
     */
    public function updateWhere(array $conditions, array $values): int
    {
        foreach ($conditions as $column => $value) {
            $this->where($column, $value);
        }

        return $this->update($values);
    }

    /**
     * Delete rows matching simple equality conditions.
     *
     * @param array<string, mixed> $conditions
     */
    public function deleteWhere(array $conditions): int
    {
        foreach ($conditions as $column => $value) {
            $this->where($column, $value);
        }

        return $this->delete();
    }

    public function update(array $values): int
    {
        $sql = $this->grammar->compileUpdate($this, $values);
        $bindings = [];
        foreach ($values as $value) {
            if ($value instanceof Expression) {
                continue;
            }
            $bindings[] = $value;
        }

        $bindings = array_merge($bindings, $this->bindings['where']);
        return $this->connection->update($sql, $bindings);
    }

    public function increment(
        string $column,
        int|float $amount = 1,
        array $extra = [],
    ): int {
        $wrapped = $this->grammar->wrap($column);
        $values = array_merge(
            [
                $column => new Expression("{$wrapped} + {$amount}"),
            ],
            $extra,
        );

        return $this->update($values);
    }

    public function decrement(
        string $column,
        int|float $amount = 1,
        array $extra = [],
    ): int {
        return $this->increment($column, -$amount, $extra);
    }

    public function delete(?int $id = null): int
    {
        if ($id !== null) {
            $this->where('id', '=', $id);
        }

        $sql = $this->grammar->compileDelete($this);
        return $this->connection->delete($sql, $this->bindings['where']);
    }

    public function toSql(): string
    {
        return $this->grammar->compileSelect($this);
    }

    /** @return array<int, mixed> */
    public function bindings(): array
    {
        return array_merge(
            $this->bindings['select'],
            $this->bindings['from'],
            $this->bindings['join'],
            $this->bindings['where'],
            $this->bindings['having'],
            $this->bindings['order'],
            $this->bindings['union'],
        );
    }

    /** @return array<int, string|Expression> */
    public function columns(): array
    {
        return $this->columns;
    }

    public function isDistinct(): bool
    {
        return $this->distinct;
    }

    public function fromTable(): string|Expression
    {
        return $this->from ??
            throw new InvalidArgumentException(
                'No table specified for query builder.',
            );
    }

    public function hasFromTable(): bool
    {
        return $this->from !== null;
    }

    /** @return array<int, JoinClause> */
    public function joins(): array
    {
        return $this->joins;
    }

    /** @return array<int, array<string, mixed>> */
    public function wheres(): array
    {
        return $this->wheres;
    }

    /** @return array<int, string|Expression> */
    public function groups(): array
    {
        return $this->groups;
    }

    /** @return array<int, array<string, mixed>> */
    public function havings(): array
    {
        return $this->havings;
    }

    public function orders(): array
    {
        return $this->orders;
    }

    public function limitValue(): ?int
    {
        return $this->limit;
    }

    public function offsetValue(): ?int
    {
        return $this->offset;
    }

    public function unions(): array
    {
        return $this->unions;
    }

    public function lockValue(): ?string
    {
        return $this->lock;
    }

    public function hasUnion(): bool
    {
        return $this->unions !== [];
    }

    private function createSub(Closure $callback): self
    {
        $query = $this->newQuery();
        $callback($query);
        return $query;
    }

    private function addNestedWhereQuery(
        self $query,
        string $boolean = 'and',
    ): self {
        if ($query->wheres() === []) {
            return $this;
        }

        $this->wheres[] = [
            'type' => 'Nested',
            'query' => $query,
            'boolean' => $boolean,
        ];
        $this->addBinding($query->bindings(), 'where');
        return $this;
    }

    private function forNestedWhere(): self
    {
        return $this->newQuery();
    }

    private function addBinding(mixed $value, string $type): void
    {
        if (!array_key_exists($type, $this->bindings)) {
            $this->bindings[$type] = [];
        }

        if ($value instanceof Expression) {
            return;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                $this->addBinding($item, $type);
            }
            return;
        }

        $this->bindings[$type][] = $value;
    }

    /**
     * @return array{0: Expression, 1: array<int, mixed>}
     */
    private function compileSubSelect(
        Closure|self|string $query,
        string $as,
        array $bindings = [],
    ): array {
        if ($query instanceof Closure) {
            $builder = $this->createSub($query);
            return [
                new Expression(
                    '(' .
                        $builder->toSql() .
                        ') as ' .
                        $this->grammar->wrap($as),
                ),
                $builder->bindings(),
            ];
        }

        if ($query instanceof self) {
            return [
                new Expression(
                    '(' . $query->toSql() . ') as ' . $this->grammar->wrap($as),
                ),
                $query->bindings(),
            ];
        }

        return [
            new Expression('(' . $query . ') as ' . $this->grammar->wrap($as)),
            $bindings,
        ];
    }

    private function assertTwoElementArray(array $values, string $method): array
    {
        if (count($values) !== 2) {
            throw new InvalidArgumentException(
                $method . ' requires exactly two values.',
            );
        }

        return array_values($values);
    }
}
