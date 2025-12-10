<?php

declare(strict_types=1);

namespace Lalaz\Web\Routing;

/**
 * Represents a group of routes with a common prefix.
 *
 * Allows applying middlewares to all routes in the group fluently.
 * RouteGroups are created by the Router::group() method and provide
 * a convenient way to share attributes across multiple routes.
 *
 * Example usage:
 * ```php
 * $router->group('/api', function (Router $r) {
 *     $r->get('/users', [UserController::class, 'index']);
 *     $r->post('/users', [UserController::class, 'store']);
 * })->middleware(AuthMiddleware::class)
 *   ->middleware(RateLimitMiddleware::class);
 * ```
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
class RouteGroup
{
    /**
     * Create a new route group.
     *
     * @param array<int, RouteDefinition> $routes Routes created within this group
     * @param string $prefix The prefix applied to these routes
     */
    public function __construct(
        private array $routes,
        private string $prefix,
    ) {
    }

    /**
     * Apply a single middleware to all routes in the group.
     *
     * Middlewares are executed in the order they are added. The middleware
     * can be a class name (string), a callable, or an object instance.
     *
     * Example:
     * ```php
     * $group->middleware(AuthMiddleware::class)
     *       ->middleware(LoggingMiddleware::class);
     * ```
     *
     * @param callable|string|object $middleware The middleware to apply
     * @return self Returns self for method chaining
     */
    public function middleware(callable|string|object $middleware): self
    {
        return $this->middlewares([$middleware]);
    }

    /**
     * Apply multiple middlewares to all routes in the group.
     *
     * All middlewares in the array are added to each route in the group.
     *
     * Example:
     * ```php
     * $group->middlewares([
     *     AuthMiddleware::class,
     *     RateLimitMiddleware::class,
     *     CorsMiddleware::class,
     * ]);
     * ```
     *
     * @param array<int, callable|string|object> $middlewares Array of middlewares to apply
     * @return self Returns self for method chaining
     */
    public function middlewares(array $middlewares): self
    {
        foreach ($this->routes as $route) {
            $route->addMiddlewares($middlewares);
        }

        return $this;
    }

    /**
     * Get the prefix for this group.
     *
     * Returns the URL prefix that was applied to all routes in this group.
     *
     * @return string The group's URL prefix
     */
    public function prefix(): string
    {
        return $this->prefix;
    }

    /**
     * Get all routes in this group.
     *
     * Returns an array of all RouteDefinition objects that belong to this group.
     *
     * @return array<int, RouteDefinition> Array of route definitions
     */
    public function routes(): array
    {
        return $this->routes;
    }

    /**
     * Get the count of routes in this group.
     *
     * @return int The number of routes in this group
     */
    public function count(): int
    {
        return count($this->routes);
    }
}
