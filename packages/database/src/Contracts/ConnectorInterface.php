<?php

declare(strict_types=1);

namespace Lalaz\Database\Contracts;

interface ConnectorInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function connect(array $config): \PDO;
}
