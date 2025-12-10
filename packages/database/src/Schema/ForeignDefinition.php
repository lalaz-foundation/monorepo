<?php

declare(strict_types=1);

namespace Lalaz\Database\Schema;

final class ForeignDefinition
{
    public function __construct(
        public string $column,
        public string $references,
        public string $on,
        public ?string $name = null,
        public ?string $onDelete = null,
        public ?string $onUpdate = null,
    ) {
    }

    public function onDelete(string $action): self
    {
        $this->onDelete = $action;
        return $this;
    }

    public function onUpdate(string $action): self
    {
        $this->onUpdate = $action;
        return $this;
    }
}
