<?php

declare(strict_types=1);

namespace Lalaz\Auth\Concerns;

use Lalaz\Auth\Contracts\PasswordHasherInterface;
use Lalaz\Auth\Contracts\SessionInterface;
use Lalaz\Auth\NativePasswordHasher;

/**
 * Trait for models that can be authenticated.
 *
 * This trait provides identity-related methods for user models.
 * Authentication should be handled by Guards (SessionGuard, JwtGuard, etc.)
 * rather than directly in the model.
 *
 * Recommended usage:
 * ```php
 * // Use SessionGuard for authentication (preferred)
 * $user = $sessionGuard->attempt(['email' => $email, 'password' => $password]);
 *
 * // Or use AuthManager facade
 * $user = auth()->attempt(['email' => $email, 'password' => $password]);
 * ```
 *
 * @package Lalaz\Auth\Concerns
 * @phpstan-ignore trait.unused
 */
trait Authenticatable
{
    /**
     * Session key for storing authenticated user.
     *
     * @var string
     * @deprecated Use SessionGuard instead of static authentication methods.
     */
    private static string $userSessionKey = '__luser';

    /**
     * The password hasher instance for this model.
     *
     * @var PasswordHasherInterface|null
     */
    private static ?PasswordHasherInterface $passwordHasher = null;

    /**
     * Get the column/property name for username.
     *
     * @return string
     */
    abstract protected static function usernamePropertyName(): string;

    /**
     * Get the column/property name for password.
     *
     * @return string
     */
    abstract protected static function passwordPropertyName(): string;

    /**
     * Set the password hasher for this model.
     *
     * @param PasswordHasherInterface $hasher
     * @return void
     */
    public static function setPasswordHasher(PasswordHasherInterface $hasher): void
    {
        static::$passwordHasher = $hasher;
    }

    /**
     * Get the password hasher for this model.
     *
     * @return PasswordHasherInterface
     */
    public static function getPasswordHasher(): PasswordHasherInterface
    {
        return static::$passwordHasher ?? new NativePasswordHasher();
    }

    // ===== Identity Methods (Pure - No Side Effects) =====

    /**
     * Get the unique identifier for authentication.
     *
     * @return mixed
     */
    public function getAuthIdentifier(): mixed
    {
        return $this->id ?? ($this->getId() ?? null);
    }

    /**
     * Get the name of the identifier column.
     *
     * @return string
     */
    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    /**
     * Get the password for authentication.
     *
     * @return string
     */
    public function getAuthPassword(): string
    {
        $property = static::passwordPropertyName();
        return $this->{$property} ?? '';
    }

    /**
     * Get the remember token value.
     *
     * @return string|null
     */
    public function getRememberToken(): ?string
    {
        return $this->{$this->getRememberTokenName()} ?? null;
    }

    /**
     * Set the remember token value.
     *
     * @param string|null $value
     * @return void
     */
    public function setRememberToken(?string $value): void
    {
        $this->{$this->getRememberTokenName()} = $value;
    }

