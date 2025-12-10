<?php

declare(strict_types=1);

namespace Lalaz\Auth\Middlewares;

use Lalaz\Auth\AuthContext;
use Lalaz\Auth\AuthManager;
use Lalaz\Auth\Contracts\SessionInterface;
use Lalaz\Exceptions\HttpException;
use Lalaz\Web\Http\Contracts\MiddlewareInterface;
use Lalaz\Web\Http\Contracts\RequestInterface;
use Lalaz\Web\Http\Contracts\ResponseInterface;

/**
 * Authentication Middleware
 *
 * Ensures a user is authenticated before proceeding.
 * Supports multiple authentication guards (session, jwt, api-key).
 *
 * Usage:
 * ```php
 * // Web routes - session based
 * $router->group('/web', fn($r) => ...)->middleware(
 *     AuthenticationMiddleware::session()
 * );
 *
 * // API routes - JWT based
 * $router->group('/api', fn($r) => ...)->middleware(
 *     AuthenticationMiddleware::jwt()
 * );
 *
 * // API routes - API Key based
 * $router->group('/api/v2', fn($r) => ...)->middleware(
 *     AuthenticationMiddleware::apiKey()
 * );
 *
 * // Custom guard
 * $router->get('/custom', ...)->middleware(
 *     AuthenticationMiddleware::guard('my-guard')
 * );
 *
 * // With explicit dependencies (DI)
 * new AuthenticationMiddleware(
 *     guard: 'web',
 *     authManager: $authManager,
 *     authContext: $authContext,
 *     session: $session
 * );
 * ```
 *
 * @package Lalaz\Auth\Middlewares
 */
class AuthenticationMiddleware implements MiddlewareInterface
{
    /**
     * The guard name to use for authentication.
     *
     * @var string
     */
    private string $guardName;

    /**
     * URL to redirect to if not authenticated (null = throw 401).
     *
     * @var string|null
     */
    private ?string $loginUrl;

    /**
     * The auth manager instance (injected or resolved).
     *
     * @var AuthManager|null
     */
    private ?AuthManager $authManager;

    /**
     * The auth context instance (injected or resolved).
     *
     * @var AuthContext|null
     */
    private ?AuthContext $authContext;

    /**
     * The session instance (injected or resolved).
     *
     * @var SessionInterface|null
     */
    private ?SessionInterface $session;

    /**
     * Create a new authentication middleware instance.
     *
     * @param string $guard The guard name to use.
     * @param string|null $loginUrl URL to redirect (null = throw 401).
     * @param AuthManager|null $authManager The auth manager (null = resolve from container).
     * @param AuthContext|null $authContext The auth context (null = resolve from container).
     * @param SessionInterface|null $session The session (null = resolve from container).
     */
    public function __construct(
        string $guard = 'web',
        ?string $loginUrl = null,
        ?AuthManager $authManager = null,
        ?AuthContext $authContext = null,
        ?SessionInterface $session = null,
    ) {
        $this->guardName = $guard;
        $this->loginUrl = $loginUrl;
        $this->authManager = $authManager;
        $this->authContext = $authContext;
        $this->session = $session;
    }

    /**
     * Handle an incoming request.
     *
     * @param RequestInterface $req The HTTP request.
     * @param ResponseInterface $res The HTTP response.
     * @param callable $next The next middleware in the chain.
     * @return mixed
     * @throws HttpException When not authenticated and no login URL.
     */
    public function handle(RequestInterface $req, ResponseInterface $res, callable $next): mixed
    {
        $user = $this->authenticate($req);
        $authContext = $this->getAuthContext();

        if ($user === null) {
            if ($this->loginUrl !== null) {
                $res->redirect($this->loginUrl);
                return null;
            }

            throw HttpException::unauthorized('Authentication required');
        }

        // Populate auth context with guard info
        if ($authContext) {
            $authContext->setCurrentGuard($this->guardName);
            $authContext->setUser($user, $this->guardName);
        }

        // Also set on request for convenience
        /** @phpstan-ignore property.notFound */
        $req->user = $user;

        return $next($req, $res);
    }

