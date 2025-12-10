<?php

declare(strict_types=1);

/**
 * Configuration helper functions.
 *
 * Provides convenient global functions for accessing configuration
 * and environment values throughout the application.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */

use Lalaz\Config\Config;

if (!function_exists('env')) {
    /**
     * Gets an environment variable or configuration value.
     *
     * @param string $key     The configuration key (dot notation supported)
     * @param mixed  $default Default value if key doesn't exist
     *
     * @return mixed The configuration value or default
     *
     * @example
     * ```php
     * $debug = env('APP_DEBUG', false);
     * $dbHost = env('DB_HOST', 'localhost');
     * ```
     */
    function env(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('config')) {
    /**
     * Gets a configuration value using dot notation.
     *
     * @param string $key     The configuration key (dot notation supported)
     * @param mixed  $default Default value if key doesn't exist
     *
     * @return mixed The configuration value or default
     *
     * @example
     * ```php
     * $appName = config('app.name', 'My App');
     * $mailDriver = config('mail.driver');
     * $cacheStore = config('cache.default', 'file');
     * ```
     */
    function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}
