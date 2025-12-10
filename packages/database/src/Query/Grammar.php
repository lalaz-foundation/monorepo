<?php

declare(strict_types=1);

namespace Lalaz\Database\Query;

class Grammar
{
    public function compileSelect(QueryBuilder $query): string
    {
        $select = $this->compileComponents($query);

        if ($query->hasUnion()) {
            return "({$select})" . $this->compileUnions($query);
        }

        return $select . $this->compileUnions($query);
    }

    public function compileInsert(QueryBuilder $query, array $values): string
    {
        $table = $this->wrapTable($query->fromTable());
        $columns = $this->columnize(array_keys($values[0]));
        $parameters = implode(', ', array_fill(0, count($values[0]), '?'));
        $rows = implode(
            ', ',
            array_fill(0, count($values), '(' . $parameters . ')'),
        );

        return "insert into {$table} ({$columns}) values {$rows}";
    }

    /**
     * Compile an upsert statement.
     *
     * @param array<int, array<string, mixed>> $values
     * @param array<int, string> $uniqueBy
     * @param array<int, string> $updateColumns
     */
    public function compileUpsert(
        QueryBuilder $query,
        array $values,
        array $uniqueBy,
        array $updateColumns,
    ): string {
        throw new \RuntimeException('Upsert is not supported by this grammar.');
    }

    public function compileUpdate(QueryBuilder $query, array $values): string
    {
        $table = $this->wrapTable($query->fromTable());
        $columns = [];

        foreach ($values as $column => $value) {
            $columns[] =
                $this->wrap($column) . ' = ' . $this->parameter($value);
        }

        $sql = "update {$table} set " . implode(', ', $columns);
        $sql .= $this->compileWheres($query);

        if ($query->limitValue() !== null) {
            $sql .= ' limit ' . $query->limitValue();
        }

        return $sql;
    }

    public function compileDelete(QueryBuilder $query): string
    {
        $table = $this->wrapTable($query->fromTable());
        $sql = "delete from {$table}";
        $sql .= $this->compileWheres($query);

        if ($query->limitValue() !== null) {
            $sql .= ' limit ' . $query->limitValue();
        }

        return $sql;
    }

    private function compileComponents(QueryBuilder $query): string
    {
        $components = [
            'select' => $this->compileColumns($query),
            'from' => $query->hasFromTable()
                ? 'from ' . $this->wrapTable($query->fromTable())
                : null,
            'joins' => $this->compileJoins($query),
            'wheres' => $this->compileWheres($query),
            'groups' => $this->compileGroups($query),
            'havings' => $this->compileHavings($query),
            'orders' => $this->compileOrders($query),
            'limit' =>
                $query->limitValue() !== null
                    ? 'limit ' . $query->limitValue()
                    : null,
            'offset' =>
                $query->offsetValue() !== null
                    ? 'offset ' . $query->offsetValue()
                    : null,
            'lock' => $query->lockValue(),
        ];

        return trim(
            preg_replace(
                "/\s+/",
                ' ',
                implode(' ', array_filter($components)) ?? '',
            ),
        );
    }

    private function compileColumns(QueryBuilder $query): string
    {
        $select = $query->columns();
        $distinct = $query->isDistinct() ? 'distinct ' : '';
        return 'select ' .
            $distinct .
            ($select === [] ? '*' : $this->columnize($select));
    }

    private function compileJoins(QueryBuilder $query): ?string
    {
        if ($query->joins() === []) {
            return null;
        }

        $joins = array_map(function (JoinClause $join) {
            $table = $join->getTable();
            $table =
                $table instanceof Expression
                    ? (string) $table
                    : $this->wrapTable($table);
            $conditions = [];

            foreach ($join->conditions() as $condition) {
                if (($condition['type'] ?? null) === 'nested') {
                    /** @var JoinClause $clause */
                    $clause = $condition['clause'];
                    $conditions[] =
                        strtoupper($condition['boolean']) .
                        ' (' .
                        substr($this->compileNestedJoin($clause), 3) .
                        ')';
                    continue;
                }

                $first = $this->wrap($condition['first']);
                $second = $this->wrap($condition['second']);
                $conditions[] =
                    strtoupper($condition['boolean']) .
                    " {$first} {$condition['operator']} {$second}";
            }

            $compiled = implode(' ', $conditions);
            $compiled =
                preg_replace("/^(AND|OR)\s/", '', $compiled ?? '') ?? '';

            $type = strtoupper($join->getType());
            if ($compiled === '') {
                return sprintf('%s join %s', $type, $table);
            }

            return sprintf('%s join %s on %s', $type, $table, $compiled);
        }, $query->joins());

        return implode(' ', $joins);
    }

