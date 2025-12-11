<?php

declare(strict_types=1);

namespace Lalaz\Web\Http\Contracts;

/**
 * Contract for HTTP middleware components.
 *
 * Middleware are layers that wrap around the request/response cycle,
 * allowing you to inspect or modify requests before they reach the
 * route handler, and responses before they are sent to the client.
 *
 * Middleware can optionally return a value from the next handler, which
 * enables the return-based response pattern where controllers can return
 * typed values instead of manipulating the response directly.
 *
 * Example implementation (traditional void return):
 * ```php
 * class AuthMiddleware implements MiddlewareInterface
 * {
 *     public function handle(RequestInterface $req, ResponseInterface $res, callable $next): mixed
 *     {
 *         if (!$req->header('Authorization')) {
 *             $res->status(401)->json(['error' => 'Unauthorized']);
 *             return null;
 *         }
 *
 *         return $next($req, $res);
 *     }
 * }
 * ```
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
interface MiddlewareInterface
{
    /**
     * Handle an incoming HTTP request.
     *
     * Middleware should call `$next($req, $res)` to pass control to the
     * next handler in the chain. The return value from `$next` should be
     * returned to enable the return-based response pattern.
     *
     * @param RequestInterface $req The incoming HTTP request.
     * @param ResponseInterface $res The HTTP response being built.
     * @param callable $next The next middleware/handler in the chain.
     * @return mixed The return value from the handler chain.
     */
    public function handle(RequestInterface $req, ResponseInterface $res, callable $next): mixed;
}
