<?php

declare(strict_types=1);

namespace Lalaz\Console\Commands;

use Lalaz\Console\Contracts\CommandInterface;
use Lalaz\Console\Generators\Generator;
use Lalaz\Console\Input;
use Lalaz\Console\Output;

/**
 * Command that generates a service provider class.
 *
 * Creates a new ServiceProvider extending the base class
 * with the register method ready for custom bindings.
 * Automatically appends "ServiceProvider" suffix if not provided.
 *
 * Usage: php lalaz craft:provider App
 *        php lalaz craft:provider AppServiceProvider
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
final class CraftProviderCommand implements CommandInterface
{
    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'craft:provider';
    }

    /**
     * {@inheritdoc}
     */
    public function description(): string
    {
        return 'Generate a service provider class';
    }

    /**
     * {@inheritdoc}
     */
    public function arguments(): array
    {
        return [
            ['name' => 'name', 'description' => 'Provider class name', 'optional' => false],
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
            $output->error('Usage: php lalaz craft:provider App');
            return 1;
        }

        // Ensure name ends with "ServiceProvider"
        $name = Generator::ensureSuffix($name, 'ServiceProvider');

        [$class, $path] = Generator::normalizeClass($name, 'App\\Providers');
        $file = getcwd() . '/app/' . $path . '.php';

        $namespace = substr($class, 0, strrpos($class, '\\'));
        $short = substr($class, strrpos($class, '\\') + 1);

        $stub = <<<PHP
<?php declare(strict_types=1);

namespace {$namespace};

use Lalaz\Container\ServiceProvider;

final class {$short} extends ServiceProvider
{
    public function register(): void
    {
        // Register bindings
    }
}
PHP;
        Generator::writeFile($file, $stub);
        $output->writeln("Service provider created: {$file}");
        return 0;
    }
}
