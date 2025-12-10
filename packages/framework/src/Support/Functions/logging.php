<?php

declare(strict_types=1);

/**
 * Logging helper functions.
 *
 * Provides shorthand global functions for logging at different levels.
 * These are convenience wrappers around the Log facade.
 *
 * @package lalaz/framework
 * @author Lalaz Framework <hello@lalaz.dev>
 * @link https://lalaz.dev
 */

use Lalaz\Logging\Log;

if (!function_exists('emergency')) {
    /**
     * Log an emergency message.
     *
     * System is unusable.
     *
     * @param string|\Stringable $message The log message
     * @param array $context Additional context data
     * @return void
     *
     * @example
     * ```php
     * emergency('Database server is down!', ['server' => 'db-primary']);
     * ```
     */
    function emergency(string|\Stringable $message, array $context = []): void
    {
        Log::emergency($message, $context);
    }
}

if (!function_exists('alert')) {
    /**
     * Log an alert message.
     *
     * Action must be taken immediately.
     *
     * @param string|\Stringable $message The log message
     * @param array $context Additional context data
     * @return void
     *
     * @example
     * ```php
     * alert('Database connection pool exhausted', ['connections' => 100]);
     * ```
     */
    function alert(string|\Stringable $message, array $context = []): void
    {
        Log::alert($message, $context);
    }
}

if (!function_exists('critical')) {
    /**
     * Log a critical message.
     *
     * Critical conditions, e.g. unexpected exception.
     *
     * @param string|\Stringable $message The log message
     * @param array $context Additional context data
     * @return void
     *
     * @example
     * ```php
     * critical('Payment gateway unavailable', ['gateway' => 'stripe']);
     * ```
     */
    function critical(string|\Stringable $message, array $context = []): void
    {
        Log::critical($message, $context);
    }
}

if (!function_exists('error')) {
    /**
     * Log an error message.
     *
     * Runtime errors that do not require immediate action.
     *
     * @param string|\Stringable $message The log message
     * @param array $context Additional context data
     * @return void
     *
     * @example
     * ```php
     * error('Failed to send email', ['to' => 'user@example.com', 'exception' => $e]);
     * ```
     */
    function error(string|\Stringable $message, array $context = []): void
    {
        Log::error($message, $context);
    }
}

if (!function_exists('warning')) {
    /**
     * Log a warning message.
     *
     * Exceptional occurrences that are not errors.
     *
     * @param string|\Stringable $message The log message
     * @param array $context Additional context data
     * @return void
     *
     * @example
     * ```php
     * warning('Deprecated API endpoint called', ['endpoint' => '/api/v1/users']);
     * ```
     */
    function warning(string|\Stringable $message, array $context = []): void
    {
        Log::warning($message, $context);
    }
}

if (!function_exists('notice')) {
    /**
     * Log a notice message.
     *
     * Normal but significant events.
     *
     * @param string|\Stringable $message The log message
     * @param array $context Additional context data
     * @return void
     *
     * @example
     * ```php
     * notice('User account activated', ['user_id' => 123]);
     * ```
     */
    function notice(string|\Stringable $message, array $context = []): void
    {
        Log::notice($message, $context);
    }
}

if (!function_exists('info')) {
    /**
     * Log an info message.
     *
     * Interesting events like user login.
     *
     * @param string|\Stringable $message The log message
     * @param array $context Additional context data
     * @return void
     *
     * @example
     * ```php
     * info('User logged in', ['user_id' => 123, 'ip' => '192.168.1.1']);
     * ```
     */
    function info(string|\Stringable $message, array $context = []): void
    {
        Log::info($message, $context);
    }
}

if (!function_exists('debug')) {
    /**
     * Log a debug message.
     *
     * Detailed debug information.
     *
     * @param string|\Stringable $message The log message
     * @param array $context Additional context data
     * @return void
     *
     * @example
     * ```php
     * debug('Query executed', ['sql' => $sql, 'bindings' => $bindings, 'time' => '12ms']);
     * ```
     */
    function debug(string|\Stringable $message, array $context = []): void
    {
        Log::debug($message, $context);
    }
}

if (!function_exists('logger')) {
    /**
     * Get the logger instance or log a debug message.
     *
     * @param string|null $message Optional message to log at debug level
     * @param array $context Additional context data
     * @return \Lalaz\Logging\LogManager|null
     *
     * @example
     * ```php
     * // Get logger instance
     * $logger = logger();
     * $logger->info('Something happened');
     *
     * // Quick debug log
     * logger('Processing started', ['items' => count($items)]);
     * ```
     */
    function logger(?string $message = null, array $context = []): ?\Lalaz\Logging\LogManager
    {
        if ($message === null) {
            return Log::getFacadeRoot();
        }

        Log::debug($message, $context);
        return null;
    }
}
