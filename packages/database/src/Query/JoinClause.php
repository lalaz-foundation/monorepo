<?php

declare(strict_types=1);

namespace Lalaz\Database\Query;

final class JoinClause
{
    /** @var array<int, array{boolean:string,type:string,first:mixed,operator?:string,second?:mixed}> */
    private array $conditions = [];

    public function __construct(
        private string $type,
        private string|Expression $table,
    ) {
    }

    public function on(
        mixed $first,
        ?string $operator = null,
        mixed $second = null,
        string $boolean = 'and',
    ): self {
        if ($first instanceof \Closure) {
            $nested = new self($this->type, $this->table);
            $first($nested);
            $this->conditions[] = [
                'type' => 'nested',
                'boolean' => $boolean,
                'clause' => $nested,
            ];
            return $this;
        }

        $this->conditions[] = [
            'boolean' => $boolean,
            'type' => 'basic',
            'first' => $first,
            'operator' => $operator ?? '=',
            'second' => $second,
        ];

        return $this;
    }

    public function orOn(
        mixed $first,
        ?string $operator = null,
        mixed $second = null,
    ): self {
        return $this->on($first, $operator, $second, 'or');
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getTable(): string|Expression
    {
        return $this->table;
    }

    /**
     * @return array<int, mixed>
     */
    public function conditions(): array
    {
        return $this->conditions;
    }
}
