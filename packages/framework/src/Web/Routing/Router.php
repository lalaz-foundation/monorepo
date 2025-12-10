<?php

declare(strict_types=1);

namespace Lalaz\Web\Routing;

use Lalaz\Exceptions\HttpException;
use Lalaz\Web\Routing\Attribute\Route as RouteAttribute;
use Lalaz\Web\Routing\Contracts\RouterInterface;

/**
 * Lightweight HTTP router for the minimal Lalaz kernel.
 *
 * Supports standard HTTP methods (GET, POST, PUT, PATCH, DELETE, OPTIONS, HEAD)
 * as well as custom methods via the `methods()` method (e.g., PURGE, LOCK, PROPFIND).
 * Provides route grouping, resource routes, attribute-based routing, and URL generation.
 *
 * @package lalaz/framework
 */
class Router implements RouterInterface
{
    /**
     * Standard HTTP methods with convenience shortcuts.
     *
     * @var array<int, string>
     */
    public const STANDARD_METHODS = [
        'GET',
        'POST',
        'PUT',
        'PATCH',
        'DELETE',
        'OPTIONS',
        'HEAD',
    ];

    /**
     * Registered route definitions (master list, preserves registration order).
     *
     * @var array<int, RouteDefinition>
     */
    private array $routes = [];

    /**
     * Fast lookup map for fully static routes (no parameters) by method and path.
     * Format: [METHOD => ["/path" => RouteDefinition]]
     *
     * @var array<string, array<string, RouteDefinition>>
     */
    private array $staticRoutes = [];

    /**
     * Dynamic (parameterized) routes indexed by method, segment count, and
     * a literal-position key. This reduces the bucket size for dynamic lookups
     * when many routes share the same segment count but differ in literal
     * values at known positions.
     *
     * Format: [METHOD => [segmentCount => [literalKey => array<int, RouteDefinition>]]]
     * literalKey is a string like "0=users|2=posts" (position=value pairs)
     * or '_' when there are no literal parts.
     *
     * @var array<string, array<int, array<string, array<int, RouteDefinition>>>>
     */
    private array $dynamicRoutes = [];

    /**
     * Number of leading segments to include in the literal-prefix key.
     * This is intentionally small to keep memory bounded while helping
     * bucketize common patterns like /dynamic/route/{id} where the first
     * few segments are often literal values.
     */
    private int $literalPrefixSize = 3;

    /**
     * Current prefix for grouped routes.
     *
     * @var string|null
     */
    private ?string $prefix = null;

    /**
     * Current group middlewares to apply to routes.
     *
     * @var array<int, callable|string|object>
     */
    private array $groupMiddlewares = [];

    /**
     * URL generator instance (lazy loaded).
     *
     * @var RouteUrlGenerator|null
     */
    private ?RouteUrlGenerator $urlGenerator = null;

    /* -------------------------------------------------------------------------
     * Route registration
     * ---------------------------------------------------------------------- */

    /**
     * Register a route for multiple HTTP methods at once.
     *
     * @param array<int, string> $methods
     * @param string $path
     * @param callable|array|string $handler
     * @param array<int, callable|string|object> $middlewares
     * @return array<int, RouteDefinition>
     */
    public function methods(
        array $methods,
        string $path,
        callable|array|string $handler,
        array $middlewares = [],
    ): array {
        $definitions = [];

        foreach ($methods as $method) {
            $definitions[] = $this->route($method, $path, $handler, $middlewares);
        }

        return $definitions;
    }

