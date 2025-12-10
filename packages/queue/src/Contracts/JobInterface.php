<?php

declare(strict_types=1);

namespace Lalaz\Queue\Contracts;

interface JobInterface
{
    public function handle(array $payload): void;
}
