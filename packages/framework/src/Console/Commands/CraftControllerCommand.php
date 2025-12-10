<?php

declare(strict_types=1);

namespace Lalaz\Console\Commands;

use Lalaz\Console\Contracts\CommandInterface;
use Lalaz\Console\Generators\Generator;
use Lalaz\Console\Input;
use Lalaz\Console\Output;

/**
 * Command that generates an HTTP controller class.
 *
 * Creates a new controller with a sample route attribute and handler method.
 * Supports generating invokable controllers with --invokable flag.
 * Automatically appends "Controller" suffix if not provided.
 *
 * Usage: php lalaz craft:controller Home
 *        php lalaz craft:controller HomeController
 *        php lalaz craft:controller Home --invokable
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
final class CraftControllerCommand implements CommandInterface
{
    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'craft:controller';
    }

    /**
     * {@inheritdoc}
     */
    public function description(): string
    {
        return 'Generate an HTTP controller class';
    }

    /**
     * {@inheritdoc}
     */
    public function arguments(): array
    {
        return [
            [
                'name' => 'name',
                'description' => 'Controller class name',
                'optional' => false,
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function options(): array
    {
        return [
            [
                'name' => 'invokable',
                'description' => 'Generate an __invoke handler',
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Input $input, Output $output): int
    {
        $name = $input->argument(0);
        if (!$name) {
            $output->error(
                'Usage: php lalaz craft:controller Home',
            );
            return 1;
        }

        // Ensure name ends with "Controller"
        $name = Generator::ensureSuffix($name, 'Controller');

        [$class, $path] = Generator::normalizeClass(
            $name,
            'App\\Controllers',
        );
        $file = getcwd() . '/app/' . $path . '.php';
        $stub = $this->buildStub($class, $input->hasFlag('invokable'));

        Generator::writeFile($file, $stub);
        $output->writeln("Controller created: {$file}");
        return 0;
    }

    /**
     * Builds the controller stub code.
     *
     * @param string $class     The fully-qualified class name
     * @param bool   $invokable Whether to generate an invokable controller
     *
     * @return string The generated PHP code
     */
    private function buildStub(string $class, bool $invokable): string
    {
        $namespace = substr($class, 0, strrpos($class, '\\'));
        $short = substr($class, strrpos($class, '\\') + 1);
        $method = $invokable ? '__invoke' : 'handle';

        return <<<PHP
<?php declare(strict_types=1);

namespace {$namespace};

use Lalaz\Web\Http\Response;
use Lalaz\Web\Routing\Attribute\Route;

final class {$short}
{
    #[Route(path: '/example', method: 'GET')]
    public function {$method}(Response \$response): void
    {
        \$response->json(['message' => '{$short} works!']);
    }
}
PHP;
    }
}