    /**
     * Get the column name for remember token.
     *
     * @return string
     */
    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }

    // ===== Credential Validation (Pure - No Side Effects) =====

    /**
     * Verify if the given password matches the user's password.
     *
     * This is a pure method with no side effects.
     * Uses the configured password hasher (NativePasswordHasher by default).
     *
     * @param string $password The plain text password to verify.
     * @return bool True if password matches.
     */
    public function verifyPassword(string $password): bool
    {
        $hashedPassword = $this->getAuthPassword();

        if (empty($hashedPassword)) {
            return false;
        }

        return static::getPasswordHasher()->verify($password, $hashedPassword);
    }

    /**
     * Check if the user's password needs rehashing.
     *
     * @return bool True if rehashing is recommended.
     */
    public function passwordNeedsRehash(): bool
    {
        $hashedPassword = $this->getAuthPassword();

        if (empty($hashedPassword)) {
            return false;
        }

        return static::getPasswordHasher()->needsRehash($hashedPassword);
    }

    /**
     * Hash a plain text password using the model's hasher.
     *
     * @param string $password The plain text password.
     * @return string The hashed password.
     */
    public static function hashPassword(string $password): string
    {
        return static::getPasswordHasher()->hash($password);
    }

    /**
     * Find user by username/email and verify password.
     *
     * This is a pure method - it only validates, no session side effects.
     * Use SessionGuard::attempt() or AuthManager::attempt() for full authentication.
     *
     * @param string $username The username/email.
     * @param string $password The plain text password.
     * @return static|null The user if credentials are valid, null otherwise.
     */
    public static function validateCredentials(string $username, string $password): ?static
    {
        $usernameProperty = static::usernamePropertyName();
        $user = static::findByUsername($username, $usernameProperty);

        if ($user === null) {
            return null;
        }

        if (!$user->verifyPassword($password)) {
            return null;
        }

        return $user;
    }

    /**
     * Find a user by username.
     *
     * Override this method if your model uses a different query approach.
     *
     * @param string $username The username to search for.
     * @param string $property The property name to search.
     * @return static|null The user or null.
     */
    protected static function findByUsername(string $username, string $property): ?static
    {
        // Try findBy method
        if (method_exists(static::class, 'findBy')) {
            $results = static::findBy([$property => $username]);
            return $results[0] ?? null;
        }

        // Try findOneBy method
        if (method_exists(static::class, 'findOneBy')) {
            return static::findOneBy([$property => $username]);
        }

        return null;
    }

    // ===== Deprecated Methods (For Backward Compatibility) =====

    /**
     * Authenticate a user with username and password.
     *
     * @param string $username The username/email.
     * @param string $password The plain text password.
     * @param SessionInterface|null $session Optional session instance.
     * @return mixed The authenticated user or false on failure.
     *
     * @deprecated Use SessionGuard::attempt() or AuthManager::attempt() instead.
     *             This method will be removed in version 4.0.
     *
     * Example migration:
     * ```php
     * // Before (deprecated)
     * $user = User::authenticate($email, $password);
     *
     * // After (recommended)
     * $user = $sessionGuard->attempt(['email' => $email, 'password' => $password]);
     * // or
     * $user = auth()->attempt(['email' => $email, 'password' => $password]);
     * ```
     */
    public static function authenticate(
        string $username,
        string $password,
        ?SessionInterface $session = null,
    ): mixed {
        @trigger_error(
            'Method ' . __METHOD__ . ' is deprecated. Use SessionGuard::attempt() instead.',
            E_USER_DEPRECATED
        );

        $user = static::validateCredentials($username, $password);

        if ($user === null) {
            return false;
        }

        // Store in session (side effect - deprecated behavior)
        $session = $session ?? static::resolveSession();

        if ($session) {
            $session->regenerate();
            $session->set(static::$userSessionKey, $user);
        }

        return $user;
    }

    /**
     * Log out the current user.
     *
     * @param SessionInterface|null $session Optional session instance.
     * @return void
     *
     * @deprecated Use SessionGuard::logout() or AuthManager::logout() instead.
     *             This method will be removed in version 4.0.
     */
    public static function logout(?SessionInterface $session = null): void
    {
        @trigger_error(
            'Method ' . __METHOD__ . ' is deprecated. Use SessionGuard::logout() instead.',
            E_USER_DEPRECATED
        );

        $session = $session ?? static::resolveSession();

        if ($session) {
            $session->destroy();
        }
    }

    /**
     * Get the currently authenticated user.
     *
     * @param SessionInterface|null $session Optional session instance.
     * @return mixed The authenticated user or null.
     *
     * @deprecated Use SessionGuard::user() or auth()->user() instead.
     *             This method will be removed in version 4.0.
     */
    public static function authenticatedUser(?SessionInterface $session = null): mixed
    {
        @trigger_error(
            'Method ' . __METHOD__ . ' is deprecated. Use SessionGuard::user() or auth()->user() instead.',
            E_USER_DEPRECATED
        );

        $session = $session ?? static::resolveSession();

        if ($session) {
            return $session->get(static::$userSessionKey);
        }

        return null;
    }

    /**
     * Check if there is an authenticated user.
     *
     * @param SessionInterface|null $session Optional session instance.
     * @return bool True if authenticated.
     *
     * @deprecated Use SessionGuard::check() or auth()->check() instead.
     *             This method will be removed in version 4.0.
     */
    public static function isAuthenticated(?SessionInterface $session = null): bool
    {
        @trigger_error(
            'Method ' . __METHOD__ . ' is deprecated. Use SessionGuard::check() or auth()->check() instead.',
            E_USER_DEPRECATED
        );

        return static::authenticatedUser($session) !== null;
    }

    /**
     * Resolve the session instance from container or create default.
     *
     * @return SessionInterface|null
     * @deprecated Internal method for deprecated authentication methods.
     */
    protected static function resolveSession(): ?SessionInterface
    {
        // Try to resolve from container
        if (function_exists('resolve')) {
            try {
                return resolve(SessionInterface::class);
            } catch (\Throwable) {
                // Fallback to SessionManager if available
                if (class_exists(\Lalaz\Web\Http\SessionManager::class)) {
                    return resolve(\Lalaz\Web\Http\SessionManager::class);
                }
            }
        }

        return null;
    }
}
