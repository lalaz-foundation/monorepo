<?php

declare(strict_types=1);

namespace Lalaz\Database\Console;

use Lalaz\Console\Contracts\CommandInterface;
use Lalaz\Console\Input;
use Lalaz\Console\Output;
use Lalaz\Container\Contracts\ContainerInterface;
use Lalaz\Database\Seeding\SeederRunner;

final class SeedCommand implements CommandInterface
{
    public function __construct(private ContainerInterface $container)
    {
    }

    public function name(): string
    {
        return 'db:seed';
    }

    public function description(): string
    {
        return 'Run database seeders.';
    }

    public function arguments(): array
    {
        return [];
    }

    public function options(): array
    {
        return [
            [
                'name' => 'class',
                'description' => 'Specific seeder class to run.',
                'required' => false,
            ],
            [
                'name' => 'path',
                'short' => 'p',
                'description' =>
                    'Path to seeders directory (default: database/seeders).',
                'required' => false,
            ],
        ];
    }

    public function handle(Input $input, Output $output): int
    {
        /** @var SeederRunner $runner */
        $runner = $this->container->resolve(SeederRunner::class);

        $class = $input->option('class');
        $path =
            $input->option('path') ?? $input->option('p') ?? 'database/seeders';

        if (is_string($class) && $class !== '') {
            $runner->run($class);
            $output->writeln("Seeded: {$class}");
            return 0;
        }

        $runner->runAll([$path]);
        $output->writeln("Seeded directory: {$path}");
        return 0;
    }
}
