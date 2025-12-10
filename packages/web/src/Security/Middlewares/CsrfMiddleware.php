<?php

declare(strict_types=1);

namespace Lalaz\Web\Security\Middlewares;

use Lalaz\Exceptions\HttpException;
use Lalaz\Web\Http\Contracts\MiddlewareInterface;
use Lalaz\Web\Http\Contracts\RequestInterface;
use Lalaz\Web\Http\Contracts\ResponseInterface;
use Lalaz\Web\Security\CsrfProtection;

/**
 * CSRF Protection Middleware
 *
 * Validates CSRF tokens for state-changing HTTP methods (POST, PUT, PATCH, DELETE).
 * Supports pattern-based exclusions for API routes, webhooks, and other exceptions.
 *
 * @package Lalaz\Web\Security\Middlewares
 */
class CsrfMiddleware implements MiddlewareInterface
{
    /**
     * Route patterns to exclude from CSRF validation.
     *
     * @var array<string>
     */
    private array $except;

    /**
     * HTTP methods that require CSRF validation.
     *
     * @var array<string>
     */
    private const PROTECTED_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * Create a new CSRF middleware instance.
     *
     * @param array<string> $except Route patterns to exclude (supports wildcards)
     *                              Example: ['/api/*', '/webhook/*', '/oauth/callback']
     */
    public function __construct(array $except = [])
    {
        $this->except = $except;
    }

    /**
     * Handle an incoming request.
     *
     * @param RequestInterface $req The HTTP request.
     * @param ResponseInterface $res The HTTP response.
     * @param callable $next The next middleware in the chain.
     * @return mixed
     * @throws HttpException If CSRF validation fails.
     */
    public function handle(RequestInterface $req, ResponseInterface $res, callable $next): mixed
    {
        // Skip CSRF validation if route is excluded
        if ($this->shouldSkip($req)) {
            return $next($req, $res);
        }

        // Only validate CSRF for state-changing methods
        if (in_array($req->method(), self::PROTECTED_METHODS, true)) {
            $this->validateCsrf($req);
        }

        return $next($req, $res);
    }

    /**
     * Determine if CSRF validation should be skipped for this request.
     *
     * @param RequestInterface $req The HTTP request.
     * @return bool True if validation should be skipped.
     */
    private function shouldSkip(RequestInterface $req): bool
    {
        $path = $req->path();

        foreach ($this->except as $pattern) {
            if ($this->matchesPattern($path, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a path matches a pattern (supports wildcards).
     *
     * @param string $path The request path.
     * @param string $pattern The pattern to match against.
     * @return bool True if the path matches the pattern.
     */
    private function matchesPattern(string $path, string $pattern): bool
    {
        // Exact match
        if ($path === $pattern) {
            return true;
        }

        // Wildcard match: /api/* matches /api/users, /api/posts, etc.
        if (str_ends_with($pattern, '/*')) {
            $prefix = substr($pattern, 0, -2);
            return str_starts_with($path, $prefix);
        }

        // Wildcard match: /api/*/callback matches /api/123/callback
        if (str_contains($pattern, '*')) {
            $regex = str_replace(
                ['/', '*'],
                ['\/', '.*'],
                $pattern
            );
            return preg_match('/^' . $regex . '$/', $path) === 1;
        }

        return false;
    }

    /**
     * Validate the CSRF token for the request.
     *
     * @param RequestInterface $req The HTTP request.
     * @return void
     * @throws HttpException If CSRF validation fails.
     */
    private function validateCsrf(RequestInterface $req): void
    {
        $headers = $req->headers();
        $body = $this->getBodyForCsrf($req);

        if (!CsrfProtection::validateToken($body, $headers)) {
            throw HttpException::csrfMismatch('Invalid CSRF token', [
                'ip' => $req->ip(),
                'user_agent' => $req->userAgent(),
                'path' => $req->path(),
                'method' => $req->method(),
            ]);
        }

        CsrfProtection::rotateToken();
    }

    /**
     * Get the request body in a format suitable for CSRF validation.
     *
     * @param RequestInterface $req The HTTP request.
     * @return array|object|null The request body.
     */
    private function getBodyForCsrf(RequestInterface $req): array|object|null
    {
        if ($req->isJson()) {
            $decoded = $req->json();

            if (is_array($decoded) || is_object($decoded)) {
                return $decoded;
            }

            return null;
        }

        return $req->body();
    }

    /**
     * Create middleware with common API route exclusions.
     *
     * @return self
     */
    public static function excludingApi(): self
    {
        return new self(['/api/*']);
    }

    /**
     * Create middleware with webhook exclusions.
     *
     * @param array<string> $webhookPaths Additional webhook paths to exclude.
     * @return self
     */
    public static function excludingWebhooks(array $webhookPaths = []): self
    {
        $defaults = ['/webhook/*', '/webhooks/*'];
        return new self(array_merge($defaults, $webhookPaths));
    }
}
