<?php

declare(strict_types=1);

namespace Lalaz\Auth\Middlewares;

use Lalaz\Auth\AuthContext;
use Lalaz\Exceptions\HttpException;
use Lalaz\Web\Http\Contracts\MiddlewareInterface;
use Lalaz\Web\Http\Contracts\RequestInterface;
use Lalaz\Web\Http\Contracts\ResponseInterface;

/**
 * Authorization Middleware
 *
 * Checks if the current user has at least one of the required roles.
 * Supports multiple guards for different authentication contexts.
 *
 * Usage:
 * ```php
 * // Basic role check (uses current guard)
 * AuthorizationMiddleware::requireRoles('admin', 'moderator');
 *
 * // Role check for specific guard
 * AuthorizationMiddleware::requireRoles('admin')->forGuard('api');
 *
 * // Factory methods
 * AuthorizationMiddleware::admin();
 * AuthorizationMiddleware::moderator();
 *
 * // With explicit dependency (DI)
 * new AuthorizationMiddleware(
 *     requiredRoles: ['admin'],
 *     authContext: $authContext
 * );
 * ```
 *
 * @package Lalaz\Auth\Middlewares
 */
class AuthorizationMiddleware implements MiddlewareInterface
{
    /**
     * Required roles (user must have at least one).
     *
     * @var array<string>
     */
    protected array $requiredRoles;

    /**
     * The guard to check authentication against.
     *
     * @var string|null
     */
    protected ?string $guard = null;

    /**
     * The auth context instance (injected or resolved).
     *
     * @var AuthContext|null
     */
    protected ?AuthContext $authContext;

    /**
     * Create a new authorization middleware instance.
     *
     * @param array<string> $requiredRoles Roles the user must have (any).
     * @param string|null $guard The guard to use (null = current guard).
     * @param AuthContext|null $authContext The auth context (null = resolve from container).
     */
    public function __construct(
        array $requiredRoles = [],
        ?string $guard = null,
        ?AuthContext $authContext = null,
    ) {
        $this->requiredRoles = $requiredRoles;
        $this->guard = $guard;
        $this->authContext = $authContext;
    }

    /**
     * Handle an incoming request.
     *
     * @param RequestInterface $req The HTTP request.
     * @param ResponseInterface $res The HTTP response.
     * @param callable $next The next middleware in the chain.
     * @return mixed
     * @throws HttpException When user lacks required roles.
     */
    public function handle(RequestInterface $req, ResponseInterface $res, callable $next): mixed
    {
        // Skip if no roles required
        if (empty($this->requiredRoles)) {
            return $next($req, $res);
        }

        $user = $this->getUser($req);

        if (!$this->hasRequiredRole($user)) {
            throw HttpException::forbidden('Insufficient permissions', [
                'required_roles' => $this->requiredRoles,
                'guard' => $this->guard,
            ]);
        }

        return $next($req, $res);
    }

    /**
     * Set the guard to use for authentication.
     *
     * @param string $guard The guard name.
     * @return self
     */
    public function forGuard(string $guard): self
    {
        $this->guard = $guard;
        return $this;
    }

    /**
     * Get the authenticated user.
     *
     * @param RequestInterface $req The request.
     * @return mixed The user or null.
     */
    private function getUser(RequestInterface $req): mixed
    {
        $auth = $this->getAuthContext();

        if ($auth) {
            // Use specific guard or current guard
            if ($this->guard !== null) {
                return $auth->user($this->guard);
            }

            if ($auth->isAuthenticated()) {
                return $auth->user();
            }
        }

        // Fallback to request user property
        return $req->user ?? null;
    }

    /**
     * Check if user has any of the required roles.
     *
     * @param mixed $user The user.
     * @return bool True if user has required role.
     */
    private function hasRequiredRole(mixed $user): bool
    {
        if ($user === null) {
            return false;
        }

        if (method_exists($user, 'hasAnyRole')) {
            return $user->hasAnyRole($this->requiredRoles);
        }

        // Try individual role checks
        if (method_exists($user, 'hasRole')) {
            foreach ($this->requiredRoles as $role) {
                if ($user->hasRole($role)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get auth context (injected or from container).
     *
     * @return AuthContext|null
     */
    private function getAuthContext(): ?AuthContext
    {
        if ($this->authContext !== null) {
            return $this->authContext;
        }

        if (function_exists('resolve')) {
            try {
                return resolve(AuthContext::class);
            } catch (\Throwable) {
                // Ignore
            }
        }

        return null;
    }

    /**
     * Set the auth context instance.
     *
     * @param AuthContext $authContext The auth context.
     * @return self
     */
    public function setAuthContext(AuthContext $authContext): self
    {
        $this->authContext = $authContext;
        return $this;
    }

    // ===== Factory Methods =====

    /**
     * Create middleware requiring specific roles.
     *
     * @param string ...$roles The required roles.
     * @return self
     */
    public static function requireRoles(string ...$roles): self
    {
        return new self($roles);
    }

    /**
     * Create middleware requiring admin role.
     *
     * @return self
     */
    public static function admin(): self
    {
        return new self(['admin']);
    }

    /**
     * Create middleware requiring moderator or admin role.
     *
     * @return self
     */
    public static function moderator(): self
    {
        return new self(['admin', 'moderator']);
    }

    /**
     * Create middleware for specific guard requiring roles.
     *
     * @param string $guard The guard name.
     * @param string ...$roles The required roles.
     * @return self
     */
    public static function guard(string $guard, string ...$roles): self
    {
        return new self($roles, $guard);
    }
}
