<?php

declare(strict_types=1);

namespace Lalaz\Auth\Guards;

use Lalaz\Auth\Contracts\RememberTokenProviderInterface;
use Lalaz\Auth\Contracts\SessionInterface;
use Lalaz\Web\Http\Contracts\RequestInterface;

/**
 * Session Guard
 *
 * Authentication guard for session-based (stateful) authentication.
 * Used for traditional web applications with server-side sessions.
 *
 * @package Lalaz\Auth\Guards
 */
class SessionGuard extends BaseGuard
{
    /**
     * Guard name.
     */
    private const string NAME = 'session';

    /**
     * Session key for storing the user ID.
     */
    private const string SESSION_KEY = '__auth_user';

    /**
     * Session key for the remember token.
     */
    private const string REMEMBER_KEY = '__auth_remember';

    /**
     * The session instance.
     *
     * @var SessionInterface|null
     */
    private ?SessionInterface $session = null;

    /**
     * Whether to use remember me functionality.
     *
     * @var bool
     */
    private bool $rememberMe = false;

    /**
     * The HTTP request instance for cookie/secure detection.
     *
     * @var RequestInterface|null
     */
    private ?RequestInterface $request = null;

    /**
     * Create a new SessionGuard instance.
     *
     * @param SessionInterface|null $session
     * @param \Lalaz\Auth\Contracts\UserProviderInterface|null $provider
     * @param RequestInterface|null $request The HTTP request for cookie/secure detection.
     */
    public function __construct(
        ?SessionInterface $session = null,
        ?\Lalaz\Auth\Contracts\UserProviderInterface $provider = null,
        ?RequestInterface $request = null
    ) {
        parent::__construct($provider);
        $this->session = $session;
        $this->request = $request;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * {@inheritdoc}
     */
    public function attempt(array $credentials): mixed
    {
        if ($this->provider === null) {
            return null;
        }

        $user = $this->provider->retrieveByCredentials($credentials);

        if ($user === null) {
            return null;
        }

        if (!$this->provider->validateCredentials($user, $credentials)) {
            return null;
        }

        $this->login($user);

        // Handle remember me
        if ($this->rememberMe && isset($credentials['remember']) && $credentials['remember']) {
            $this->createRememberToken($user);
        }

        return $user;
    }

    /**
     * Attempt authentication with remember me option.
     *
     * @param array<string, mixed> $credentials
     * @param bool $remember
     * @return mixed
     */
    public function attemptWithRemember(array $credentials, bool $remember = false): mixed
    {
        $this->rememberMe = $remember;
        $credentials['remember'] = $remember;
        return $this->attempt($credentials);
    }

    /**
     * {@inheritdoc}
     */
    public function login(mixed $user): void
    {
        $this->user = $user;
        $userId = $this->getUserId($user);

        if ($this->session !== null && $userId !== null) {
            $this->session->regenerate();
            $this->session->set(self::SESSION_KEY, $userId);
        }
    }

    /**
     * Log in a user by their ID.
     *
     * @param mixed $id
     * @return mixed The user or null.
     */
    public function loginById(mixed $id): mixed
    {
        if ($this->provider === null) {
            return null;
        }

        $user = $this->provider->retrieveById($id);

        if ($user !== null) {
            $this->login($user);
        }

        return $user;
    }

    /**
     * {@inheritdoc}
     */
    public function logout(): void
    {
        $this->user = null;

        if ($this->session !== null) {
            $this->session->remove(self::SESSION_KEY);
            $this->session->remove(self::REMEMBER_KEY);
            $this->session->destroy();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function user(): mixed
    {
        // Return cached user
        if ($this->user !== null) {
            return $this->user;
        }

        // Try to load from session
        $userId = $this->getSessionUserId();

        if ($userId !== null && $this->provider !== null) {
            $this->user = $this->provider->retrieveById($userId);
        }

        // Try remember token if no session user
        if ($this->user === null) {
            $this->user = $this->getUserFromRememberToken();
        }

        return $this->user;
    }

    /**
     * Get the user ID from session.
     *
     * @return mixed
     */
    private function getSessionUserId(): mixed
    {
        if ($this->session === null) {
            return null;
        }

        return $this->session->get(self::SESSION_KEY);
    }

    /**
     * Try to authenticate user from remember token.
     *
     * @return mixed
     */
    private function getUserFromRememberToken(): mixed
    {
        // Check if provider supports remember tokens
        if (!$this->provider instanceof RememberTokenProviderInterface) {
            return null;
        }

        // Use injected request if available
        $token = $this->request !== null
            ? $this->request->cookie(self::REMEMBER_KEY)
            : ($_COOKIE[self::REMEMBER_KEY] ?? null);

        if ($token === null) {
            return null;
        }

        $parts = explode('|', $token);

        if (count($parts) !== 2) {
            return null;
        }

        [$userId, $rememberToken] = $parts;

        $user = $this->provider->retrieveByToken($userId, $rememberToken);

        if ($user !== null) {
            $this->login($user);
        }

        return $user;
    }

    /**
     * Create a remember token for the user.
     *
     * @param mixed $user
     * @return void
     */
    private function createRememberToken(mixed $user): void
    {
        // Check if provider supports remember tokens
        if (!$this->provider instanceof RememberTokenProviderInterface) {
            return;
        }

        $token = bin2hex(random_bytes(32));
        $this->provider->updateRememberToken($user, $token);

        $userId = $this->getUserId($user);
        $cookieValue = $userId . '|' . $token;

        // Set cookie for 30 days
        $expires = time() + (30 * 24 * 60 * 60);

        setcookie(
            self::REMEMBER_KEY,
            $cookieValue,
            [
                'expires' => $expires,
                'path' => '/',
                'httponly' => true,
                'secure' => $this->isSecure(),
                'samesite' => 'Lax',
            ]
        );
    }

    /**
     * Check if connection is secure.
     *
     * @return bool
     */
    private function isSecure(): bool
    {
        // Use injected request if available
        if ($this->request !== null) {
            return $this->request->isSecure();
        }

        // Fallback to superglobals
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
    }

    /**
     * Set the request instance.
     *
     * @param RequestInterface $request
     * @return void
     */
    public function setRequest(RequestInterface $request): void
    {
        $this->request = $request;
    }

    /**
     * Set the session instance.
     *
     * @param SessionInterface $session
     * @return void
     */
    public function setSession(SessionInterface $session): void
    {
        $this->session = $session;
    }
}
