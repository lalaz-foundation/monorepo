<?php

declare(strict_types=1);

namespace Lalaz\Auth;

/**
 * Guard-scoped authentication context.
 *
 * Provides a fluent wrapper around AuthContext for a specific guard.
 * Enables chained access like: $context->guard('api')->user()
 *
 * @package Lalaz\Auth
 */
class GuardContext
{
    /**
     * The parent auth context.
     *
     * @var AuthContext
     */
    private AuthContext $context;

    /**
     * The guard name.
     *
     * @var string
     */
    private string $guard;

    /**
     * Create a new guard context instance.
     *
     * @param AuthContext $context The parent context.
     * @param string $guard The guard name.
     */
    public function __construct(AuthContext $context, string $guard)
    {
        $this->context = $context;
        $this->guard = $guard;
    }

    /**
     * Get the guard name.
     *
     * @return string
     */
    public function name(): string
    {
        return $this->guard;
    }

    /**
     * Set the authenticated user for this guard.
     *
     * @param mixed $user The user entity/model.
     * @return self
     */
    public function setUser(mixed $user): self
    {
        $this->context->setUser($user, $this->guard);
        return $this;
    }

    /**
     * Get the authenticated user for this guard.
     *
     * @return mixed The user entity or null if not authenticated.
     */
    public function user(): mixed
    {
        return $this->context->user($this->guard);
    }

    /**
     * Clear the authenticated user for this guard.
     *
     * @return self
     */
    public function clear(): self
    {
        $this->context->clear($this->guard);
        return $this;
    }

    /**
     * Check if a user is authenticated for this guard.
     *
     * @return bool True if authenticated, false otherwise.
     */
    public function isAuthenticated(): bool
    {
        return $this->context->isAuthenticated($this->guard);
    }

    /**
     * Alias for isAuthenticated().
     *
     * @return bool True if authenticated, false otherwise.
     */
    public function check(): bool
    {
        return $this->isAuthenticated();
    }

    /**
     * Check if no user is authenticated (guest) for this guard.
     *
     * @return bool True if guest, false if authenticated.
     */
    public function isGuest(): bool
    {
        return $this->context->isGuest($this->guard);
    }

    /**
     * Alias for isGuest().
     *
     * @return bool True if guest, false if authenticated.
     */
    public function guest(): bool
    {
        return $this->isGuest();
    }

    /**
     * Get the authenticated user's ID for this guard.
     *
     * @return mixed The user ID or null if not available.
     */
    public function id(): mixed
    {
        return $this->context->id($this->guard);
    }

    /**
     * Check if the user has a specific role.
     *
     * @param string $role The role to check.
     * @return bool True if user has the role.
     */
    public function hasRole(string $role): bool
    {
        return $this->context->hasRole($role, $this->guard);
    }

    /**
     * Check if the user has any of the specified roles.
     *
     * @param array<string> $roles The roles to check.
     * @return bool True if user has any of the roles.
     */
    public function hasAnyRole(array $roles): bool
    {
        return $this->context->hasAnyRole($roles, $this->guard);
    }

    /**
     * Check if the user has a specific permission.
     *
     * @param string $permission The permission to check.
     * @return bool True if user has the permission.
     */
    public function hasPermission(string $permission): bool
    {
        return $this->context->hasPermission($permission, $this->guard);
    }

    /**
     * Check if the user has any of the specified permissions.
     *
     * @param array<string> $permissions The permissions to check.
     * @return bool True if user has any of the permissions.
     */
    public function hasAnyPermission(array $permissions): bool
    {
        return $this->context->hasAnyPermission($permissions, $this->guard);
    }
}
