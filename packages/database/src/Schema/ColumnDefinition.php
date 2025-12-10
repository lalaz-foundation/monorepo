<?php

declare(strict_types=1);

namespace Lalaz\Database\Schema;

final class ColumnDefinition
{
    public function __construct(
        public string $name,
        public string $type,
        public ?int $length = null,
        public bool $nullable = false,
        public bool $autoIncrement = false,
        public bool $primary = false,
        public bool $unique = false,
        public bool $unsigned = false,
        public mixed $default = null,
        public bool $change = false,
    ) {
    }

    public function nullable(bool $value = true): self
    {
        $this->nullable = $value;
        return $this;
    }

    public function default(mixed $value): self
    {
        $this->default = $value;
        return $this;
    }

    public function unique(bool $value = true): self
    {
        $this->unique = $value;
        return $this;
    }

    public function primary(bool $value = true): self
    {
        $this->primary = $value;
        return $this;
    }

    public function autoIncrement(bool $value = true): self
    {
        $this->autoIncrement = $value;
        return $this;
    }

    public function unsigned(bool $value = true): self
    {
        $this->unsigned = $value;
        return $this;
    }

    public function change(bool $value = true): self
    {
        $this->change = $value;
        return $this;
    }
}
