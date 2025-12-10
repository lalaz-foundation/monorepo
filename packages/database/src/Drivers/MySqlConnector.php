<?php

declare(strict_types=1);

namespace Lalaz\Database\Drivers;

use Lalaz\Database\Contracts\ConnectorInterface;
use PDO;

final class MySqlConnector implements ConnectorInterface
{
    public function connect(array $config): PDO
    {
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 3306;
        $database = $config['database'] ?? '';
        $charset = $config['charset'] ?? 'utf8mb4';
        $options = $this->defaultOptions($config['options'] ?? []);

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $host,
            $port,
            $database,
            $charset,
        );

        return new PDO(
            $dsn,
            $config['username'] ?? null,
            $config['password'] ?? null,
            $options,
        );
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
