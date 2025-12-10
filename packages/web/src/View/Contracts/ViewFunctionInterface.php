<?php

declare(strict_types=1);

namespace Lalaz\Web\View\Contracts;

interface ViewFunctionInterface
{
    public function getName(): string;

    public function getCallable(): callable;

    public function getOptions(): array;
}
