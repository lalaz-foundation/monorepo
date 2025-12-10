<?php

declare(strict_types=1);

namespace Lalaz\Database\Drivers;

use Lalaz\Database\Contracts\ConnectorInterface;
use PDO;

final class SqliteConnector implements ConnectorInterface
{
    public function connect(array $config): PDO
    {
        $path = $config['path'] ?? ':memory:';
        $dsn = str_starts_with($path, 'sqlite:') ? $path : 'sqlite:' . $path;

        $options = $this->defaultOptions($config['options'] ?? []);

        return new PDO($dsn, null, null, $options);
    }

    /**
     * @param array<int|string, mixed> $options
     * @return array<int, mixed>
     */
    private function defaultOptions(array $options): array
    {
        return $options + [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
    }
}
