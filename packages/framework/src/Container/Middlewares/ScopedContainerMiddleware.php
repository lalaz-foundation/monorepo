<?php

declare(strict_types=1);

namespace Lalaz\Container\Middlewares;

use Lalaz\Container\Container;
use Lalaz\Web\Http\Contracts\MiddlewareInterface;
use Lalaz\Web\Http\Contracts\RequestInterface;
use Lalaz\Web\Http\Contracts\ResponseInterface;

/**
 * HTTP middleware for managing container scopes per request.
 *
 * This middleware ensures that a new container scope is started at the
 * beginning of each HTTP request and properly ended when the request
 * completes (or fails). This enables request-scoped services to be
 * resolved uniquely per request.
 *
 * Typically registered as the first middleware in the stack to ensure
 * all subsequent middleware and request handling has access to
 * request-scoped services.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
class ScopedContainerMiddleware implements MiddlewareInterface
{
    /**
     * Create a new scoped container middleware.
     *
     * @param Container $container The DI container instance.
     */
    public function __construct(protected Container $container)
    {
    }

    /**
     * Handle the request within a container scope.
     *
     * Begins a new scope before passing the request to the next
     * middleware, and ensures the scope is ended after processing
     * completes (even if an exception is thrown).
     *
     * @param RequestInterface $req The incoming HTTP request.
     * @param ResponseInterface $res The HTTP response.
     * @param callable $next The next middleware in the chain.
     * @return mixed The result from the next middleware/handler.
     */
    public function handle(RequestInterface $req, ResponseInterface $res, callable $next): mixed
    {
        $this->container->beginScope();

        try {
            return $next($req, $res);
        } finally {
            $this->container->endScope();
        }
    }
}
