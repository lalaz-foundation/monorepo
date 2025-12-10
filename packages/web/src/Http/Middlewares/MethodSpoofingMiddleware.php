<?php

declare(strict_types=1);

namespace Lalaz\Web\Http\Middlewares;

use Lalaz\Web\Http\Contracts\MiddlewareInterface;
use Lalaz\Web\Http\Contracts\RequestInterface;
use Lalaz\Web\Http\Contracts\ResponseInterface;

/**
 * Method Spoofing Middleware.
 *
 * Allows HTML forms to use HTTP methods other than GET and POST
 * by including a hidden `_method` field. This enables RESTful
 * routing with standard HTML forms.
 *
 * Supported methods: PUT, PATCH, DELETE
 *
 * Usage in forms:
 * ```html
 * <form method="POST" action="/users/5">
 *     <input type="hidden" name="_method" value="DELETE">
 *     <button>Delete User</button>
 * </form>
 * ```
 *
 * Or using the Twig helper:
 * ```twig
 * <form method="POST" action="/users/5">
 *     {{ methodField('DELETE') | raw }}
 *     <button>Delete User</button>
 * </form>
 * ```
 *
 * @package Lalaz\Web\Http\Middlewares
 */
class MethodSpoofingMiddleware implements MiddlewareInterface
{
    /**
     * The form field name for method spoofing.
     */
    public const FIELD_NAME = '_method';

    /**
     * Allowed spoofed methods.
     *
     * @var array<int, string>
     */
    private const ALLOWED_METHODS = ['PUT', 'PATCH', 'DELETE'];

    /**
     * Handle the request.
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param callable $next
     * @return mixed
     */
    public function handle(RequestInterface $request, ResponseInterface $response, callable $next): mixed
    {
        // Only process POST requests with _method field
        if ($request->method() === 'POST') {
            $spoofedMethod = $this->getSpoofedMethod($request);

            if ($spoofedMethod !== null) {
                $request->setMethod($spoofedMethod);
            }
        }

        return $next($request, $response);
    }

    /**
     * Get the spoofed method from the request.
     *
     * @param RequestInterface $request
     * @return string|null The spoofed method or null if not valid
     */
    private function getSpoofedMethod(RequestInterface $request): ?string
    {
        // Check form body first
        $method = $request->input(self::FIELD_NAME);

        // Also check X-HTTP-Method-Override header
        if ($method === null) {
            $method = $request->header('X-HTTP-Method-Override');
        }

        if ($method === null) {
            return null;
        }

        $method = strtoupper(trim((string) $method));

        if (!in_array($method, self::ALLOWED_METHODS, true)) {
            return null;
        }

        return $method;
    }
}
