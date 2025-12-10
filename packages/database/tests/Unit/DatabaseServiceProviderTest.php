<?php declare(strict_types=1);

namespace Lalaz\Database\Tests\Unit;

use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Lalaz\Database\Connection;
use Lalaz\Database\Contracts\ConnectionInterface;
use Lalaz\Database\Contracts\ConnectionManagerInterface;
use Lalaz\Database\Contracts\ConnectorInterface;
use Lalaz\Database\Exceptions\DatabaseConfigurationException;
use Lalaz\Database\DatabaseServiceProvider;

#[Group('integration')]
class DatabaseServiceProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Skip: Requires framework core (Config, Container, Console\Input, Console\Output)
        if (!class_exists(\Lalaz\Config\Config::class)) {
            $this->markTestSkipped('Requires lalaz/framework core package');
        }

        \Lalaz\Config\Config::setConfig("database", [
            "driver" => "sqlite",
            "pool" => [
                "min" => 0,
                "max" => 1,
            ],
            "connections" => [
                "sqlite" => [
                    "path" => ":memory:",
                    "options" => [],
                ],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        if (class_exists(\Lalaz\Config\Config::class)) {
            \Lalaz\Config\Config::setConfig("database", []);
        }
        parent::tearDown();
    }

    public function test_database_service_provider_singletons_manager_and_scopes_connections(): void
    {
        $container = new \Lalaz\Container\Container();
        $provider = new DatabaseServiceProvider($container);
        $provider->register();

        $managerA = $container->resolve(ConnectionManagerInterface::class);
        $managerB = $container->resolve(ConnectionManagerInterface::class);
        $this->assertSame($managerA, $managerB);

        $container->beginScope();
        $connectionA = $container->resolve(ConnectionInterface::class);
        $connectionB = $container->resolve(ConnectionInterface::class);
        $this->assertInstanceOf(Connection::class, $connectionA);
        $this->assertSame($connectionA, $connectionB);

        $statement = $connectionA->query("SELECT 1 AS one");
        $this->assertSame(["one" => 1], $statement->fetch());

        unset($connectionA, $connectionB);
        $container->endScope();

        $container->beginScope();
        $connectionC = $container->resolve(ConnectionInterface::class);
        $connectionD = $container->resolve(ConnectionInterface::class);
        $this->assertInstanceOf(Connection::class, $connectionC);
        $this->assertSame($connectionC, $connectionD);
        $container->endScope();
    }

    public function test_database_service_provider_throws_when_config_missing(): void
    {
        \Lalaz\Config\Config::setConfig("database", []);
        $container = new \Lalaz\Container\Container();

        $provider = new DatabaseServiceProvider($container);

        $this->expectException(DatabaseConfigurationException::class);
        $this->expectExceptionMessage("Database configuration not found");
        $provider->register();
    }

    public function test_custom_connectors_enable_additional_drivers(): void
    {
        \Lalaz\Config\Config::setConfig("database", [
            "driver" => "tenant",
            "connections" => [
                "tenant" => ["path" => ":memory:"],
            ],
            "connectors" => [
                "tenant" => FakeConnector::class,
            ],
        ]);

        $container = new \Lalaz\Container\Container();
        $provider = new DatabaseServiceProvider($container);
        $provider->register();

        $container->beginScope();
        $connection = $container->resolve(ConnectionInterface::class);
        $row = $connection->query("SELECT 7 AS lucky")->fetch();
        $this->assertSame(7, $row["lucky"] ?? null);
        $container->endScope();
    }

    public function test_database_status_command_reports_manager_failures(): void
    {
        $manager = new class implements ConnectionManagerInterface {
            public function acquire(): PDO
            {
                throw new \RuntimeException("boom");
            }

            public function acquireRead(): PDO
            {
                return $this->acquire();
            }

            public function release(PDO $connection): void {}

            public function releaseRead(PDO $connection): void {}

            public function driver(): string
            {
                return "sqlite";
            }

            public function readDriver(): string
            {
                return "sqlite";
            }

            public function config(string $key, mixed $default = null): mixed
            {
                return null;
            }

            public function acquireWithTimeout(?int $timeoutMs = null): PDO
            {
                return $this->acquire();
            }

            public function poolStatus(): array
            {
                return [
                    "total" => 0,
                    "pooled" => 0,
                    "max" => 0,
                    "min" => 0,
                ];
            }

            public function listenQuery(callable $listener): void {}

            public function dispatchQueryEvent(array $event): void {}
        };

        $container = new \Lalaz\Container\Container();
        $container->singleton(ConnectionManagerInterface::class, fn() => $manager);

        $command = new \Lalaz\Database\Console\DatabaseStatusCommand($container);
        $output = new class extends \Lalaz\Console\Output {
            public array $lines = [];

            public function writeln(string $message = ""): void
            {
                $this->lines[] = $message;
            }

            public function error(string $message): void
            {
                $this->lines[] = $message;
            }
        };

        $result = $command->handle(
            new \Lalaz\Console\Input(["lalaz", "database:status"]),
            $output,
        );

        $this->assertSame(1, $result);
        $this->assertContains(
            "Unable to acquire a database connection: boom",
            $output->lines,
        );
    }

    public function test_database_status_command_reports_sqlite_success(): void
    {
        $container = new \Lalaz\Container\Container();
        $provider = new DatabaseServiceProvider($container);
        $provider->register();

        $command = new \Lalaz\Database\Console\DatabaseStatusCommand($container);

        $output = new class extends \Lalaz\Console\Output {
            public array $lines = [];

            public function writeln(string $message = ""): void
            {
                $this->lines[] = $message;
            }

            public function error(string $message): void
            {
                $this->lines[] = $message;
            }
        };

        $result = $command->handle(
            new \Lalaz\Console\Input(["lalaz", "database:status"]),
            $output,
        );

        $this->assertSame(0, $result);
        $this->assertContains("✓ Connection successful.", $output->lines);
    }
}

final class FakeConnector implements ConnectorInterface
{
    public function connect(array $config): PDO
    {
        return new PDO("sqlite::memory:");
    }
}
