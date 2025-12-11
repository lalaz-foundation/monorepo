<?php

declare(strict_types=1);

namespace Lalaz\Console\Commands;

use Lalaz\Console\Contracts\CommandInterface;
use Lalaz\Console\Input;
use Lalaz\Console\Output;
use Lalaz\Runtime\Http\HttpApplication;
use Lalaz\Web\Routing\RouteDefinition;

/**
 * Command that validates route definitions.
 *
 * Checks for duplicate route definitions (same method + URI)
 * that could cause routing conflicts.
 *
 * Usage: php lalaz routes:validate
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
final class RoutesValidateCommand implements CommandInterface
{
    /**
     * Creates a new RoutesValidateCommand instance.
     *
     * @param HttpApplication $app The application instance
     */
    public function __construct(private HttpApplication $app)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'routes:validate';
    }

    /**
     * {@inheritdoc}
     */
    public function description(): string
    {
        return 'Check for duplicate routes or missing cache files.';
    }

    /**
     * {@inheritdoc}
     */
    public function arguments(): array
    {
        return [];
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
        $this->app->warmRouting();
        $routes = $this->app->router()->all();
        $duplicates = $this->findDuplicates($routes);

        if ($duplicates !== []) {
            $output->error('Duplicate method + URI combinations detected:');
            foreach ($duplicates as $key => $instances) {
                $output->error('  ' . $key . ' registered ' . count($instances) . ' times');
            }
            return 1;
        }

        $output->writeln('Routes look good.');
        return 0;
    }

    /**
     * Finds duplicate route definitions.
     *
     * Routes with the same method + URI combination are considered
     * duplicates and could cause routing conflicts.
     *
     * @param array<int, RouteDefinition> $routes All registered routes
     *
     * @return array<string, array<int, RouteDefinition>> Duplicate routes grouped by key
     */
    private function findDuplicates(array $routes): array
    {
        $byKey = [];
        foreach ($routes as $route) {
            $key = $route->method() . ' ' . $route->path();
            $byKey[$key][] = $route;
        }

        return array_filter($byKey, fn ($list) => count($list) > 1);
    }
}
