<?php

declare(strict_types=1);

namespace Lalaz\Database\Tests\Unit;

use Lalaz\Database\Tests\Common\DatabaseUnitTestCase;
use Lalaz\Database\Exceptions\DatabaseConfigurationException;

class DatabaseConfigurationExceptionTest extends DatabaseUnitTestCase
{
    public function test_missing_config_exception(): void
    {
        $exception = DatabaseConfigurationException::missingConfig();

        $this->assertInstanceOf(DatabaseConfigurationException::class, $exception);
        $this->assertStringContainsString('Database configuration not found', $exception->getMessage());
    }

    public function test_missing_connection_exception(): void
    {
        $exception = DatabaseConfigurationException::missingConnection('mysql');

        $this->assertInstanceOf(DatabaseConfigurationException::class, $exception);
        $this->assertStringContainsString('mysql', $exception->getMessage());
    }

    public function test_missing_connection_key_exception(): void
    {
        $exception = DatabaseConfigurationException::missingConnectionKey('mysql', 'host');

        $this->assertInstanceOf(DatabaseConfigurationException::class, $exception);
        $this->assertStringContainsString('mysql', $exception->getMessage());
        $this->assertStringContainsString('host', $exception->getMessage());
    }

    public function test_invalid_driver_exception(): void
    {
        $exception = DatabaseConfigurationException::invalidDriver('unknown');

        $this->assertInstanceOf(DatabaseConfigurationException::class, $exception);
        $this->assertStringContainsString('unknown', $exception->getMessage());
    }

    public function test_invalid_connector_exception(): void
    {
        $exception = DatabaseConfigurationException::invalidConnector('custom');

        $this->assertInstanceOf(DatabaseConfigurationException::class, $exception);
        $this->assertStringContainsString('custom', $exception->getMessage());
    }
}
