<?php

declare(strict_types=1);

namespace Lalaz\Database\Console;

use Lalaz\Console\Contracts\CommandInterface;
use Lalaz\Console\Input;
use Lalaz\Console\Output;
use Lalaz\Container\Contracts\ContainerInterface;
use Lalaz\Database\Migrations\Migrator;

final class MigrateResetCommand implements CommandInterface
{
    public function __construct(private ContainerInterface $container)
    {
    }

    public function name(): string
    {
        return 'migrate:reset';
    }

    public function description(): string
    {
        return 'Rollback all database migrations.';
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
        $rolled = $migrator->reset([$path]);

        if ($rolled === []) {
            $output->writeln('Nothing to reset.');
            return 0;
        }

        foreach ($rolled as $migration) {
            $output->writeln("Rolled back: {$migration}");
        }

        return 0;
    }
}
