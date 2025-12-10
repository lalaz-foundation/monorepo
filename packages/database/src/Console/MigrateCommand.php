<?php

declare(strict_types=1);

namespace Lalaz\Database\Console;

use Lalaz\Console\Contracts\CommandInterface;
use Lalaz\Console\Input;
use Lalaz\Console\Output;
use Lalaz\Container\Contracts\ContainerInterface;
use Lalaz\Database\Migrations\Migrator;

final class MigrateCommand implements CommandInterface
{
    public function __construct(private ContainerInterface $container)
    {
    }

    public function name(): string
    {
        return 'migrate';
    }

    public function description(): string
    {
        return 'Run the database migrations.';
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

        $path = $input->option('path') ?? 'database/migrations';
        $ran = $migrator->run([$path]);

        if ($ran === []) {
            $output->writeln('No outstanding migrations.');
            return 0;
        }

        foreach ($ran as $migration) {
            $output->writeln("Migrated: {$migration}");
        }

        return 0;
    }
}
