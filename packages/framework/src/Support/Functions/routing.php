<?php

declare(strict_types=1);

/**
 * Routing helper functions.
 *
 * Provides convenient global functions for working with routes,
 * including URL generation for named routes and route existence checks.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */

use Lalaz\Web\Routing\Contracts\RouterInterface;

if (!function_exists('route')) {
    /**
     * Generate URL for a named route.
     *
     * @param string $name Route name (e.g., 'users.show')
     * @param array<string, mixed> $parameters Route parameters and query string values
     * @param bool $absolute Whether to generate an absolute URL
     * @return string The generated URL
     *
     * @throws \Lalaz\Exceptions\RoutingException When route is not found
     *
     * @example
     * ```php
     * // Relative URL
     * $url = route('users.show', ['id' => 42]);
     * // Returns: /users/42
     *
     * // Absolute URL
     * $url = route('users.show', ['id' => 42], true);
     * // Returns: https://example.com/users/42
     *
     * // With query parameters
     * $url = route('users.index', ['page' => 2]);
     * // Returns: /users?page=2
     * ```
     */
    function route(string $name, array $parameters = [], bool $absolute = false): string
    {
        /** @var RouterInterface $router */
        $router = resolve(RouterInterface::class);

        return $router->url()->route($name, $parameters, $absolute);
    }
}

if (!function_exists('route_has')) {
    /**
     * Check if a named route exists.
     *
     * @param string $name Route name
     * @return bool
     */
    function route_has(string $name): bool
    {
        /** @var RouterInterface $router */
        $router = resolve(RouterInterface::class);

        return $router->url()->has($name);
    }
}

if (!function_exists('current_route_name')) {
    /**
     * Get the current route name from request attributes.
     *
     * @return string|null
     */
    function current_route_name(): ?string
    {
        // This relies on the route name being stored in request attributes
        // during route matching
        return $_SERVER['LALAZ_ROUTE_NAME'] ?? null;
    }
}