    /**
     * Authenticate using the configured guard.
     *
     * @param RequestInterface $req The HTTP request.
     * @return mixed The authenticated user or null.
     */
    private function authenticate(RequestInterface $req): mixed
    {
        $authManager = $this->getAuthManager();

        if ($authManager && $authManager->hasGuard($this->guardName)) {
            $guard = $authManager->guard($this->guardName);

            if ($guard->check()) {
                return $guard->user();
            }
        }

        // Fallback to session for 'web' or 'session' guard
        if (in_array($this->guardName, ['web', 'session'], true)) {
            return $this->getSessionUser();
        }

        return null;
    }

    /**
     * Get user from session (fallback).
     *
     * @return mixed The user or null.
     */
    private function getSessionUser(): mixed
    {
        $session = $this->getSession();

        if ($session) {
            return $session->get('__luser');
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            return $_SESSION['__luser'] ?? null;
        }

        return null;
    }

    /**
     * Get the guard name.
     *
     * @return string
     */
    public function getGuardName(): string
    {
        return $this->guardName;
    }

    // ===== Factory Methods =====

    /**
     * Create middleware for a specific guard.
     *
     * @param string $guard The guard name.
     * @param string|null $loginUrl Optional redirect URL.
     * @return self
     */
    public static function guard(string $guard, ?string $loginUrl = null): self
    {
        return new self($guard, $loginUrl);
    }

    /**
     * Create middleware for session-based authentication (web).
     *
     * @param string|null $loginUrl URL to redirect if not authenticated.
     * @return self
     */
    public static function session(?string $loginUrl = null): self
    {
        return new self('session', $loginUrl);
    }

    /**
     * Alias for session() - for web routes.
     *
     * @param string|null $loginUrl URL to redirect if not authenticated.
     * @return self
     */
    public static function web(?string $loginUrl = null): self
    {
        return new self('web', $loginUrl);
    }

    /**
     * Create middleware for JWT-based authentication (API).
     *
     * @return self
     */
    public static function jwt(): self
    {
        return new self('jwt');
    }

    /**
     * Alias for jwt() - for API routes.
     *
     * @return self
     */
    public static function api(): self
    {
        return new self('api');
    }

    /**
     * Create middleware for API key authentication.
     *
     * @return self
     */
    public static function apiKey(): self
    {
        return new self('api-key');
    }

    /**
     * Create middleware that redirects to login page.
     *
     * @param string $loginUrl The login URL.
     * @param string $guard The guard name.
     * @return self
     */
    public static function redirectTo(string $loginUrl, string $guard = 'web'): self
    {
        return new self($guard, $loginUrl);
    }

    /**
     * Create middleware that throws 401 on unauthenticated access.
     *
     * @param string $guard The guard name.
     * @return self
     */
    public static function strict(string $guard = 'web'): self
    {
        return new self($guard);
    }

    // ===== Dependency Resolution =====

    /**
     * Get auth manager (injected or from container).
     *
     * @return AuthManager|null
     */
    private function getAuthManager(): ?AuthManager
    {
        if ($this->authManager !== null) {
            return $this->authManager;
        }

        if (function_exists('resolve')) {
            try {
                return resolve(AuthManager::class);
            } catch (\Throwable) {
                // Ignore
            }
        }

        return null;
    }

    /**
     * Get session instance (injected or from container).
     *
     * @return SessionInterface|null
     */
    private function getSession(): ?SessionInterface
    {
        if ($this->session !== null) {
            return $this->session;
        }

        if (function_exists('resolve')) {
            try {
                return resolve(SessionInterface::class);
            } catch (\Throwable) {
                if (class_exists(\Lalaz\Web\Http\SessionManager::class)) {
                    try {
                        return resolve(\Lalaz\Web\Http\SessionManager::class);
                    } catch (\Throwable) {
                        // Ignore
                    }
                }
            }
        }

        return null;
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

    // ===== Setters for Dependency Injection =====

    /**
     * Set the auth manager instance.
     *
     * @param AuthManager $authManager The auth manager.
     * @return self
     */
    public function setAuthManager(AuthManager $authManager): self
    {
        $this->authManager = $authManager;
        return $this;
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

    /**
     * Set the session instance.
     *
     * @param SessionInterface $session The session.
     * @return self
     */
    public function setSession(SessionInterface $session): self
    {
        $this->session = $session;
        return $this;
    }
}
