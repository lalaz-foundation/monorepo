<?php

declare(strict_types=1);

namespace Lalaz\Database\Query;

final class Expression
{
    public function __construct(private string $value)
    {
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