    /**
     * Register a new route for the given HTTP method.
     *
     * @param string $method
     * @param string $path
     * @param callable|array|string $handler
     * @param array<int, callable|string|object> $middlewares
     * @return RouteDefinition
     */
    public function route(
        string $method,
        string $path,
        $handler,
        array $middlewares = [],
    ): RouteDefinition {
        // Apply prefix if we're inside a group
        if ($this->prefix !== null) {
            $path = $this->prefix . '/' . ltrim($path, '/');
        }

        // Merge group middlewares with route-specific middlewares
        $allMiddlewares = array_merge($this->groupMiddlewares, $middlewares);

        $definition = new RouteDefinition(
            strtoupper($method),
            $path,
            $handler,
            $allMiddlewares,
        );

        // Master list
        $this->routes[] = $definition;

        // Index into static or dynamic tables
        $m     = $definition->method();
        $rPath = $definition->path(); // assumindo path já normalizado dentro de RouteDefinition

        if ($this->isStaticPath($rPath)) {
            if (!isset($this->staticRoutes[$m])) {
                $this->staticRoutes[$m] = [];
            }

            // Last-registered wins
            $this->staticRoutes[$m][$rPath] = $definition;
        } else {
            // Detect splat-like patterns (variable-length segments), e.g. {path:.+}
            $patterned = preg_match('/\{[\w-]+:(?:.*[+*].*|.*\/.*)\}/', $rPath) === 1;

            $segmentCount = $this->getSegmentCount($rPath);

            if (!isset($this->dynamicRoutes[$m][$segmentCount])) {
                $this->dynamicRoutes[$m][$segmentCount] = [];
            }

            $literalKey = $this->literalPrefixKeyFromPath($rPath);

            if ($patterned) {
                if (!isset($this->dynamicRoutes[$m]['splat'])) {
                    $this->dynamicRoutes[$m]['splat'] = [];
                }

                $this->dynamicRoutes[$m]['splat'][] = $definition;
            } else {
                if (!isset($this->dynamicRoutes[$m][$segmentCount][$literalKey])) {
                    $this->dynamicRoutes[$m][$segmentCount][$literalKey] = [];
                }

                $this->dynamicRoutes[$m][$segmentCount][$literalKey][] = $definition;
            }
        }

        return $definition;
    }

    public function get(string $path, $handler, array $middlewares = []): RouteDefinition
    {
        return $this->route('GET', $path, $handler, $middlewares);
    }

    public function post(string $path, $handler, array $middlewares = []): RouteDefinition
    {
        return $this->route('POST', $path, $handler, $middlewares);
    }

    public function put(string $path, $handler, array $middlewares = []): RouteDefinition
    {
        return $this->route('PUT', $path, $handler, $middlewares);
    }

    public function patch(string $path, $handler, array $middlewares = []): RouteDefinition
    {
        return $this->route('PATCH', $path, $handler, $middlewares);
    }

    public function delete(string $path, $handler, array $middlewares = []): RouteDefinition
    {
        return $this->route('DELETE', $path, $handler, $middlewares);
    }

    public function options(string $path, $handler, array $middlewares = []): RouteDefinition
    {
        return $this->route('OPTIONS', $path, $handler, $middlewares);
    }

    public function head(string $path, $handler, array $middlewares = []): RouteDefinition
    {
        return $this->route('HEAD', $path, $handler, $middlewares);
    }

    /**
     * Register handler for all standard HTTP verbs.
     */
    public function any(string $path, $handler, array $middlewares = []): array
    {
        return $this->methods(self::STANDARD_METHODS, $path, $handler, $middlewares);
    }

    /**
     * Create a route group with common attributes (prefix, middleware).
     *
     * Supports two signatures:
     * - group(string $prefix, callable $callback) - Legacy: prefix only
     * - group(array $attributes, callable $callback) - New: prefix + middleware
     *
     * Example with array attributes:
     * ```php
     * $router->group(['prefix' => '/admin', 'middleware' => [AuthMiddleware::class]], function($router) {
     *     $router->get('/dashboard', 'AdminController@dashboard');
     * });
     * ```
     *
     * @param string|array{prefix?: string, middleware?: array} $attributes Group prefix or attributes array
     * @param callable $callback Callback receiving the router instance
     * @return RouteGroup
     */
    public function group(string|array $attributes, callable $callback): RouteGroup
    {
        // Normalize attributes
        if (is_string($attributes)) {
            $attributes = ['prefix' => $attributes];
        }

        $prefix = $attributes['prefix'] ?? '';
        $middlewares = $attributes['middleware'] ?? [];

        // Save previous state
        $previousPrefix = $this->prefix;
        $previousMiddlewares = $this->groupMiddlewares;

        // Apply new group attributes
        $this->prefix = rtrim(($previousPrefix ?? '') . '/' . trim($prefix, '/'), '/');
        $this->groupMiddlewares = array_merge($previousMiddlewares, $middlewares);

        $beforeGroupRoutes = count($this->routes);

        $callback($this);

        $groupRoutes = array_slice($this->routes, $beforeGroupRoutes);
        $routeGroup  = new RouteGroup($groupRoutes, $this->prefix);

        // Restore previous state
        $this->prefix = $previousPrefix;
        $this->groupMiddlewares = $previousMiddlewares;

        return $routeGroup;
    }

