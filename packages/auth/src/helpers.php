<?php

declare(strict_types=1);

use Lalaz\Auth\AuthContext;
use Lalaz\Auth\AuthManager;
use Lalaz\Auth\GuardContext;

if (!function_exists('auth')) {
    /**
     * Get the auth manager or a specific guard's context.
     *
     * Usage:
     * ```php
     * // Get auth manager
     * $manager = auth();
     *
     * // Get user from default guard
     * $user = auth()->user();
     *
     * // Get user from specific guard
     * $apiUser = auth('api')->user();
     * $webUser = auth('web')->user();
     *
     * // Check authentication
     * if (auth('api')->check()) {
     *     // User is authenticated via API
     * }
     *
     * // Get auth context (for role/permission checks)
     * auth()->context()->hasRole('admin');
     * ```
     *
     * @param string|null $guard The guard name (null = auth manager).
     * @return AuthManager|GuardContext|null
     */
    function auth(?string $guard = null): AuthManager|GuardContext|null
    {
        if (!function_exists('resolve')) {
            return null;
        }

        try {
            $manager = resolve(AuthManager::class);

            if ($guard !== null) {
                // Return guard-scoped context for chained access
                $context = resolve(AuthContext::class);
                return $context->guard($guard);
            }

            return $manager;
        } catch (\Throwable) {
            return null;
        }
    }
}

if (!function_exists('auth_context')) {
    /**
     * Get the auth context directly.
     *
     * Usage:
     * ```php
     * // Get context
     * $context = auth_context();
     *
     * // Check roles
     * if ($context->hasRole('admin')) {
     *     // ...
     * }
     *
     * // Check permissions for specific guard
     * if ($context->guard('api')->hasPermission('users.create')) {
     *     // ...
     * }
     * ```
     *
     * @return AuthContext|null
     */
    function auth_context(): ?AuthContext
    {
        if (!function_exists('resolve')) {
            return null;
        }

        try {
            return resolve(AuthContext::class);
        } catch (\Throwable) {
            return null;
        }
    }
}

if (!function_exists('user')) {
    /**
     * Get the authenticated user.
     *
     * Shortcut for auth()->user() or auth($guard)->user()
     *
     * Usage:
     * ```php
     * $user = user();           // Default guard
     * $apiUser = user('api');   // API guard
     * ```
     *
     * @param string|null $guard The guard name (null = current guard).
     * @return mixed The authenticated user or null.
     */
    function user(?string $guard = null): mixed
    {
        $context = auth_context();

        if ($context === null) {
            return null;
        }

        return $context->user($guard);
    }
}

if (!function_exists('authenticated')) {
    /**
     * Check if a user is authenticated.
     *
     * Shortcut for auth()->check() or auth($guard)->check()
     *
     * Usage:
     * ```php
     * if (authenticated()) {
     *     // User is authenticated
     * }
     *
     * if (authenticated('api')) {
     *     // User is authenticated via API guard
     * }
     * ```
     *
     * @param string|null $guard The guard name (null = current guard).
     * @return bool True if authenticated.
     */
    function authenticated(?string $guard = null): bool
    {
        $context = auth_context();

        if ($context === null) {
            return false;
        }

        return $context->isAuthenticated($guard);
    }
}

if (!function_exists('guest')) {
    /**
     * Check if no user is authenticated.
     *
     * Shortcut for auth()->guest() or auth($guard)->guest()
     *
     * Usage:
     * ```php
     * if (guest()) {
     *     // No user authenticated
     * }
     * ```
     *
     * @param string|null $guard The guard name (null = current guard).
     * @return bool True if guest (not authenticated).
     */
    function guest(?string $guard = null): bool
    {
        $context = auth_context();

        if ($context === null) {
            return true;
        }

        return $context->isGuest($guard);
    }
}
