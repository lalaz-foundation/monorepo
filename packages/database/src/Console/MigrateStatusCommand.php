<?php

declare(strict_types=1);

namespace Lalaz\Database\Console;

use Lalaz\Console\Contracts\CommandInterface;
use Lalaz\Console\Input;
use Lalaz\Console\Output;
use Lalaz\Container\Contracts\ContainerInterface;
use Lalaz\Database\Migrations\Migrator;

final class MigrateStatusCommand implements CommandInterface
{
    public function __construct(private ContainerInterface $container)
    {
    }

    public function name(): string
    {
        return 'migrate:status';
    }

    public function description(): string
    {
        return 'Show the status of each migration.';
    }

    public function arguments(): array
    {
        return [];
    }

    public function options(): array
    {
        return [];
    }

    public function handle(Input $input, Output $output): int
    {
        /** @var Migrator $migrator */
        $migrator = $this->container->resolve(Migrator::class);

        $rows = $migrator->status();

        if ($rows === []) {
            $output->writeln('No migrations have been run.');
            return 0;
        }

        foreach ($rows as $row) {
            $output->writeln(
                "{$row['migration']} (batch: {$row['batch']})",
            );
        }

        return 0;
    }
}
