<?php

declare(strict_types=1);

namespace Lalaz\Web\View;

use Lalaz\Web\View\Contracts\ViewFunctionInterface;

class ViewFunction implements ViewFunctionInterface
{
    public function __construct(
        private string $name,
        private $callable,
        private array $options = [],
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCallable(): callable
    {
        return $this->callable;
    }

    public function getOptions(): array
    {
        return $this->options;
    }
}
