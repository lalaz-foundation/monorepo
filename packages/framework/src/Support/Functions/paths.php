<?php

declare(strict_types=1);

/**
 * Path helper functions.
 *
 * Provides convenient global functions for accessing application paths.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */

use Lalaz\Runtime\ApplicationBootstrap;

if (!function_exists('base_path')) {
    /**
     * Get the path to the base of the install.
     *
     * @param string $path Optional path to append
     * @return string
     */
    function base_path(string $path = ''): string
    {
        $base = ApplicationBootstrap::basePath() ?? getcwd();
        return $base . ($path ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : '');
    }
}

if (!function_exists('config_path')) {
    /**
     * Get the configuration path.
     *
     * @param string $path Optional path to append
     * @return string
     */
    function config_path(string $path = ''): string
    {
        return base_path('config' . ($path ? DIRECTORY_SEPARATOR . $path : ''));
    }
}

if (!function_exists('database_path')) {
    /**
     * Get the database path.
     *
     * @param string $path Optional path to append
     * @return string
     */
    function database_path(string $path = ''): string
    {
        return base_path('database' . ($path ? DIRECTORY_SEPARATOR . $path : ''));
    }
}

if (!function_exists('public_path')) {
    /**
     * Get the public path.
     *
     * @param string $path Optional path to append
     * @return string
     */
    function public_path(string $path = ''): string
    {
        return base_path('public' . ($path ? DIRECTORY_SEPARATOR . $path : ''));
    }
}

if (!function_exists('storage_path')) {
    /**
     * Get the storage path.
     *
     * @param string $path Optional path to append
     * @return string
     */
    function storage_path(string $path = ''): string
    {
        return base_path('storage' . ($path ? DIRECTORY_SEPARATOR . $path : ''));
    }
}

if (!function_exists('resource_path')) {
    /**
     * Get the resource path.
     *
     * @param string $path Optional path to append
     * @return string
     */
    function resource_path(string $path = ''): string
    {
        return base_path('resources' . ($path ? DIRECTORY_SEPARATOR . $path : ''));
    }
}
