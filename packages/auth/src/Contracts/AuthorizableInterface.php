<?php

declare(strict_types=1);

namespace Lalaz\Auth\Contracts;

/**
 * Contract for entities that can be authorized with roles and permissions.
 *
 * @package Lalaz\Auth\Contracts
 */
interface AuthorizableInterface
{
    /**
     * Get all roles for the user.
     *
     * @return array<string>
     */
    public function getRoles(): array;

    /**
     * Get all permissions for the user.
     *
     * @return array<string>
     */
    public function getPermissions(): array;

    /**
     * Check if the user has a specific role.
     *
     * @param string $role
     * @return bool
     */
    public function hasRole(string $role): bool;

    /**
     * Check if the user has a specific permission.
     *
     * @param string $permission
     * @return bool
     */
    public function hasPermission(string $permission): bool;

    /**
     * Check if the user has any of the specified roles.
     *
     * @param array<string> $roles
     * @return bool
     */
    public function hasAnyRole(array $roles): bool;

    /**
     * Check if the user has any of the specified permissions.
     *
     * @param array<string> $permissions
     * @return bool
     */
    public function hasAnyPermission(array $permissions): bool;

    /**
     * Check if the user has all of the specified permissions.
     *
     * @param array<string> $permissions
     * @return bool
     */
    public function hasAllPermissions(array $permissions): bool;
}