    /**
     * Register resourceful routes for a controller.
     */
    public function resource(
        string $name,
        string|array $controller,
        array $only = [],
        array $except = [],
    ): RouteGroup {
        $name      = trim($name, '/');
        $paramName = rtrim($name, 's') . 'Id'; // users -> userId

        $actions = [
            'index'   => ['GET', "/{$name}", 'index'],
            'create'  => ['GET', "/{$name}/create", 'create'],
            'store'   => ['POST', "/{$name}", 'store'],
            'show'    => ['GET', "/{$name}/{{$paramName}}", 'show'],
            'edit'    => ['GET', "/{$name}/{{$paramName}}/edit", 'edit'],
            'update'  => ['PUT', "/{$name}/{{$paramName}}", 'update'],
            'patch'   => ['PATCH', "/{$name}/{{$paramName}}", 'update'],
            'destroy' => ['DELETE', "/{$name}/{{$paramName}}", 'destroy'],
        ];

        if (!empty($only)) {
            $actions = array_intersect_key($actions, array_flip($only));
        }

        if (!empty($except)) {
            $actions = array_diff_key($actions, array_flip($except));
        }

        $beforeRoutes = count($this->routes);

        foreach ($actions as [$method, $path, $action]) {
            $handler = is_string($controller) ? [$controller, $action] : $controller;
            $this->route($method, $path, $handler);
        }

        $groupRoutes = array_slice($this->routes, $beforeRoutes);

        return new RouteGroup($groupRoutes, "/{$name}");
    }

    /**
     * Register routes via #[Route] attributes in controllers.
     *
     * @param array<int, class-string> $controllers
     */
    public function registerControllers(array $controllers): void
    {
        foreach ($controllers as $controller) {
            if (!class_exists($controller)) {
                continue;
            }

            $reflection = new \ReflectionClass($controller);

            foreach ($reflection->getMethods() as $method) {
                foreach ($method->getAttributes(RouteAttribute::class) as $attribute) {
                    /** @var RouteAttribute $instance */
                    $instance = $attribute->newInstance();

                    foreach ($instance->getMethods() as $httpMethod) {
                        $this->route(
                            $httpMethod,
                            $instance->getPath(),
                            [$controller, $method->getName()],
                            $instance->getMiddlewares(),
                        );
                    }
                }
            }
        }
    }

    /* -------------------------------------------------------------------------
     * Matching
     * ---------------------------------------------------------------------- */

