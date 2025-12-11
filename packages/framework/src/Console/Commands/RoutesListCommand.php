<?php

declare(strict_types=1);

namespace Lalaz\Console\Commands;

use Lalaz\Console\Contracts\CommandInterface;
use Lalaz\Console\Input;
use Lalaz\Console\Output;
use Lalaz\Runtime\Http\HttpApplication;
use Lalaz\Web\Routing\RouteDefinition;

/**
 * Command that displays registered HTTP routes.
 *
 * Lists all routes with their HTTP methods, URIs, handlers, and middleware.
 * Supports filtering by method, path, controller, and middleware.
 * Output can be formatted as table, JSON, or Markdown.
 *
 * Usage: php lalaz routes:list [--method=GET] [--format=json]
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
final class RoutesListCommand implements CommandInterface
{
    /**
     * Creates a new RoutesListCommand instance.
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
        return 'routes:list';
    }

    /**
     * {@inheritdoc}
     */
    public function description(): string
    {
        return 'Display registered HTTP routes';
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
        return [
            [
                'name' => 'method',
                'description' => 'Filter by HTTP method (e.g. GET)',
                'requiresValue' => true,
            ],
            [
                'name' => 'path',
                'description' => 'Filter by URI substring',
                'requiresValue' => true,
            ],
            [
                'name' => 'controller',
                'description' => 'Filter by handler/controller name',
                'requiresValue' => true,
            ],
            [
                'name' => 'middleware',
                'description' => 'Filter by middleware name',
                'requiresValue' => true,
            ],
            [
                'name' => 'format',
                'description' => 'Output format: table, json, md',
                'requiresValue' => true,
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Input $input, Output $output): int
    {
        $this->app->warmRouting();
        $routes = $this->app->router()->all();

        if ($routes === []) {
            $output->writeln('No routes registered.');
            return 0;
        }

        $filters = [
            'method' => $this->normalizeFilter($input->option('method')),
            'path' => $this->normalizeFilter($input->option('path')),
            'controller' => $this->normalizeFilter(
                $input->option('controller'),
            ),
            'middleware' => $this->normalizeFilter(
                $input->option('middleware'),
            ),
        ];

        $filtered = array_values(
            array_filter(
                $routes,
                fn (RouteDefinition $route) => $this->matchesFilters(
                    $route,
                    $filters,
                ),
            ),
        );

        if ($filtered === []) {
            $output->writeln('No routes matched the provided filters.');
            return 0;
        }

        $rows = array_map(
            fn (RouteDefinition $route) => [
                'method' => $route->method(),
                'path' => $route->path(),
                'handler' => $this->describeHandler($route->handler()),
                'middlewares' => $this->describeMiddlewares($route),
            ],
            $filtered,
        );

        $format = strtolower((string) $input->option('format', 'table'));

        if (!in_array($format, ['table', 'json', 'md'], true)) {
            $output->writeln(
                "Invalid format '" .
                    $format .
                    "'. Available formats: table, json, md.",
            );
            return 1;
        }

        $output->writeln($this->renderRows($rows, $format));
        return 0;
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

        if ($handler instanceof \Closure) {
            $ref = new \ReflectionFunction($handler);
            if ($ref->isClosure()) {
                $file = basename((string) $ref->getFileName());
                return 'Closure@' . $file . ':' . $ref->getStartLine();
            }
        }

        return is_object($handler) ? get_class($handler) : 'Closure';
    }

    /**
     * Describes route middleware as an array of strings.
     *
     * @param RouteDefinition $route The route definition
     *
     * @return array<int, string> Middleware descriptions
     */
    private function describeMiddlewares(RouteDefinition $route): array
    {
        $names = [];
        foreach ($route->middlewares() as $middleware) {
            if (is_string($middleware)) {
                $names[] = $middleware;
                continue;
            }

            if (is_array($middleware)) {
                [$classOrInstance, $method] = $middleware;
                $class = is_object($classOrInstance)
                    ? get_class($classOrInstance)
                    : (string) $classOrInstance;
                $names[] = $class . '@' . $method;
                continue;
            }

            if ($middleware instanceof \Closure) {
                $ref = new \ReflectionFunction($middleware);
                $file = basename((string) $ref->getFileName());
                $names[] = 'Closure@' . $file . ':' . $ref->getStartLine();
                continue;
            }

            if (is_object($middleware)) {
                $names[] = get_class($middleware);
                continue;
            }

            $names[] = 'Closure';
        }

        return $names;
    }

    /**
     * Renders route rows in the specified format.
     *
     * @param array<int, array{method:string,path:string,handler:string,middlewares:array<int,string>}> $rows Route data
     * @param string $format Output format (table, json, md)
     *
     * @return string Formatted output
     */
    private function renderRows(array $rows, string $format): string
    {
        return match ($format) {
            'json' => json_encode(
                array_map(
                    fn (array $row) => [
                        'method' => $row['method'],
                        'path' => $row['path'],
                        'handler' => $row['handler'],
                        'middlewares' => $row['middlewares'],
                    ],
                    $rows,
                ),
                JSON_PRETTY_PRINT,
            ) ?:
            '[]',
            'md' => $this->formatMarkdown($rows),
            default => $this->formatTable($rows),
        };
    }

    /**
     * Formats rows as a Markdown table.
     *
     * @param array<int, array{method:string,path:string,handler:string,middlewares:array<int,string>}> $rows Route data
     *
     * @return string Markdown output
     */
    private function formatMarkdown(array $rows): string
    {
        $lines = [
            '| Method | URI | Handler | Middlewares |',
            '| ------ | --- | ------- | ----------- |',
        ];

        foreach ($rows as $row) {
            $lines[] =
                '| ' .
                $row['method'] .
                ' | ' .
                $row['path'] .
                ' | ' .
                $row['handler'] .
                ' | ' .
                ($row['middlewares'] === []
                    ? '-'
                    : implode(', ', $row['middlewares'])) .
                ' |';
        }

        return implode(PHP_EOL, $lines);
    }

    /**
     * Formats rows as an ASCII table.
     *
     * @param array<int, array{method:string,path:string,handler:string,middlewares:array<int,string>}> $rows Route data
     *
     * @return string Table output
     */
    private function formatTable(array $rows): string
    {
        $methodWidth = max(
            array_map(fn ($row) => strlen($row['method']), $rows),
        );
        $pathWidth = max(array_map(fn ($row) => strlen($row['path']), $rows));
        $handlerWidth = max(
            array_map(fn ($row) => strlen($row['handler']), $rows),
        );
        $middlewaresWidth = max(
            array_map(
                fn ($row) => strlen(
                    $row['middlewares'] === []
                        ? '-'
                        : implode(', ', $row['middlewares']),
                ),
                $rows,
            ),
        );

        $methodWidth = max($methodWidth, strlen('Method')) + 2;
        $pathWidth = max($pathWidth, strlen('URI')) + 2;
        $handlerWidth = max($handlerWidth, strlen('Handler')) + 2;
        $middlewaresWidth = max($middlewaresWidth, strlen('Middlewares')) + 2;

        $widths = [$methodWidth, $pathWidth, $handlerWidth, $middlewaresWidth];

        $separator = str_repeat('-', array_sum($widths) + 13);
        $lines = [
            $separator,
            $this->formatRow(
                ['Method', 'URI', 'Handler', 'Middlewares'],
                $widths,
            ),
            $separator,
        ];

        foreach ($rows as $row) {
            $lines[] = $this->formatRow(
                [
                    $row['method'],
                    $row['path'],
                    $row['handler'],
                    $row['middlewares'] === []
                        ? '-'
                        : implode(', ', $row['middlewares']),
                ],
                $widths,
            );
        }

        $lines[] = $separator;

        return implode(PHP_EOL, $lines);
    }

    /**
     * Formats a single row with column widths.
     *
     * @param array<int, string> $values Column values
     * @param array<int, int>    $widths Column widths
     *
     * @return string Formatted row
     */
    private function formatRow(array $values, array $widths): string
    {
        $segments = [];
        foreach ($values as $index => $value) {
            $segments[] = sprintf('%-' . $widths[$index] . 's', $value);
        }

        return '| ' . implode(' | ', $segments) . ' |';
    }

    /**
     * Checks if a route matches the provided filters.
     *
     * @param RouteDefinition $route   The route to check
     * @param array{method:?string,path:?string,controller:?string,middleware:?string} $filters Filter values
     *
     * @return bool True if route matches all filters
     */
    private function matchesFilters(
        RouteDefinition $route,
        array $filters,
    ): bool {
        if (
            $filters['method'] !== null &&
            strtoupper($filters['method']) !== $route->method()
        ) {
            return false;
        }

        if (
            $filters['path'] !== null &&
            stripos($route->path(), $filters['path']) === false
        ) {
            return false;
        }

        if ($filters['controller'] !== null) {
            $handler = $this->describeHandler($route->handler());
            if (stripos($handler, $filters['controller']) === false) {
                return false;
            }
        }

        if ($filters['middleware'] !== null) {
            $middlewares = $this->describeMiddlewares($route);
            $match = false;
            foreach ($middlewares as $middleware) {
                if (stripos($middleware, $filters['middleware']) !== false) {
                    $match = true;
                    break;
                }
            }

            if (!$match) {
                return false;
            }
        }

        return true;
    }

    /**
     * Normalizes a filter value.
     *
     * @param mixed $value The filter value
     *
     * @return string|null Trimmed string or null
     */
    private function normalizeFilter(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
