<?php

declare(strict_types=1);

namespace Lalaz\Auth\Concerns;

/**
 * Trait for models with role-based authorization.
 *
 * Provides methods to check user roles and permissions.
 * Models using this trait must implement fetchRoles() and fetchPermissions().
 *
 * @package Lalaz\Auth\Concerns
 * @phpstan-ignore trait.unused
 */
trait Authorizable
{
    /**
     * Cached roles for the current request.
     *
     * @var array<string>|null
     */
    private ?array $cachedRoles = null;

    /**
     * Cached permissions for the current request.
     *
     * @var array<string>|null
     */
    private ?array $cachedPermissions = null;

    /**
     * Get all roles for the user.
     *
     * Results are cached for the duration of the request.
     *
     * @return array<string>
     */
    public function getRoles(): array
    {
        if ($this->cachedRoles !== null) {
            return $this->cachedRoles;
        }

        $this->cachedRoles = $this->fetchRoles();
        return $this->cachedRoles;
    }

    /**
     * Get all permissions for the user.
     *
     * Results are cached for the duration of the request.
     *
     * @return array<string>
     */
    public function getPermissions(): array
    {
        if ($this->cachedPermissions !== null) {
            return $this->cachedPermissions;
        }

        $this->cachedPermissions = $this->fetchPermissions();
        return $this->cachedPermissions;
    }

    /**
     * Check if the user has a specific role.
     *
     * @param string $role The role to check.
     * @return bool True if user has the role.
     */
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getRoles(), true);
    }

    /**
     * Check if the user has a specific permission.
     *
     * @param string $permission The permission to check.
     * @return bool True if user has the permission.
     */
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->getPermissions(), true);
    }

    /**
     * Check if the user has any of the specified permissions.
     *
     * @param array<string> $permissions Permissions to check.
     * @return bool True if user has at least one.
     */
    public function hasAnyPermission(array $permissions): bool
    {
        return !empty(array_intersect($permissions, $this->getPermissions()));
    }

    /**
     * Check if the user has all of the specified permissions.
     *
     * @param array<string> $permissions Permissions to check.
     * @return bool True if user has all permissions.
     */
    public function hasAllPermissions(array $permissions): bool
    {
        return empty(array_diff($permissions, $this->getPermissions()));
    }

    /**
     * Check if the user has any of the specified roles.
     *
     * @param array<string> $roles Roles to check.
     * @return bool True if user has at least one.
     */
    public function hasAnyRole(array $roles): bool
    {
        return !empty(array_intersect($roles, $this->getRoles()));
    }

    /**
     * Check if the user has all of the specified roles.
     *
     * @param array<string> $roles Roles to check.
     * @return bool True if user has all roles.
     */
    public function hasAllRoles(array $roles): bool
    {
        return empty(array_diff($roles, $this->getRoles()));
    }

    /**
     * Clear the cached roles and permissions.
     *
     * Call this after modifying user roles/permissions.
     *
     * @return void
     */
    public function clearAuthorizationCache(): void
    {
        $this->cachedRoles = null;
        $this->cachedPermissions = null;
    }

    /**
     * Fetch roles from storage.
     *
     * Override this method to load roles from your database.
     *
     * @return array<string>
     */
    abstract protected function fetchRoles(): array;

    /**
     * Fetch permissions from storage.
     *
     * Override this method to load permissions from your database.
     * Consider including permissions from roles as well.
     *
     * @return array<string>
     */
    abstract protected function fetchPermissions(): array;
}
