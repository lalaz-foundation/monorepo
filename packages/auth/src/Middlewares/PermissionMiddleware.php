<?php

declare(strict_types=1);

namespace Lalaz\Auth\Middlewares;

use Lalaz\Auth\AuthContext;
use Lalaz\Exceptions\HttpException;
use Lalaz\Web\Http\Contracts\MiddlewareInterface;
use Lalaz\Web\Http\Contracts\RequestInterface;
use Lalaz\Web\Http\Contracts\ResponseInterface;

/**
 * Permission Middleware
 *
 * Checks if the current user has any/all of the required permissions.
 * Supports multiple guards for different authentication contexts.
 *
 * Usage:
 * ```php
 * // Require any permission (OR)
 * PermissionMiddleware::any('posts.create', 'posts.edit');
 *
 * // Require all permissions (AND)
 * PermissionMiddleware::all('posts.create', 'posts.edit');
 *
 * // With specific guard
 * PermissionMiddleware::any('users.manage')->forGuard('api');
 *
 * // With redirect on failure
 * PermissionMiddleware::redirectOnFailure(['admin.access'], '/unauthorized');
 *
 * // With explicit dependency (DI)
 * new PermissionMiddleware(
 *     permissions: ['posts.create'],
 *     authContext: $authContext
 * );
 * ```
 *
 * @package Lalaz\Auth\Middlewares
 */
class PermissionMiddleware implements MiddlewareInterface
{
    /**
     * Required permissions (user must have at least one / all).
     *
     * @var array<string>
     */
    protected array $requiredPermissions;

    /**
     * URL to redirect to when unauthorized.
     *
     * @var string|null
     */
    protected ?string $redirectUrl;

    /**
     * Whether all permissions are required (AND) vs any (OR).
     *
     * @var bool
     */
    protected bool $requireAll;

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
     * Create a new permission middleware instance.
     *
     * @param array<string> $permissions Permissions to check.
     * @param string|null $redirectUrl URL to redirect (null = throw 403).
     * @param bool $requireAll Require all permissions (true) or any (false).
     * @param string|null $guard The guard to use (null = current guard).
     * @param AuthContext|null $authContext The auth context (null = resolve from container).
     */
    public function __construct(
        array $permissions = [],
        ?string $redirectUrl = null,
        bool $requireAll = false,
        ?string $guard = null,
        ?AuthContext $authContext = null,
    ) {
        $this->requiredPermissions = $permissions;
        $this->redirectUrl = $redirectUrl;
        $this->requireAll = $requireAll;
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
     * @throws HttpException When user lacks required permissions.
     */
    public function handle(RequestInterface $req, ResponseInterface $res, callable $next): mixed
    {
        // Skip if no permissions required
        if (empty($this->requiredPermissions)) {
            return $next($req, $res);
        }

        $user = $this->getUser($req);

        if (!$this->hasRequiredPermissions($user)) {
            if ($this->redirectUrl !== null) {
                $res->redirect($this->redirectUrl);
                return null;
            }

            throw HttpException::forbidden('Insufficient permissions', [
                'required_permissions' => $this->requiredPermissions,
                'require_all' => $this->requireAll,
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

        return $req->user ?? null;
    }

    /**
     * Check if user has required permissions.
     *
     * @param mixed $user The user.
     * @return bool True if user has required permissions.
     */
    private function hasRequiredPermissions(mixed $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($this->requireAll) {
            // User must have ALL permissions
            if (method_exists($user, 'hasAllPermissions')) {
                return $user->hasAllPermissions($this->requiredPermissions);
            }

            if (method_exists($user, 'hasPermission')) {
                foreach ($this->requiredPermissions as $permission) {
                    if (!$user->hasPermission($permission)) {
                        return false;
                    }
                }
                return true;
            }

            return false;
        }

        // User must have ANY permission
        if (method_exists($user, 'hasAnyPermission')) {
            return $user->hasAnyPermission($this->requiredPermissions);
        }

        if (method_exists($user, 'hasPermission')) {
            foreach ($this->requiredPermissions as $permission) {
                if ($user->hasPermission($permission)) {
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
     * Create middleware requiring any of the specified permissions.
     *
     * @param string ...$permissions The permissions.
     * @return self
     */
    public static function any(string ...$permissions): self
    {
        return new self($permissions, null, false);
    }

    /**
     * Create middleware requiring all of the specified permissions.
     *
     * @param string ...$permissions The permissions.
     * @return self
     */
    public static function all(string ...$permissions): self
    {
        return new self($permissions, null, true);
    }

    /**
     * Create middleware that redirects on failure.
     *
     * @param array<string> $permissions The permissions.
     * @param string $redirectUrl URL to redirect to.
     * @return self
     */
    public static function redirectOnFailure(array $permissions, string $redirectUrl): self
    {
        return new self($permissions, $redirectUrl, false);
    }

    /**
     * Create middleware for specific guard requiring any permission.
     *
     * @param string $guard The guard name.
     * @param string ...$permissions The required permissions.
     * @return self
     */
    public static function guard(string $guard, string ...$permissions): self
    {
        return new self($permissions, null, false, $guard);
    }
}
