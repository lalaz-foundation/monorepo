<?php

declare(strict_types=1);

namespace Lalaz\Database;

use Lalaz\Config\Config;
use Lalaz\Container\Contracts\ContainerInterface;
use Lalaz\Container\ServiceProvider;
use Lalaz\Database\Console\CraftMigrationCommand;
use Lalaz\Database\Console\CraftSeederCommand;
use Lalaz\Database\Console\DatabaseStatusCommand;
use Lalaz\Database\Console\MigrateCommand;
use Lalaz\Database\Console\MigrateRefreshCommand;
use Lalaz\Database\Console\MigrateResetCommand;
use Lalaz\Database\Console\MigrateRollbackCommand;
use Lalaz\Database\Console\MigrateStatusCommand;
use Lalaz\Database\Console\SeedCommand;
use Lalaz\Database\Contracts\ConnectionInterface;
use Lalaz\Database\Contracts\ConnectionManagerInterface;
use Lalaz\Database\Contracts\ConnectorInterface;
use Lalaz\Database\Exceptions\DatabaseConfigurationException;
use Lalaz\Database\Migrations\MigrationRepository;
use Lalaz\Database\Migrations\Migrator;
use Lalaz\Database\Schema\SchemaBuilder;
use Lalaz\Database\Seeding\SeederRunner;
use Psr\Log\LoggerInterface;

/**
 * Service provider for the Database package.
 *
 * Registers connection manager, schema builder, migrator, and CLI commands.
 */
