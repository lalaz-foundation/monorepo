<?php

declare(strict_types=1);

namespace Lalaz\Console\Commands;

use Lalaz\Console\Contracts\CommandInterface;
use Lalaz\Console\Input;
use Lalaz\Console\Output;

/**
 * Command that generates a route entry in routes/web.php.
 *
 * Appends a new route definition to the web routes file
 * with the specified path and HTTP method.
 *
 * Usage: php lalaz craft:route /status --method=GET
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
final class CraftRouteCommand implements CommandInterface
{
    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'craft:route';
    }

    /**
     * {@inheritdoc}
     */
    public function description(): string
    {
        return 'Generate an entry route in routes/web.php';
    }

    /**
     * {@inheritdoc}
     */
    public function arguments(): array
    {
        return [
            [
                'name' => 'path',
                'description' => 'Route path (e.g., /status)',
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
                'name' => 'method',
                'description' => 'HTTP method',
                'requiresValue' => true,
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Input $input, Output $output): int
    {
        $path = $input->argument(0);
        if (!$path) {
            $output->error('Usage: php lalaz craft:route /status --method=GET');
            return 1;
        }

        // Ensure path starts with /
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        $method = strtolower((string) ($input->option('method') ?? 'GET'));
        $file = getcwd() . '/routes/web.php';

        if (!file_exists($file)) {
            $output->error('routes/web.php not found.');
            return 1;
        }

        $content = file_get_contents($file);

        // Find the closing }; of the return function
        $closingPos = strrpos($content, '};');
        if ($closingPos === false) {
            $output->error('Could not find closing }; in routes/web.php');
            return 1;
        }

        // Build the new route snippet
        $snippet = sprintf(
            "\n    \$router->%s('%s', function (Response \$response): void {\n        \$response->json(['path' => '%s']);\n    });\n",
            $method,
            $path,
            $path,
        );

        // Insert the snippet before the closing };
        $newContent = substr($content, 0, $closingPos) . $snippet . substr($content, $closingPos);

        file_put_contents($file, $newContent);
        $output->writeln("Route [{$method}] {$path} appended to routes/web.php");
        return 0;
    }
}
