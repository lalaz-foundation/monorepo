<?php

declare(strict_types=1);

namespace Lalaz\Web\Routing\Contracts;

use Lalaz\Web\Routing\MatchedRoute;
use Lalaz\Web\Routing\RouteDefinition;
use Lalaz\Web\Routing\RouteGroup;
use Lalaz\Web\Routing\RouteUrlGenerator;

/**
 * Abstraction for routing implementations consumed by the HTTP runtime.
 *
 * This interface supports both standard HTTP methods (GET, POST, PUT, etc.)
 * and custom HTTP methods (PURGE, LOCK, PROPFIND, etc.) via the `methods()`
 * method, following the Open/Closed Principle.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 */
interface RouterInterface
{
    /**
     * Register a route for multiple HTTP methods at once.
     *
     * This is the extensible way to register routes for custom HTTP methods
     * (PURGE, LOCK, PROPFIND, etc.) or multiple methods for the same handler.
     *
     * @param array<int, string> $methods HTTP methods (e.g., ['GET', 'POST'] or ['PURGE'])
     * @param string $path URL path pattern
     * @param callable|array|string $handler Route handler
     * @param array<int, callable|array|string> $middlewares Route middlewares
     * @return array<int, RouteDefinition> Created route definitions
     */
    public function methods(
        array $methods,
        string $path,
        callable|array|string $handler,
        array $middlewares = [],
    ): array;

    /**
     * Registers a route for the provided HTTP method.
     *
     * @param string $method
     * @param string $path
     * @param callable|array|string $handler
     * @param array<int, callable|array|string> $middlewares
     * @return RouteDefinition
     */
    public function route(
        string $method,
        string $path,
        callable|array|string $handler,
        array $middlewares = [],
    ): RouteDefinition;

    /**
     * Shortcut for registering a GET route.
     *
     * @param string $path
     * @param callable|array|string $handler
     * @param array<int, callable|array|string> $middlewares
     * @return RouteDefinition
     */
    public function get(
        string $path,
        callable|array|string $handler,
        array $middlewares = [],
    ): RouteDefinition;

    /**
     * Shortcut for registering a POST route.
     *
     * @param string $path
     * @param callable|array|string $handler
     * @param array<int, callable|array|string> $middlewares
     * @return RouteDefinition
     */
    public function post(
        string $path,
        callable|array|string $handler,
        array $middlewares = [],
    ): RouteDefinition;

    /**
     * Shortcut for registering a PUT route.
     *
     * @param string $path
     * @param callable|array|string $handler
     * @param array<int, callable|array|string> $middlewares
     * @return RouteDefinition
     */
    public function put(
        string $path,
        callable|array|string $handler,
        array $middlewares = [],
    ): RouteDefinition;

    /**
     * Shortcut for registering a PATCH route.
     *
     * @param string $path
     * @param callable|array|string $handler
     * @param array<int, callable|array|string> $middlewares
     * @return RouteDefinition
     */
    public function patch(
        string $path,
        callable|array|string $handler,
        array $middlewares = [],
    ): RouteDefinition;

    /**
     * Shortcut for registering a DELETE route.
     *
     * @param string $path
     * @param callable|array|string $handler
     * @param array<int, callable|array|string> $middlewares
     * @return RouteDefinition
     */
    public function delete(
        string $path,
        callable|array|string $handler,
        array $middlewares = [],
    ): RouteDefinition;

    /**
     * Shortcut for registering an OPTIONS route.
     *
     * @param string $path
     * @param callable|array|string $handler
     * @param array<int, callable|array|string> $middlewares
     * @return RouteDefinition
     */
    public function options(
        string $path,
        callable|array|string $handler,
        array $middlewares = [],
    ): RouteDefinition;

    /**
     * Shortcut for registering a HEAD route.
     *
     * @param string $path
     * @param callable|array|string $handler
     * @param array<int, callable|array|string> $middlewares
     * @return RouteDefinition
     */
    public function head(
        string $path,
        callable|array|string $handler,
        array $middlewares = [],
    ): RouteDefinition;

    /**
     * Register the handler for all common HTTP verbs.
     *
     * @param string $path
     * @param callable|array|string $handler
     * @param array<int, callable|array|string> $middlewares
     * @return array<int, RouteDefinition>
     */
    public function any(
        string $path,
        callable|array|string $handler,
        array $middlewares = [],
    ): array;

    /**
     * Create a route group with common attributes (prefix, middleware).
     *
     * Routes defined within the callback will have the prefix prepended
     * and group middlewares applied.
     *
     * Supports two signatures:
     * - group(string $prefix, callable $callback) - Legacy: prefix only
     * - group(array $attributes, callable $callback) - New: prefix + middleware
     *
     * Example:
     * ```php
     * // Simple prefix
     * $router->group('/admin', function($router) { ... });
     *
     * // With middleware
     * $router->group(['prefix' => '/admin', 'middleware' => [AuthMiddleware::class]], function($router) { ... });
     * ```
     *
     * @param string|array{prefix?: string, middleware?: array} $attributes Group prefix or attributes array
     * @param callable(RouterInterface): void $callback Callback that defines routes
     * @return RouteGroup The route group for chaining middlewares
     */
    public function group(string|array $attributes, callable $callback): RouteGroup;

    /**
     * Register resourceful routes for a controller.
     *
     * Creates standard CRUD routes:
     * - GET    /{name}           -> index
     * - GET    /{name}/create    -> create
     * - POST   /{name}           -> store
     * - GET    /{name}/{id}      -> show
     * - GET    /{name}/{id}/edit -> edit
     * - PUT    /{name}/{id}      -> update
     * - PATCH  /{name}/{id}      -> update
     * - DELETE /{name}/{id}      -> destroy
     *
     * @param string $name Resource name (used as URL prefix)
     * @param string|array $controller Controller class or [class, method] mapping
     * @param array<string> $only Limit to specific actions
     * @param array<string> $except Exclude specific actions
     * @return RouteGroup The route group for chaining middlewares
     */
    public function resource(
        string $name,
        string|array $controller,
        array $only = [],
        array $except = [],
    ): RouteGroup;

    /**
     * Register controllers that use #[Route] attributes.
     *
     * @param array<int, class-string> $controllers
     * @return void
     */
    public function registerControllers(array $controllers): void;

    /**
     * Match a request method/path into a route.
     *
     * @param string $method
     * @param string $path
     * @return MatchedRoute
     */
    public function match(string $method, string $path): MatchedRoute;

    /**
     * Returns all defined routes (unsorted).
     *
     * @return array<int, RouteDefinition>
     */
    public function all(): array;

    /**
     * Find a route by its name.
     *
     * @param string $name The route name
     * @return RouteDefinition|null
     */
    public function findRouteByName(string $name): ?RouteDefinition;

    /**
     * Get the URL generator instance.
     *
     * @return RouteUrlGenerator
     */
    public function url(): RouteUrlGenerator;

    /**
     * Exports the router definitions to a cacheable array.
     *
     * @return array<int, array<string, mixed>>
     */
    public function exportDefinitions(): array;

    /**
     * Hydrates the router from cached definitions.
     *
     * @param array<int, array<string, mixed>> $definitions
     * @return void
     */
    public function loadFromDefinitions(array $definitions): void;
}