    private function compileNestedJoin(JoinClause $join): string
    {
        $conditions = [];

        foreach ($join->conditions() as $condition) {
            if (($condition['type'] ?? null) === 'nested') {
                $conditions[] =
                    strtoupper($condition['boolean']) .
                    ' (' .
                    substr($this->compileNestedJoin($condition['clause']), 3) .
                    ')';
                continue;
            }

            $first = $this->wrap($condition['first']);
            $second = $this->wrap($condition['second']);
            $conditions[] =
                strtoupper($condition['boolean']) .
                " {$first} {$condition['operator']} {$second}";
        }

        $compiled = implode(' ', $conditions);
        return preg_replace("/^(AND|OR)\s/", '', $compiled ?? '') ?? '';
    }

    private function compileWheres(QueryBuilder $query): string
    {
        if ($query->wheres() === []) {
            return '';
        }

        $sql = [];

        foreach ($query->wheres() as $where) {
            $method = 'where' . $where['type'];
            $boolean = strtoupper($where['boolean']);
            $sql[] = $boolean . ' ' . $this->{$method}($query, $where);
        }

        $compiled = implode(' ', $sql);
        $compiled = preg_replace("/^(AND|OR)\s/", '', $compiled ?? '') ?? '';

        return ' where ' . $compiled;
    }

    private function whereBasic(QueryBuilder $query, array $where): string
    {
        $column = $this->wrap($where['column']);
        return "{$column} {$where['operator']} ?";
    }

    private function whereNested(QueryBuilder $query, array $where): string
    {
        return '(' . substr($this->compileWheres($where['query']), 7) . ')';
    }

    private function whereNull(QueryBuilder $query, array $where): string
    {
        $column = $this->wrap($where['column']);
        return "{$column} is null";
    }

    private function whereNotNull(QueryBuilder $query, array $where): string
    {
        $column = $this->wrap($where['column']);
        return "{$column} is not null";
    }

    private function whereIn(QueryBuilder $query, array $where): string
    {
        $column = $this->wrap($where['column']);
        $placeholders = implode(
            ', ',
            array_fill(0, count($where['values']), '?'),
        );
        return "{$column} in ({$placeholders})";
    }

    private function whereNotIn(QueryBuilder $query, array $where): string
    {
        $column = $this->wrap($where['column']);
        $placeholders = implode(
            ', ',
            array_fill(0, count($where['values']), '?'),
        );
        return "{$column} not in ({$placeholders})";
    }

    private function whereInSub(QueryBuilder $query, array $where): string
    {
        $column = $this->wrap($where['column']);
        return "{$column} in (" . $where['query']->toSql() . ')';
    }

    private function whereNotInSub(QueryBuilder $query, array $where): string
    {
        $column = $this->wrap($where['column']);
        return "{$column} not in (" . $where['query']->toSql() . ')';
    }

    private function whereBetween(QueryBuilder $query, array $where): string
    {
        $column = $this->wrap($where['column']);
        return "{$column} between ? and ?";
    }

    private function whereNotBetween(QueryBuilder $query, array $where): string
    {
        $column = $this->wrap($where['column']);
        return "{$column} not between ? and ?";
    }

    private function whereBetweenColumns(
        QueryBuilder $query,
        array $where,
    ): string {
        $column = $this->wrap($where['column']);
        $first = $this->wrap($where['values'][0]);
        $second = $this->wrap($where['values'][1]);
        return "{$column} between {$first} and {$second}";
    }

    private function whereNotBetweenColumns(
        QueryBuilder $query,
        array $where,
    ): string {
        $column = $this->wrap($where['column']);
        $first = $this->wrap($where['values'][0]);
        $second = $this->wrap($where['values'][1]);
        return "{$column} not between {$first} and {$second}";
    }