    /**
     * Match a request against the registered routes.
     *
     * @throws HttpException
     */
    public function match(string $method, string $path): MatchedRoute
    {
        $normalizedPath = RouteDefinition::normalizePath($path);
        $method = strtoupper($method);

        // 1) Fast path: static exact match (HEAD incluído)
        if (isset($this->staticRoutes[$method][$normalizedPath])) {
            return new MatchedRoute($this->staticRoutes[$method][$normalizedPath], []);
        }

        // 2) HEAD -> GET static fallback
        if ($method === 'HEAD' && isset($this->staticRoutes['GET'][$normalizedPath])) {
            return new MatchedRoute($this->staticRoutes['GET'][$normalizedPath], []);
        }

        // 3) Dynamic routes
        $matched = null;

        if ($method === 'HEAD') {
            // Se não há nenhuma rota dinâmica HEAD, evita scan inútil
            if (!empty($this->dynamicRoutes['HEAD'])) {
                $matched = $this->findRoute('HEAD', $normalizedPath);
            }

            if ($matched === null) {
                $matched = $this->findRoute('GET', $normalizedPath);
            }
        } else {
            $matched = $this->findRoute($method, $normalizedPath);
        }

        if ($matched !== null) {
            return $matched;
        }

        // 4) 405 / 404
        $allowed = $this->allowedMethodsForPath($normalizedPath);

        if ($allowed !== []) {
            $exception = HttpException::methodNotAllowed(
                'Method not allowed.',
                ['allowed' => $allowed],
            );

            $exception->withHeaders([
                'Allow' => implode(', ', $allowed),
            ]);

            throw $exception;
        }

        throw HttpException::notFound('Route not found', [
            'method' => $method,
            'path'   => $normalizedPath,
        ]);
    }

    /**
     * Get all registered routes.
     *
     * @return array<int, RouteDefinition>
     */
    public function all(): array
    {
        return $this->routes;
    }

    /**
     * Find a route by its name.
     */
    public function findRouteByName(string $name): ?RouteDefinition
    {
        foreach ($this->routes as $route) {
            if ($route->getName() === $name) {
                return $route;
            }
        }

        return null;
    }

    /**
     * Get the URL generator instance.
     */
    public function url(): RouteUrlGenerator
    {
        if ($this->urlGenerator === null) {
            $this->urlGenerator = new RouteUrlGenerator($this);
        }

        return $this->urlGenerator;
    }

    /* -------------------------------------------------------------------------
     * Export / Import (cache)
     * ---------------------------------------------------------------------- */

    /**
     * Export all cacheable route definitions.
     *
     * @return array<int, array<string, mixed>>
     */
    public function exportDefinitions(): array
    {
        $exported = [];

        foreach ($this->routes as $route) {
            $encoded = $route->toArray();

            if ($encoded !== null) {
                $exported[] = $encoded;
            }
        }

        return $exported;
    }

    /**
     * Load routes from cached definitions.
     *
     * @param array<int, array<string, mixed>> $definitions
     */
    public function loadFromDefinitions(array $definitions): void
    {
        $this->routes        = [];
        $this->staticRoutes  = [];
        $this->dynamicRoutes = [];

        foreach ($definitions as $definition) {
            $route = RouteDefinition::fromArray($definition);
            $this->routes[] = $route;

            $m     = $route->method();
            $rPath = $route->path();

            if ($this->isStaticPath($rPath)) {
                if (!isset($this->staticRoutes[$m])) {
                    $this->staticRoutes[$m] = [];
                }
                $this->staticRoutes[$m][$rPath] = $route;
            } else {
                $segmentCount = $this->getSegmentCount($rPath);

                if (!isset($this->dynamicRoutes[$m][$segmentCount])) {
                    $this->dynamicRoutes[$m][$segmentCount] = [];
                }

                $literalKey = $this->literalPrefixKeyFromPath($rPath);

                $patterned = preg_match('/\{[\w-]+:(?:.*[+*].*|.*\/.*)\}/', $rPath) === 1;

                if ($patterned) {
                    if (!isset($this->dynamicRoutes[$m]['splat'])) {
                        $this->dynamicRoutes[$m]['splat'] = [];
                    }

                    $this->dynamicRoutes[$m]['splat'][] = $route;
                } else {
                    if (!isset($this->dynamicRoutes[$m][$segmentCount][$literalKey])) {
                        $this->dynamicRoutes[$m][$segmentCount][$literalKey] = [];
                    }

                    $this->dynamicRoutes[$m][$segmentCount][$literalKey][] = $route;
                }
            }
        }
    }

    /* -------------------------------------------------------------------------
     * Internals
     * ---------------------------------------------------------------------- */

