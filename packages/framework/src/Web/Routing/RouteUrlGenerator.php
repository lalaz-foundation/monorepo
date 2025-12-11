<?php

declare(strict_types=1);

namespace Lalaz\Web\Routing;

use Lalaz\Config\Contracts\ConfigRepositoryInterface;
use Lalaz\Exceptions\RoutingException;
use Lalaz\Web\Routing\Contracts\RouterInterface;

/**
 * Generates URLs for named routes.
 *
 * Provides a clean API to generate URLs from route names with parameter substitution.
 * Supports both relative and absolute URL generation, with automatic query string
 * handling for extra parameters.
 *
 * Example usage:
 * ```php
 * // First, define named routes
 * $router->get('/users', [UserController::class, 'index'])->name('users.index');
 * $router->get('/users/{id}', [UserController::class, 'show'])->name('users.show');
 *
 * // Then generate URLs
 * $generator = $router->url();
 *
 * // Relative URL
 * $url = $generator->route('users.show', ['id' => 42]);
 * // Returns: /users/42
 *
 * // Absolute URL
 * $url = $generator->route('users.show', ['id' => 42], absolute: true);
 * // Returns: https://example.com/users/42
 *
 * // With query parameters (extra params become query string)
 * $url = $generator->route('users.index', ['page' => 2, 'sort' => 'name']);
 * // Returns: /users?page=2&sort=name
 * ```
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
class RouteUrlGenerator
{
    /**
     * Named routes cache for fast lookup.
     *
     * @var array<string, RouteDefinition>
     */
    private array $namedRoutes = [];

    /**
     * Whether the named routes have been indexed.
     *
     * @var bool
     */
    private bool $indexed = false;

    /**
     * Create a new URL generator.
     *
     * @param RouterInterface $router The router containing route definitions
     * @param ConfigRepositoryInterface|null $config Optional config for base URL
     */
    public function __construct(
        private RouterInterface $router,
        private ?ConfigRepositoryInterface $config = null,
    ) {
    }

    /**
     * Generate a URL for the named route.
     *
     * @param string $name Route name (e.g., 'users.show')
     * @param array<string, mixed> $parameters Route parameters and query string values
     * @param bool $absolute Whether to generate an absolute URL
     * @return string The generated URL
     *
     * @throws RoutingException When route is not found or required parameters are missing
     */
    public function route(
        string $name,
        array $parameters = [],
        bool $absolute = false,
    ): string {
        $route = $this->findRoute($name);

        if ($route === null) {
            throw RoutingException::routeNotFound($name, $this->getAvailableRoutes());
        }

        return $this->buildUrl($route, $parameters, $absolute);
    }

    /**
     * Generate a URL for the named route, returning null if not found.
     *
     * @param string $name Route name
     * @param array<string, mixed> $parameters Route parameters
     * @param bool $absolute Whether to generate an absolute URL
     * @return string|null The generated URL or null if route not found
     */
    public function routeOrNull(
        string $name,
        array $parameters = [],
        bool $absolute = false,
    ): ?string {
        $route = $this->findRoute($name);

        if ($route === null) {
            return null;
        }

        try {
            return $this->buildUrl($route, $parameters, $absolute);
        } catch (RoutingException) {
            return null;
        }
    }

    /**
     * Check if a named route exists.
     *
     * @param string $name Route name
     * @return bool
     */
    public function has(string $name): bool
    {
        return $this->findRoute($name) !== null;
    }

    /**
     * Get all named routes.
     *
     * @return array<string, RouteDefinition>
     */
    public function getNamedRoutes(): array
    {
        $this->indexRoutes();
        return $this->namedRoutes;
    }

    /**
     * Get available route names for error messages.
     *
     * @return array<int, string>
     */
    public function getAvailableRoutes(): array
    {
        $this->indexRoutes();
        return array_keys($this->namedRoutes);
    }

    /**
     * Clear the named routes cache (useful after adding new routes).
     *
     * @return void
     */
    public function refresh(): void
    {
        $this->namedRoutes = [];
        $this->indexed = false;
    }

    /**
     * Find a route by name.
     *
     * @param string $name Route name
     * @return RouteDefinition|null
     */
    private function findRoute(string $name): ?RouteDefinition
    {
        $this->indexRoutes();
        return $this->namedRoutes[$name] ?? null;
    }

    /**
     * Index all routes by name for fast lookup.
     *
     * @return void
     */
    private function indexRoutes(): void
    {
        if ($this->indexed) {
            return;
        }

        foreach ($this->router->all() as $route) {
            $name = $route->getName();

            if ($name !== null && !isset($this->namedRoutes[$name])) {
                $this->namedRoutes[$name] = $route;
            }
        }

        $this->indexed = true;
    }

    /**
     * Build the URL for a route with parameters.
     *
     * @param RouteDefinition $route The route definition
     * @param array<string, mixed> $parameters Route and query parameters
     * @param bool $absolute Whether to generate absolute URL
     * @return string The generated URL
     *
     * @throws RoutingException When required parameters are missing
     */
    private function buildUrl(
        RouteDefinition $route,
        array $parameters,
        bool $absolute,
    ): string {
        $path = $route->path();
        $routeParams = $route->getParameterNames();
        $queryParams = [];

        // Substitute route parameters
        foreach ($routeParams as $param) {
            if (!array_key_exists($param, $parameters)) {
                throw RoutingException::missingRouteParameter(
                    $route->getName() ?? $path,
                    $param,
                );
            }

            $value = $parameters[$param];
            $path = str_replace("{{$param}}", (string) $value, $path);
            unset($parameters[$param]);
        }

        // Remaining parameters become query string
        foreach ($parameters as $key => $value) {
            if ($value !== null) {
                $queryParams[$key] = $value;
            }
        }

        if (!empty($queryParams)) {
            $path .= '?' . http_build_query($queryParams);
        }

        if ($absolute) {
            $path = $this->getBaseUrl() . $path;
        }

        return $path;
    }

    /**
     * Get the base URL for absolute URLs.
     *
     * @return string
     */
    private function getBaseUrl(): string
    {
        if ($this->config !== null) {
            $url = $this->config->get('app.url');

            if ($url !== null) {
                return rtrim((string) $url, '/');
            }
        }

        // Fallback to request-based detection
        $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return "{$scheme}://{$host}";
    }
}
