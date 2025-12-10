<?php

declare(strict_types=1);

namespace Lalaz\Database\Console;

use Lalaz\Console\Contracts\CommandInterface;
use Lalaz\Console\Input;
use Lalaz\Console\Output;
use Lalaz\Container\Contracts\ContainerInterface;
use Lalaz\Database\Migrations\Migrator;

final class MigrateRefreshCommand implements CommandInterface
{
    public function __construct(private ContainerInterface $container)
    {
    }

    public function name(): string
    {
        return 'migrate:refresh';
    }

    public function description(): string
    {
        return 'Rollback all migrations and run them again.';
    }

    public function arguments(): array
    {
        return [];
    }

    public function options(): array
    {
        return [
            [
                'name' => 'path',
                'description' =>
                    'Path to migrations directory (default: database/migrations)',
                'required' => false,
                'short' => 'p',
            ],
        ];
    }

    public function handle(Input $input, Output $output): int
    {
        /** @var Migrator $migrator */
        $migrator = $this->container->resolve(Migrator::class);

        $path = $input->option('path') ?? $input->option('p') ?? 'database/migrations';
        $migrator->reset([$path]);
        $ran = $migrator->run([$path]);

        foreach ($ran as $migration) {
            $output->writeln("Migrated: {$migration}");
        }

        if ($ran === []) {
            $output->writeln('No migrations to run.');
        }

        return 0;
    }
}
