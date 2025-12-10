<?php

declare(strict_types=1);

namespace Lalaz\Console\Commands;

use Lalaz\Console\Contracts\CommandInterface;
use Lalaz\Console\Generators\Generator;
use Lalaz\Console\Input;
use Lalaz\Console\Output;

/**
 * Command that generates an HTTP middleware class.
 *
 * Creates a new middleware implementing MiddlewareInterface
 * with the standard handle method signature.
 * Automatically appends "Middleware" suffix if not provided.
 *
 * Usage: php lalaz craft:middleware Auth
 *        php lalaz craft:middleware AuthMiddleware
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
final class CraftMiddlewareCommand implements CommandInterface
{
    /**
     * Get the command name.
     *
     * @return string The command name.
     */
    public function name(): string
    {
        return 'craft:middleware';
    }

    /**
     * Get the command description.
     *
     * @return string The command description.
     */
    public function description(): string
    {
        return 'Generate an HTTP middleware class';
    }

    /**
     * Get the command arguments definition.
     *
     * @return array<int, array<string, mixed>> The arguments configuration.
     */
    public function arguments(): array
    {
        return [
            [
                'name' => 'name',
                'description' => 'Middleware class name',
                'optional' => false,
            ],
        ];
    }

    /**
     * Get the command options definition.
     *
     * @return array<int, array<string, mixed>> The options configuration.
     */
    public function options(): array
    {
        return [];
    }

    /**
     * Execute the command.
     *
     * @param Input $input The command input.
     * @param Output $output The command output.
     * @return int Exit code (0 for success, non-zero for failure).
     */
    public function handle(Input $input, Output $output): int
    {
        $name = $input->argument(0);
        if (!$name) {
            $output->error(
                'Usage: php lalaz craft:middleware Auth',
            );
            return 1;
        }

        // Ensure name ends with "Middleware"
        $name = Generator::ensureSuffix($name, 'Middleware');

        [$class, $path] = Generator::normalizeClass(
            $name,
            'App\\Middleware',
        );
        $file = getcwd() . '/app/' . $path . '.php';

        $namespace = substr($class, 0, strrpos($class, '\\'));
        $short = substr($class, strrpos($class, '\\') + 1);

        $stub = <<<PHP
<?php declare(strict_types=1);

namespace {$namespace};

use Lalaz\Web\Http\Contracts\MiddlewareInterface;
use Lalaz\Web\Http\Contracts\RequestInterface;
use Lalaz\Web\Http\Contracts\ResponseInterface;

final class {$short} implements MiddlewareInterface
{
    public function handle(RequestInterface \$request, ResponseInterface \$response, callable \$next): void
    {
        // TODO: add logic
        \$next(\$request, \$response);
    }
}
PHP;
        Generator::writeFile($file, $stub);
        $output->writeln("Middleware created: {$file}");
        return 0;
    }
}
