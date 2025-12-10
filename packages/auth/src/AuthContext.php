<?php

declare(strict_types=1);

namespace Lalaz\Auth;

/**
 * Per-request authentication context.
 *
 * Stores the authenticated user for the current request per guard
 * and provides helpers to inspect identity and authentication state.
 *
 * Supports multiple guards for different authentication contexts:
 * - Web routes might use 'session' guard
 * - API routes might use 'jwt' or 'api-key' guard
 *
 * @package Lalaz\Auth
 */
class AuthContext
{
    /**
     * The authenticated users per guard.
     *
     * @var array<string, mixed>
     */
    private array $users = [];

    /**
     * The current active guard name.
     *
     * @var string
     */
    private string $currentGuard = 'web';

    /**
     * The default guard name.
     *
     * @var string
     */
    private string $defaultGuard = 'web';

    /**
     * Set the authenticated user for a guard.
     *
     * @param mixed $user The user entity/model.
     * @param string|null $guard The guard name (null = current guard).
     * @return void
     */
    public function setUser(mixed $user, ?string $guard = null): void
    {
        $guard = $guard ?? $this->currentGuard;
        $this->users[$guard] = $user;
    }

    /**
     * Get the authenticated user for a guard.
     *
     * @param string|null $guard The guard name (null = current guard).
     * @return mixed The user entity or null if not authenticated.
     */
    public function user(?string $guard = null): mixed
    {
        $guard = $guard ?? $this->currentGuard;
        return $this->users[$guard] ?? null;
    }

    /**
     * Clear the authenticated user for a guard or all guards.
     *
     * @param string|null $guard The guard name (null = clear all).
     * @return void
     */
    public function clear(?string $guard = null): void
    {
        if ($guard === null) {
            $this->users = [];
        } else {
            unset($this->users[$guard]);
        }
    }

    /**
     * Check if a user is authenticated for a guard.
     *
     * @param string|null $guard The guard name (null = current guard).
     * @return bool True if authenticated, false otherwise.
     */
    public function isAuthenticated(?string $guard = null): bool
    {
        return $this->user($guard) !== null;
    }

    /**
     * Alias for isAuthenticated().
     *
     * @param string|null $guard The guard name (null = current guard).
     * @return bool True if authenticated, false otherwise.
     */
    public function check(?string $guard = null): bool
    {
        return $this->isAuthenticated($guard);
    }

    /**
     * Check if no user is authenticated (guest) for a guard.
     *
     * @param string|null $guard The guard name (null = current guard).
     * @return bool True if guest, false if authenticated.
     */
    public function isGuest(?string $guard = null): bool
    {
        return $this->user($guard) === null;
    }

    /**
     * Alias for isGuest().
     *
     * @param string|null $guard The guard name (null = current guard).
     * @return bool True if guest, false if authenticated.
     */
    public function guest(?string $guard = null): bool
    {
        return $this->isGuest($guard);
    }

    /**
     * Set the current active guard.
     *
     * @param string $guard The guard name.
     * @return self
     */
    public function setCurrentGuard(string $guard): self
    {
        $this->currentGuard = $guard;
        return $this;
    }

    /**
     * Get the current active guard name.
     *
     * @return string
     */
    public function getCurrentGuard(): string
    {
        return $this->currentGuard;
    }

    /**
     * Set the default guard.
     *
     * @param string $guard The guard name.
     * @return self
     */
    public function setDefaultGuard(string $guard): self
    {
        $this->defaultGuard = $guard;
        return $this;
    }

    /**
     * Get the default guard name.
     *
     * @return string
     */
    public function getDefaultGuard(): string
    {
        return $this->defaultGuard;
    }

    /**
     * Get a guard-scoped context.
     *
     * This is useful for chaining: $context->guard('api')->user()
     *
     * @param string $guard The guard name.
     * @return GuardContext A guard-scoped context wrapper.
     */
    public function guard(string $guard): GuardContext
    {
        return new GuardContext($this, $guard);
    }

    /**
     * Get the authenticated user's ID.
     *
     * Attempts to retrieve the ID from various formats:
     * - Array with 'id' key
     * - Object with public 'id' property
     * - Object with getId() method
     * - Object with getAuthIdentifier() method
     *
     * @param string|null $guard The guard name (null = current guard).
     * @return mixed The user ID or null if not available.
     */
    public function id(?string $guard = null): mixed
    {
        $user = $this->user($guard);

        if ($user === null) {
            return null;
        }

        // Array shape
        if (is_array($user) && array_key_exists('id', $user)) {
            return $user['id'];
        }

        // Object property or method
        if (is_object($user)) {
            if (method_exists($user, 'getAuthIdentifier')) {
                return $user->getAuthIdentifier();
            }

            if (isset($user->id)) {
                return $user->id;
            }

            if (method_exists($user, 'getId')) {
                return $user->getId();
            }
        }

        return null;
    }

    /**
     * Check if the user has a specific role.
     *
     * @param string $role The role to check.
     * @param string|null $guard The guard name (null = current guard).
     * @return bool True if user has the role.
     */
    public function hasRole(string $role, ?string $guard = null): bool
    {
        $user = $this->user($guard);

        if ($user === null) {
            return false;
        }

        if (method_exists($user, 'hasRole')) {
            return $user->hasRole($role);
        }

        return false;
    }

    /**
     * Check if the user has any of the specified roles.
     *
     * @param array<string> $roles The roles to check.
     * @param string|null $guard The guard name (null = current guard).
     * @return bool True if user has any of the roles.
     */
    public function hasAnyRole(array $roles, ?string $guard = null): bool
    {
        $user = $this->user($guard);

        if ($user === null) {
            return false;
        }

        if (method_exists($user, 'hasAnyRole')) {
            return $user->hasAnyRole($roles);
        }

        foreach ($roles as $role) {
            if ($this->hasRole($role, $guard)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the user has a specific permission.
     *
     * @param string $permission The permission to check.
     * @param string|null $guard The guard name (null = current guard).
     * @return bool True if user has the permission.
     */
    public function hasPermission(string $permission, ?string $guard = null): bool
    {
        $user = $this->user($guard);

        if ($user === null) {
            return false;
        }

        if (method_exists($user, 'hasPermission')) {
            return $user->hasPermission($permission);
        }

        return false;
    }

    /**
     * Check if the user has any of the specified permissions.
     *
     * @param array<string> $permissions The permissions to check.
     * @param string|null $guard The guard name (null = current guard).
     * @return bool True if user has any of the permissions.
     */
    public function hasAnyPermission(array $permissions, ?string $guard = null): bool
    {
        $user = $this->user($guard);

        if ($user === null) {
            return false;
        }

        if (method_exists($user, 'hasAnyPermission')) {
            return $user->hasAnyPermission($permissions);
        }

        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission, $guard)) {
                return true;
            }
        }

        return false;
    }
}
