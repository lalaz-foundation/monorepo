<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Fixtures\Factories;

/**
 * Factory for creating temporary route files in tests.
 */
final class RouteFileFactory
{
    /**
     * Create a temporary route file with a simple GET route.
     *
     * @return string The path to the created file.
     */
    public static function simple(string $path = '/from-file', string $body = 'from-file'): string
    {
        $content = <<<PHP
        <?php
        use Lalaz\Web\Routing\Router;
        return function (Router \$router): void {
            \$router->get('{$path}', function (\Lalaz\Web\Http\Response \$response) {
                \$response->setBody('{$body}');
            });
        };
        PHP;

        return self::createFile($content);
    }

    /**
     * Create a temporary route file with custom content.
     *
     * @return string The path to the created file.
     */
    public static function withContent(string $phpContent): string
    {
        return self::createFile($phpContent);
    }

    /**
     * Create a route file with multiple routes.
     *
     * @param array<string, array{method: string, path: string, body: string}> $routes
     * @return string The path to the created file.
     */
    public static function withRoutes(array $routes): string
    {
        $routeDefinitions = '';

        foreach ($routes as $route) {
            $method = strtolower($route['method']);
            $path = $route['path'];
            $body = $route['body'];

            $routeDefinitions .= <<<PHP
                \$router->{$method}('{$path}', function (\Lalaz\Web\Http\Response \$response) {
                    \$response->setBody('{$body}');
                });
            PHP;
            $routeDefinitions .= "\n";
        }

        $content = <<<PHP
        <?php
        use Lalaz\Web\Routing\Router;
        return function (Router \$router): void {
        {$routeDefinitions}
        };
        PHP;

        return self::createFile($content);
    }

    private static function createFile(string $content): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'lalaz_routes_');
        $path = $tmp !== false ? $tmp : sys_get_temp_dir() . '/lalaz_routes_' . uniqid();

        file_put_contents($path, $content);

        return $path;
    }
}
