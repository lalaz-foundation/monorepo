<?php

declare(strict_types=1);

namespace Lalaz\Console\Commands;

use Lalaz\Console\Contracts\CommandInterface;
use Lalaz\Console\Input;
use Lalaz\Console\Output;
use Lalaz\Runtime\Http\HttpApplication;
use Lalaz\Web\Routing\RouteDefinition;

/**
 * Command that inspects a specific route.
 *
 * Shows detailed information about a route including method,
 * URI, handler, and middleware. Routes can be selected by
 * index number or URI path.
 *
 * Usage: php lalaz routes:inspect 2
 *        php lalaz routes:inspect /users
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
final class RoutesInspectCommand implements CommandInterface
{
    /**
     * Creates a new RoutesInspectCommand instance.
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
        return 'routes:inspect';
    }

    /**
     * {@inheritdoc}
     */
    public function description(): string
    {
        return 'Show detailed information about a specific route (by index or URI).';
    }

    /**
     * {@inheritdoc}
     */
    public function arguments(): array
    {
        return [
            [
                'name' => 'target',
                'description' => 'Route index or URI to inspect (e.g., 2 or /users)',
                'optional' => false,
            ],
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
        $target = $input->argument(0);
        if ($target === null) {
            $output->error('Usage: php lalaz routes:inspect <index|uri>');
            return 1;
        }

        $this->app->warmRouting();
        $routes = $this->app->router()->all();

        $route = is_numeric($target)
            ? $routes[(int) $target - 1] ?? null
            : $this->findByUri($routes, $target);

        if (!$route instanceof RouteDefinition) {
            $output->error('Route not found.');
            return 1;
        }

        $this->renderRoute($route, $output);
        return 0;
    }

    /**
     * Finds a route by its URI path.
     *
     * @param array<int, RouteDefinition> $routes All registered routes
     * @param string                      $uri    The URI to search for
     *
     * @return RouteDefinition|null The matching route or null
     */
    private function findByUri(array $routes, string $uri): ?RouteDefinition
    {
        foreach ($routes as $route) {
            if ($route->path() === RouteDefinition::normalizePath($uri)) {
                return $route;
            }
        }

        return null;
    }

    /**
     * Renders route details to output.
     *
     * @param RouteDefinition $route  The route to render
     * @param Output          $output The output instance
     *
     * @return void
     */
    private function renderRoute(RouteDefinition $route, Output $output): void
    {
        $output->writeln('Method: ' . $route->method());
        $output->writeln('URI: ' . $route->path());
        $output->writeln('Handler: ' . $this->describeHandler($route->handler()));
        $output->writeln('Middlewares:');

        if ($route->middlewares() === []) {
            $output->writeln('  (none)');
        } else {
            foreach ($route->middlewares() as $middleware) {
                $output->writeln('  - ' . (is_string($middleware) ? $middleware : get_debug_type($middleware)));
            }
        }

        $output->writeln('---');
    }

    /**
     * Describes a route handler as a string.
     *
     * @param callable|array|string $handler The route handler
     *
     * @return string Human-readable handler description
     */
    private function describeHandler(callable|array|string $handler): string
    {
        if (is_string($handler)) {
            return $handler;
        }

        if (is_array($handler)) {
            [$classOrInstance, $method] = $handler;
            $class = is_object($classOrInstance)
                ? get_class($classOrInstance)
                : (string) $classOrInstance;
            return $class . '@' . $method;
        }

        if (is_object($handler)) {
            return get_class($handler);
        }

        return 'Closure';
    }
}
