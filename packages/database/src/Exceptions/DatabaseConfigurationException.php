<?php

declare(strict_types=1);

namespace Lalaz\Database\Exceptions;

use Lalaz\Database\Contracts\ConnectorInterface;

final class DatabaseConfigurationException extends \RuntimeException
{
    /**
     * @param string $message
     */
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function missingConfig(): self
    {
        return new self(
            'Database configuration not found. Publish config/database.php (run `php lalaz package:add lalaz/database`) before bootstrapping the provider.',
        );
    }

    public static function invalidDriver(?string $driver): self
    {
        $value = $driver ?? 'null';
        return new self(
            "Database driver [{$value}] is invalid. Supported drivers: sqlite, mysql, postgres.",
        );
    }

    public static function missingConnection(string $driver): self
    {
        return new self(
            "No configuration found for database driver [{$driver}] in config/database.php.",
        );
    }

    public static function missingConnectionKey(
        string $driver,
        string $key,
    ): self {
        return new self(
            "Connection definition for driver [{$driver}] is missing required key [{$key}].",
        );
    }

    public static function invalidConnector(string $driver): self
    {
        return new self(
            "Connector definition for driver [{$driver}] must be a valid class name or instance implementing " .
                ConnectorInterface::class .
                '.',
        );
    }
}
