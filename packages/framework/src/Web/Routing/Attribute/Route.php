<?php

declare(strict_types=1);

namespace Lalaz\Web\Routing\Attribute;

use Attribute;

/**
 * Route attribute for defining HTTP routes on controller methods.
 *
 * This PHP 8 attribute allows declaring routes directly on controller
 * methods, enabling automatic route discovery and registration.
 *
 * Example usage:
 * ```php
 * class UserController
 * {
 *     #[Route('/users', method: 'GET')]
 *     public function index(): Response { }
 *
 *     #[Route('/users/{id}', methods: ['GET', 'HEAD'])]
 *     public function show(int $id): Response { }
 *
 *     #[Route('/users', method: 'POST', middlewares: ['auth', 'throttle'])]
 *     public function store(): Response { }
 * }
 * ```
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Route
{
    /**
     * Creates a new Route attribute.
     *
     * @param string                      $path        The URL path pattern (e.g., '/users/{id}')
     * @param string                      $method      Default HTTP method when $methods not provided
     * @param array<int, string>          $methods     Explicit HTTP methods for this route
     * @param array<int, callable|string> $middlewares Middleware to apply to this route
     */
    public function __construct(
        private string $path,
        private string $method = 'GET',
        private array $methods = [],
        private array $middlewares = [],
    ) {
    }

    /**
     * Gets the URL path pattern.
     *
     * @return string The route path
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Gets the HTTP methods for this route.
     *
     * If explicit methods were provided, returns those.
     * Otherwise, returns the single default method.
     *
     * @return array<int, string> Uppercase HTTP method names
     */
    public function getMethods(): array
    {
        if ($this->methods !== []) {
            return array_map(
                static fn (string $method) => strtoupper($method),
                $this->methods,
            );
        }

        return [strtoupper($this->method)];
    }

    /**
     * Gets the middleware for this route.
     *
     * @return array<int, callable|string> Middleware callables or class names
     */
    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }
}