    /**
     * Find a route matching the given method and path.
     *
     * O(Dₘₛ): apenas rotas daquele método + número de segmentos.
     */
    private function findRoute(string $method, string $path): ?MatchedRoute
    {
        if (empty($this->dynamicRoutes[$method])) {
            return null;
        }

        $segmentCount = $this->getSegmentCount($path);

        $buckets = $this->dynamicRoutes[$method][$segmentCount] ?? [];

        $reqKey = $this->literalPrefixKeyFromPath($path);

        // 1) Try exact prefix bucket
        if (isset($buckets[$reqKey])) {
            foreach ($buckets[$reqKey] as $route) {
                $params = $route->matches($method, $path);
                if ($params !== null) {
                    return new MatchedRoute($route, $params);
                }
            }
        }

        // 2) Try other buckets whose literal-key matches the request's parts
        $reqParts = explode('|', $reqKey);

        foreach ($buckets as $bucketKey => $list) {
            if ($bucketKey === $reqKey) {
                continue;
            }

            // quick pre-filter: check bucket parts against request parts; bucket '_' acts as wildcard
            $bucketParts = explode('|', $bucketKey);
            $mismatch = false;

            for ($i = 0, $n = max(count($bucketParts), count($reqParts)); $i < $n; $i++) {
                $bp = $bucketParts[$i] ?? '_';
                $rp = $reqParts[$i] ?? '';

                if ($bp !== '_' && $bp !== $rp) {
                    $mismatch = true;
                    break;
                }
            }

            if ($mismatch) {
                continue;
            }

            foreach ($list as $route) {
                $params = $route->matches($method, $path);
                if ($params !== null) {
                    return new MatchedRoute($route, $params);
                }
            }
        }

        // 3) As a second chance, try any splat (variable-length) routes
        if (isset($this->dynamicRoutes[$method]['splat'])) {
            foreach ($this->dynamicRoutes[$method]['splat'] as $route) {
                $params = $route->matches($method, $path);
                if ($params !== null) {
                    return new MatchedRoute($route, $params);
                }
            }
        }

        return null;
    }

    /**
     * Determine whether a route path is static (no parameter placeholders).
     */
    private function isStaticPath(string $path): bool
    {
        // Placeholder: {name} ou {name:pattern}
        return preg_match('/\{[\w-]+(?::[^}]+)?\}/', $path) === 0;
    }

    /**
     * Builds a small literal-prefix key from the path using the first
     * $this->literalPrefixSize segments. Placeholder segments become '_'.
     * Example: '/dynamic/route/2/{id}' => 'dynamic|route|2'
     */
    private function literalPrefixKeyFromPath(string $path): string
    {
        $p = RouteDefinition::normalizePath($path);

        if ($p === '/' || $p === '') {
            return implode('|', array_fill(0, $this->literalPrefixSize, '_'));
        }

        $parts = explode('/', ltrim($p, '/'));

        $out = [];
        for ($i = 0; $i < $this->literalPrefixSize; $i++) {
            $seg = $parts[$i] ?? '';

            if ($seg === '' || preg_match('/\{[\w-]+(?::[^}]+)?\}/', $seg)) {
                $out[] = '_';
            } else {
                $out[] = $seg;
            }
        }

        return implode('|', $out);
    }

    /**
     * Compute number of path segments for a normalized path.
     *
     * "/"            -> 1
     * "/users"       -> 1
     * "/users/123"   -> 2
     * "/posts/a/b"   -> 3
     */
    private function getSegmentCount(string $path): int
    {
        $trimmed = trim($path, '/');

        if ($trimmed === '') {
            return 1;
        }

        return substr_count($trimmed, '/') + 1;
    }

    /**
     * Get allowed HTTP methods for a given path.
     *
     * 405 não é hot-path, então mantemos simples.
     *
     * @return array<int,string>
     */
    private function allowedMethodsForPath(string $path): array
    {
        $allowed = [];

        foreach ($this->routes as $route) {
            if ($route->matchesPath($path)) {
                $allowed[] = $route->method();
            }
        }

        return array_values(array_unique($allowed));
    }
}
