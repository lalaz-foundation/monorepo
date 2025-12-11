<?php

declare(strict_types=1);

namespace Lalaz\Console\Commands;

use Lalaz\Console\Contracts\CommandInterface;
use Lalaz\Console\Generators\Generator;
use Lalaz\Console\Input;
use Lalaz\Console\Output;

/**
 * Command that generates a console command class.
 *
 * Creates a new CLI command class with the basic structure
 * required to implement the CommandInterface.
 * Automatically appends "Command" suffix if not provided.
 *
 * Usage: php lalaz craft:command Example
 *        php lalaz craft:command ExampleCommand
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
final class CraftCommandCommand implements CommandInterface
{
    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'craft:command';
    }

    /**
     * {@inheritdoc}
     */
    public function description(): string
    {
        return 'Generate a console command class';
    }

    /**
     * {@inheritdoc}
     */
    public function arguments(): array
    {
        return [
            ['name' => 'name', 'description' => 'Command class name', 'optional' => false],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function options(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Input $input, Output $output): int
    {
        $name = $input->argument(0);
        if (!$name) {
            $output->error('Usage: php lalaz craft:command Example');
            return 1;
        }

        // Ensure name ends with "Command"
        $name = Generator::ensureSuffix($name, 'Command');

        [$class, $path] = Generator::normalizeClass($name, 'App\\Console\\Commands');
        $file = getcwd() . '/app/' . $path . '.php';
        $namespace = substr($class, 0, strrpos($class, '\\'));
        $short = substr($class, strrpos($class, '\\') + 1);

        $stub = <<<PHP
<?php declare(strict_types=1);

namespace {$namespace};

use Lalaz\Console\Contracts\CommandInterface;
use Lalaz\Console\Input;
use Lalaz\Console\Output;

final class {$short} implements CommandInterface
{
    public function name(): string
    {
        return 'app:example';
    }

    public function description(): string
    {
        return 'Describe your command';
    }

    public function arguments(): array
    {
        return [];
    }

    public function options(): array
    {
        return [];
    }

    public function handle(Input \$input, Output \$output): int
    {
        \$output->writeln('Hello from {$short}!');
        return 0;
    }
}
PHP;
        Generator::writeFile($file, $stub);
        $output->writeln("Command created: {$file}");
        return 0;
    }
}
