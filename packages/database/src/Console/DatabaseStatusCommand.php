<?php

declare(strict_types=1);

namespace Lalaz\Database\Console;

use Lalaz\Console\Contracts\CommandInterface;
use Lalaz\Console\Input;
use Lalaz\Console\Output;
use Lalaz\Container\Contracts\ContainerInterface;
use Lalaz\Database\Contracts\ConnectionManagerInterface;
use PDO;

final class DatabaseStatusCommand implements CommandInterface
{
    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    public function name(): string
    {
        return 'database:status';
    }

    public function description(): string
    {
        return 'Checks the configured database connection and reports status.';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function arguments(): array
    {
        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function options(): array
    {
        return [];
    }

    public function handle(Input $input, Output $output): int
    {
        /** @var ConnectionManagerInterface $manager */
        $manager = $this->container->resolve(ConnectionManagerInterface::class);

        $driver = $manager->driver();
        $output->writeln("Default driver: {$driver}");

        try {
            $pdo = $manager->acquire();
        } catch (\Throwable $exception) {
            $output->error(
                'Unable to acquire a database connection: ' .
                    $exception->getMessage(),
            );
            return 1;
        }

        try {
            $statement = $pdo->query($this->probeQuery($driver));
            if ($statement !== false) {
                $statement->fetch();
            }

            $version = $this->serverVersion($pdo);
            $output->writeln('✓ Connection successful.');
            if ($version !== null) {
                $output->writeln("Server version: {$version}");
            }

            return 0;
        } catch (\Throwable $exception) {
            $output->error(
                'Connection test failed: ' . $exception->getMessage(),
            );
            return 1;
        } finally {
            $manager->release($pdo);
        }
    }

    private function probeQuery(string $driver): string
    {
        return match ($driver) {
            'postgres' => 'SELECT 1',
            default => 'SELECT 1',
        };
    }

    private function serverVersion(PDO $pdo): ?string
    {
        try {
            return (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
        } catch (\Throwable) {
            return null;
        }
    }
}
