<?php

declare(strict_types=1);

namespace Lalaz\Web\View\Contracts;

interface ViewComposerInterface
{
    public function compose(array $data): array;
}