final class DatabaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        [$config, $customConnectors] = $this->prepareConfiguration();

        $this->singleton(ConnectionManager::class, function (
            ContainerInterface $container,
        ) use ($config, $customConnectors): ConnectionManagerInterface {
            $logger = $this->resolveLogger($container);

            return new ConnectionManager($config, $logger, $customConnectors);
        });

        $this->alias(
            ConnectionManager::class,
            ConnectionManagerInterface::class,
        );

        $this->scoped(Connection::class, function (
            ContainerInterface $container,
        ): Connection {
            /** @var ConnectionManagerInterface $manager */
            $manager = $container->resolve(ConnectionManagerInterface::class);
            return new Connection($manager);
        });

        $this->alias(Connection::class, ConnectionInterface::class);

        $this->scoped(SchemaBuilder::class, function (
            ContainerInterface $container,
        ): SchemaBuilder {
            /** @var ConnectionInterface $connection */
            $connection = $container->resolve(ConnectionInterface::class);
            /** @var ConnectionManagerInterface $manager */
            $manager = $container->resolve(ConnectionManagerInterface::class);

            return new SchemaBuilder($connection, $manager);
        });

        $this->scoped(MigrationRepository::class, function (
            ContainerInterface $container,
        ): MigrationRepository {
            /** @var ConnectionInterface $connection */
            $connection = $container->resolve(ConnectionInterface::class);
            /** @var SchemaBuilder $schema */
            $schema = $container->resolve(SchemaBuilder::class);

            return new MigrationRepository($connection, $schema);
        });

        $this->scoped(Migrator::class, function (
            ContainerInterface $container,
        ): Migrator {
            /** @var SchemaBuilder $schema */
            $schema = $container->resolve(SchemaBuilder::class);
            /** @var MigrationRepository $repository */
            $repository = $container->resolve(MigrationRepository::class);
            /** @var ConnectionInterface $connection */
            $connection = $container->resolve(ConnectionInterface::class);

            return new Migrator($schema, $repository, $connection);
        });

        $this->scoped(SeederRunner::class, function (
            ContainerInterface $container,
        ): SeederRunner {
            return new SeederRunner($container);
        });

        $this->commands(
            DatabaseStatusCommand::class,
            CraftMigrationCommand::class,
            CraftSeederCommand::class,
            MigrateCommand::class,
            MigrateRollbackCommand::class,
            MigrateRefreshCommand::class,
            MigrateResetCommand::class,
            MigrateStatusCommand::class,
            SeedCommand::class,
        );
    }

    private function resolveLogger(
        ContainerInterface $container,
    ): ?LoggerInterface {
        if ($container->bound(LoggerInterface::class)) {
            /** @var LoggerInterface $logger */
            $logger = $container->resolve(LoggerInterface::class);
            return $logger;
        }

        return null;
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, ConnectorInterface>}
     */
    private function prepareConfiguration(): array
    {
        $config = Config::getArray('database');

        if ($config === null || $config === []) {
            throw DatabaseConfigurationException::missingConfig();
        }

        $customConnectors = $this->resolveCustomConnectors($config);

        $driver = $this->normalizeDriver(
            $config['driver'] ?? null,
            array_keys($customConnectors),
        );

        $connections = $config['connections'] ?? null;
        if (!is_array($connections) || !isset($connections[$driver])) {
            throw DatabaseConfigurationException::missingConnection($driver);
        }

        $connection = $connections[$driver];
        if (!is_array($connection)) {
            throw DatabaseConfigurationException::missingConnection($driver);
        }

        if ($this->isBuiltInDriver($driver)) {
            $this->validateConnectionDefinition($driver, $connection);
        }

        $config['driver'] = $driver;
        unset($config['connectors']);

        return [$config, $customConnectors];
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function validateConnectionDefinition(
        string $driver,
        array $definition,
    ): void {
        $required = match ($driver) {
            'sqlite' => ['path'],
            'mysql' => ['host', 'database', 'username'],
            'postgres' => ['host', 'database', 'username'],
            default => [],
        };

        foreach ($required as $key) {
            if (
                !array_key_exists($key, $definition) ||
                $definition[$key] === null ||
                $definition[$key] === ''
            ) {
                throw DatabaseConfigurationException::missingConnectionKey(
                    $driver,
                    $key,
                );
            }
        }
    }

    /**
     * @param array<int, string> $customDrivers
     */
    private function normalizeDriver(
        mixed $driver,
        array $customDrivers,
    ): string {
        if (!is_string($driver) || $driver === '') {
            throw DatabaseConfigurationException::invalidDriver($driver);
        }

        $driver = strtolower($driver);
        $allowed = array_unique(
            array_merge($this->builtInDrivers(), $customDrivers),
        );

        if (!in_array($driver, $allowed, true)) {
            throw DatabaseConfigurationException::invalidDriver($driver);
        }

        return $driver;
    }

    /**
     * @return array<string, ConnectorInterface>
     */
    private function resolveCustomConnectors(array $config): array
    {
        $connectors = $config['connectors'] ?? [];
        if ($connectors === null) {
            return [];
        }

        if (!is_array($connectors)) {
            throw DatabaseConfigurationException::invalidConnector('custom');
        }

        $resolved = [];

        foreach ($connectors as $name => $definition) {
            if (!is_string($name) || $name === '') {
                throw DatabaseConfigurationException::invalidConnector(
                    (string) $name,
                );
            }

            $resolved[$name] = $this->instantiateConnector($name, $definition);
        }

        return $resolved;
    }

    private function instantiateConnector(
        string $driver,
        mixed $definition,
    ): ConnectorInterface {
        if ($definition instanceof ConnectorInterface) {
            return $definition;
        }

        if (is_string($definition) && $definition !== '') {
            if ($this->container->bound($definition)) {
                $instance = $this->container->resolve($definition);
            } else {
                if (!class_exists($definition)) {
                    throw DatabaseConfigurationException::invalidConnector(
                        $driver,
                    );
                }
                $instance = new $definition();
            }

            if ($instance instanceof ConnectorInterface) {
                return $instance;
            }
        }

        throw DatabaseConfigurationException::invalidConnector($driver);
    }

    private function isBuiltInDriver(string $driver): bool
    {
        return in_array($driver, $this->builtInDrivers(), true);
    }

    /**
     * @return array<int, string>
     */
    private function builtInDrivers(): array
    {
        return ['sqlite', 'mysql', 'postgres'];
    }
}
