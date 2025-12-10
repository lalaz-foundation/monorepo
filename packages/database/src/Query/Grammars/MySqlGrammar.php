<?php

declare(strict_types=1);

namespace Lalaz\Database\Query\Grammars;

use Lalaz\Database\Query\Grammar;

final class MySqlGrammar extends Grammar
{
    protected function wrapValue(string $value): string
    {
        return '`' . str_replace('`', '``', $value) . '`';
    }

    public function compileShareLock(): string
    {
        return 'lock in share mode';
    }

    public function compileUpsert(
        \Lalaz\Database\Query\QueryBuilder $query,
        array $values,
        array $uniqueBy,
        array $updateColumns,
    ): string {
        $insert = $this->compileInsert($query, $values);

        if ($updateColumns === []) {
            // No-op update to satisfy MySQL syntax
            $first = $this->wrap($uniqueBy[0]);
            return $insert . " on duplicate key update {$first} = {$first}";
        }

        $updates = [];
        foreach ($updateColumns as $column) {
            $wrapped = $this->wrap($column);
            $updates[] = "{$wrapped} = values({$wrapped})";
        }

        return $insert . ' on duplicate key update ' . implode(', ', $updates);
    }
}
