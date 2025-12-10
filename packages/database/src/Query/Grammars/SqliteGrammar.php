<?php

declare(strict_types=1);

namespace Lalaz\Database\Query\Grammars;

use Lalaz\Database\Query\Grammar;

final class SqliteGrammar extends Grammar
{
    public function compileUpsert(
        \Lalaz\Database\Query\QueryBuilder $query,
        array $values,
        array $uniqueBy,
        array $updateColumns,
    ): string {
        $insert = $this->compileInsert($query, $values);
        $conflict = $this->columnize($uniqueBy);

        if ($updateColumns === []) {
            return $insert . " on conflict ({$conflict}) do nothing";
        }

        $updates = [];
        foreach ($updateColumns as $column) {
            $wrapped = $this->wrap($column);
            $updates[] = "{$wrapped} = excluded.{$column}";
        }

        return $insert .
            " on conflict ({$conflict}) do update set " .
            implode(', ', $updates);
    }
}
