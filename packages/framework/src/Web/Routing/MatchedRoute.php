<?php

declare(strict_types=1);

namespace Lalaz\Web\Routing;

/**
 * Represents a matched route with extracted parameters.
 *
 * This class is returned by the router when a request matches a route.
 * It provides access to the route definition and any parameters extracted
 * from the URL pattern.
 *
 * Example:
 * ```php
 * // For route: /users/{id}/posts/{postId}
 * // Matching URL: /users/123/posts/456
 *
 * $matched = $router->match($request);
 * $matched->params();  // ['id' => '123', 'postId' => '456']
 * $matched->handler(); // The route's handler
 * ```
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
final class MatchedRoute
{
    /**
     * Creates a new matched route instance.
     *
     * @param RouteDefinition       $definition The matched route definition
     * @param array<string, string> $params     Parameters extracted from the URL
     */
    public function __construct(
        private RouteDefinition $definition,
        private array $params,
    ) {
    }

    /**
     * Gets the route definition.
     *
     * @return RouteDefinition The matched route definition
     */
    public function definition(): RouteDefinition
    {
        return $this->definition;
    }

    /**
     * Gets the extracted URL parameters.
     *
     * Parameters are extracted from dynamic segments in the route pattern.
     * For example, the pattern "/users/{id}" matching "/users/123"
     * would return ['id' => '123'].
     *
     * @return array<string, string> Parameter name => value pairs
     */
    public function params(): array
    {
        return $this->params;
    }

    /**
     * Gets the route handler.
     *
     * The handler can be a callable, an array [Controller, method],
     * or a string class name.
     *
     * @return callable|array<mixed>|string The route handler
     */
    public function handler(): callable|array|string
    {
        return $this->definition->handler();
    }

    /**
     * Gets the route middlewares.
     *
     * @return array<int, callable|string|object> Array of middleware
     */
    public function middlewares(): array
    {
        return $this->definition->middlewares();
    }
}