    private function whereRaw(QueryBuilder $query, array $where): string
    {
        return (string) $where['sql'];
    }

    private function whereExpression(QueryBuilder $query, array $where): string
    {
        $column = $this->wrap($where['column']);
        return "{$column} {$where['operator']} " . (string) $where['value'];
    }

    private function whereColumn(QueryBuilder $query, array $where): string
    {
        $first = $this->wrap($where['first']);
        $second = $this->wrap($where['second']);
        return "{$first} {$where['operator']} {$second}";
    }

    private function whereExists(QueryBuilder $query, array $where): string
    {
        return 'exists (' . $where['query']->toSql() . ')';
    }

    private function whereNotExists(QueryBuilder $query, array $where): string
    {
        return 'not exists (' . $where['query']->toSql() . ')';
    }

    private function compileGroups(QueryBuilder $query): ?string
    {
        if ($query->groups() === []) {
            return null;
        }

        return 'group by ' . $this->columnize($query->groups());
    }

    private function compileHavings(QueryBuilder $query): ?string
    {
        if ($query->havings() === []) {
            return null;
        }

        $segments = [];

        foreach ($query->havings() as $having) {
            $method = 'having' . $having['type'];
            $segments[] =
                strtoupper($having['boolean']) .
                ' ' .
                $this->{$method}($having);
        }

        $compiled = implode(' ', $segments);
        $compiled = preg_replace("/^(AND|OR)\s/", '', $compiled ?? '') ?? '';

        return 'having ' . $compiled;
    }

    private function havingBasic(array $having): string
    {
        return $this->wrap($having['column']) .
            ' ' .
            $having['operator'] .
            ' ?';
    }

    private function havingBetween(array $having): string
    {
        return $this->wrap($having['column']) . ' between ? and ?';
    }

    private function havingNotBetween(array $having): string
    {
        return $this->wrap($having['column']) . ' not between ? and ?';
    }

    private function havingNull(array $having): string
    {
        return $this->wrap($having['column']) . ' is null';
    }

    private function havingNotNull(array $having): string
    {
        return $this->wrap($having['column']) . ' is not null';
    }

    private function havingRaw(array $having): string
    {
        return (string) $having['sql'];
    }

    private function compileOrders(QueryBuilder $query): ?string
    {
        if ($query->orders() === []) {
            return null;
        }

        $segments = array_map(function ($order) {
            if (($order['type'] ?? null) === 'raw') {
                return (string) $order['sql'];
            }

            return $this->wrap($order['column']) . ' ' . $order['direction'];
        }, $query->orders());

        return 'order by ' . implode(', ', $segments);
    }

    private function compileUnions(QueryBuilder $query): string
    {
        if ($query->unions() === []) {
            return '';
        }

        $sql = '';

        foreach ($query->unions() as $union) {
            $sql .=
                ' ' .
                trim(
                    'union ' . ($union['all'] ? 'all ' : '') . $union['query'],
                );
        }

        return $sql;
    }

    protected function columnize(array $columns): string
    {
        return implode(', ', array_map([$this, 'wrap'], $columns));
    }

    public function wrapTable(string|Expression $table): string
    {
        return $this->wrap($table);
    }

    public function wrap(string|Expression $value): string
    {
        if ($value instanceof Expression) {
            return (string) $value;
        }

        if ($value === '*') {
            return $value;
        }

        if (preg_match("/\s+as\s+/i", $value)) {
            [$before, $after] = preg_split("/\s+as\s+/i", $value, 2);
            return $this->wrap($before) . ' as ' . $this->wrap($after);
        }

        if (str_contains($value, '.')) {
            $segments = explode('.', $value);
            return implode(
                '.',
                array_map(
                    fn ($segment) => $segment === '*'
                        ? '*'
                        : $this->wrap($segment),
                    $segments,
                ),
            );
        }

        return $this->wrapValue($value);
    }

    private function parameter(mixed $value): string
    {
        return $value instanceof Expression ? (string) $value : '?';
    }

    protected function wrapValue(string $value): string
    {
        return '"' . str_replace('"', '""', $value) . '"';
    }

    public function compileShareLock(): string
    {
        return 'lock in share mode';
    }
}
