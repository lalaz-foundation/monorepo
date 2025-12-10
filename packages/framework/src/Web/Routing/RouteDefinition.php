<?php

declare(strict_types=1);

namespace Lalaz\Web\Routing;

use Lalaz\Exceptions\RoutingException;

/**
 * Represents a single registered route definition.
 *
 * This class encapsulates all information about a route including its
 * HTTP method, URL pattern, handler, middlewares, and optional name.
 * It handles pattern compilation for URL matching and parameter extraction.
 *
 * Example:
 * ```php
 * // Creating a route definition
 * $route = new RouteDefinition(
 *     'GET',
 *     '/users/{id}/posts/{postId}',
 *     [UserController::class, 'showPost'],
 *     [AuthMiddleware::class]
 * );
 *
 * // Matching against a URL
 * $params = $route->matches('GET', '/users/123/posts/456');
 * // Returns: ['id' => '123', 'postId' => '456']
 * ```
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
final class RouteDefinition
{
    /**
     * The HTTP method (uppercase).
     *
     * @var string
     */
    private string $method;

    /**
     * The normalized URL path pattern.
     *
     * @var string
     */
    private string $path;

    /**
     * The compiled regex pattern for matching.
     *
     * @var string
     */
    private string $regex;

    /**
     * Parameter names extracted from the path pattern.
     *
     * @var array<int, string>
     */
    private array $parameterNames = [];

    /**
     * Optional route name for URL generation.
     *
     * @var string|null
     */
    private ?string $name = null;

    /**
     * Creates a new route definition.
     *
     * @param string                             $method      The HTTP method
     * @param string                             $path        The URL path pattern
     * @param callable|array<mixed>|string       $handler     The route handler
     * @param array<int, callable|string|object> $middlewares Route middlewares
     */
    public function __construct(
        string $method,
        string $path,
        private $handler,
        private array $middlewares = [],
    ) {
        $this->method = strtoupper($method);
        $this->path = self::normalizePath($path);
        $this->compilePattern();
    }

    /**
     * Gets the HTTP method.
     *
     * @return string The HTTP method (uppercase)
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * Gets the URL path pattern.
     *
     * @return string The normalized path pattern
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * Gets the route handler.
     *
     * @return callable|array<mixed>|string The handler
     */
    public function handler(): callable|array|string
    {
        return $this->handler;
    }

    /**
     * Gets the route middlewares.
     *
     * @return array<int, callable|string|object> The middlewares
     */
    public function middlewares(): array
    {
        return $this->middlewares;
    }

    /**
     * Adds middlewares to this route.
     *
     * Used by RouteGroup to apply group middlewares to individual routes.
     *
     * @param array<int, callable|string|object> $middlewares Middlewares to add
     *
     * @return self For method chaining
     */
    public function addMiddlewares(array $middlewares): self
    {
        $this->middlewares = array_merge($this->middlewares, $middlewares);
        return $this;
    }

    /**
     * Sets the route name.
     *
     * Route names are used for URL generation via the router's url() method.
     *
     * @param string $name The route name (e.g., 'users.show', 'api.posts.index')
     *
     * @return self For method chaining
     */
    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Alias for name() - Laravel-style naming.
     *
     * @param string $name The route name
     *
     * @return self For method chaining
     */
    public function as(string $name): self
    {
        return $this->name($name);
    }

    /**
     * Gets the route name.
     *
     * @return string|null The route name or null if not set
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Gets the parameter names extracted from the path pattern.
     *
     * @return array<int, string> Array of parameter names
     */
    public function getParameterNames(): array
    {
        return $this->parameterNames;
    }

    /**
     * Checks if the route matches the given method and path.
     *
     * Returns extracted parameters if matched, null otherwise.
     *
     * @param string $method The HTTP method to match
     * @param string $path   The URL path to match
     *
     * @return array<string, string>|null Parameters or null if no match
     */
    public function matches(string $method, string $path): ?array
    {
        if ($this->method !== strtoupper($method)) {
            return null;
        }

        if (!preg_match($this->regex, $path, $matches)) {
            return null;
        }

        return $this->extractParams($matches);
    }

    /**
     * Checks if the route matches the given path (ignoring method).
     *
     * Used to detect routes that match a path but not the HTTP method,
     * enabling proper 405 Method Not Allowed responses.
     *
     * @param string $path The URL path to match
     *
     * @return bool True if path matches
     */
    public function matchesPath(string $path): bool
    {
        return (bool) preg_match($this->regex, $path);
    }

    /**
     * Normalizes a path for consistent matching.
     *
     * Ensures paths start with / and don't end with / (except for root).
     *
     * @param string $path The path to normalize
     *
     * @return string The normalized path
     */
    public static function normalizePath(string $path): string
    {
        if ($path === '' || $path === '/') {
            return '/';
        }

        $normalized = '/' . ltrim($path, '/');
        $normalized = rtrim($normalized, '/');

        return $normalized === '' ? '/' : $normalized;
    }

    /**
     * Compiles the path pattern into a regex.
     *
     * Handles parameter placeholders like {id} and {id:[0-9]+} with
     * custom regex constraints.
     *
     * @return void
     */
    private function compilePattern(): void
    {
        $pattern = '';
        $path = $this->path;
        $offset = 0;
        $this->parameterNames = [];

        while (
            preg_match(
                "/\{([\w-]+)(?::([^}]+))?\}/",
                $path,
                $matches,
                PREG_OFFSET_CAPTURE,
                $offset,
            )
        ) {
            $fullMatch = $matches[0];
            $start = (int) $fullMatch[1];
            $length = strlen($fullMatch[0]);

            $staticPart = substr($path, $offset, $start - $offset);
            $pattern .= preg_quote($staticPart, '#');

            $paramName = $matches[1][0];
            $paramPattern = $matches[2][0] ?? '[^/]+';

            $pattern .= '(?P<' . $paramName . '>' . $paramPattern . ')';
            $this->parameterNames[] = $paramName;

            $offset = $start + $length;
        }

        if ($offset < strlen($path)) {
            $pattern .= preg_quote(substr($path, $offset), '#');
        }

        $this->regex = '#^' . $pattern . '$#';
    }

    /**
     * Extracts named parameters from regex matches.
     *
     * @param array<string, string> $matches Regex match results
     *
     * @return array<string, string> Extracted parameters
     */
    private function extractParams(array $matches): array
    {
        $params = [];

        foreach ($this->parameterNames as $name) {
            if (array_key_exists($name, $matches)) {
                $params[$name] = $matches[$name];
            }
        }

        return $params;
    }

    /**
     * Checks if the route can be cached.
     *
     * Routes with closure handlers or object middlewares cannot be cached.
     *
     * @return bool True if cacheable
     */
    public function isCacheable(): bool
    {
        return $this->describeHandler() !== null &&
            $this->middlewaresCacheable();
    }

    /**
     * Serializes the route for caching.
     *
     * @return array<string, mixed>|null Serialized data or null if not cacheable
     */
    public function toArray(): ?array
    {
        $handler = $this->describeHandler();

        if ($handler === null) {
            return null;
        }

        return [
            'method' => $this->method,
            'path' => $this->path,
            'handler' => $handler,
            'middlewares' => $this->serializeMiddlewares(),
            'name' => $this->name,
        ];
    }

    /**
     * Creates a route definition from cached data.
     *
     * @param array<string, mixed> $payload The cached data
     *
     * @return self The hydrated route definition
     *
     * @throws RoutingException If the cached data is invalid
     */
    public static function fromArray(array $payload): self
    {
        $handler = self::hydrateHandler($payload['handler'] ?? null);
        $middlewares = self::hydrateMiddlewares($payload['middlewares'] ?? []);

        $route = new self(
            $payload['method'],
            $payload['path'],
            $handler,
            $middlewares,
        );

        if (isset($payload['name'])) {
            $route->name($payload['name']);
        }

        return $route;
    }

    /**
     * Describes the handler for serialization.
     *
     * @return array<string, mixed>|null Handler description or null if not serializable
     */
    private function describeHandler(): ?array
    {
        if (is_string($this->handler)) {
            return ['type' => 'string', 'value' => $this->handler];
        }

        if (
            is_array($this->handler) &&
            count($this->handler) === 2 &&
            is_string($this->handler[0]) &&
            is_string($this->handler[1])
        ) {
            return [
                'type' => 'class_method',
                'class' => $this->handler[0],
                'method' => $this->handler[1],
            ];
        }

        return null;
    }

    /**
     * Hydrates a handler from its serialized description.
     *
     * @param array<string, mixed>|null $description The handler description
     *
     * @return string|array<mixed> The hydrated handler
     *
     * @throws RoutingException If the description is invalid
     */
    private static function hydrateHandler(?array $description): string|array
    {
        if ($description === null) {
            throw new RoutingException(
                'Invalid route cache entry',
                ['reason' => 'Handler description is null'],
            );
        }

        if (
            ($description['type'] ?? null) === 'string' &&
            is_string($description['value'] ?? null)
        ) {
            return $description['value'];
        }

        if (
            ($description['type'] ?? null) === 'class_method' &&
            is_string($description['class'] ?? null) &&
            is_string($description['method'] ?? null)
        ) {
            return [$description['class'], $description['method']];
        }

        throw new RoutingException(
            'Unsupported cached handler type',
            ['handler' => $description],
        );
    }

    /**
     * Serializes middlewares for caching.
     *
     * Only string middlewares (class names) can be serialized.
     *
     * @return array<int, string> Serialized middlewares
     */
    private function serializeMiddlewares(): array
    {
        $serializable = [];

        foreach ($this->middlewares as $middleware) {
            if (is_string($middleware)) {
                $serializable[] = $middleware;
            }
        }

        return $serializable;
    }

    /**
     * Hydrates middlewares from cached data.
     *
     * @param array<int, mixed> $middlewares The cached middleware data
     *
     * @return array<int, string> The hydrated middlewares
     */
    private static function hydrateMiddlewares(array $middlewares): array
    {
        return array_values(
            array_filter(
                $middlewares,
                static fn ($middleware) => is_string($middleware),
            ),
        );
    }

    /**
     * Checks if all middlewares can be cached.
     *
     * @return bool True if all middlewares are strings
     */
    private function middlewaresCacheable(): bool
    {
        foreach ($this->middlewares as $middleware) {
            if (!is_string($middleware)) {
                return false;
            }
        }

        return true;
    }
}
