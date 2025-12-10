<?php

declare(strict_types=1);

/**
 * Try/Catch helper functions.
 *
 * Provides utility functions for error handling with
 * automatic logging and exception type handling.
 *
 * @package lalaz/framework
 * @author Lalaz Framework <hello@lalaz.dev>
 * @link https://lalaz.dev
 */

use Lalaz\Logging\Log;

if (!function_exists('tryCatch')) {
    /**
     * Execute a try/catch block with configurable exception handlers.
     *
     * Returns a tuple [$result, $exception] where $result is the callback
     * return value on success, and $exception is null on success or the
     * caught exception on failure.
     *
     * @param callable $tryBlock The code to execute
     * @param array<class-string<\Throwable>, callable> $catchHandlers Exception-specific handlers
     * @param callable|null $defaultHandler Default handler for unmatched exceptions
     * @param bool $logExceptions Whether to log exceptions automatically
     * @param bool $rethrow Whether to rethrow exceptions after handling
     * @return array{0: mixed, 1: \Throwable|null} Tuple of [result, exception]
     *
     * @example
     * ```php
     * // Basic usage
     * [$result, $error] = tryCatch(fn() => riskyOperation());
     *
     * if ($error) {
     *     // Handle error
     * }
     *
     * // With specific exception handlers
     * [$result, $error] = tryCatch(
     *     fn() => fetchFromApi(),
     *     [
     *         ConnectionException::class => fn($e) => 'Connection failed',
     *         TimeoutException::class => fn($e) => 'Request timed out',
     *     ],
     *     fn($e) => 'Unknown error: ' . $e->getMessage()
     * );
     *
     * // Without logging
     * [$result, $error] = tryCatch(
     *     fn() => parseJson($input),
     *     logExceptions: false
     * );
     *
     * // With rethrow
     * [$result, $error] = tryCatch(
     *     fn() => criticalOperation(),
     *     rethrow: true
     * );
     * ```
     */
    function tryCatch(
        callable $tryBlock,
        array $catchHandlers = [],
        ?callable $defaultHandler = null,
        bool $logExceptions = true,
        bool $rethrow = false,
    ): array {
        try {
            $result = $tryBlock();
            return [$result, null];
        } catch (\Throwable $e) {
            // Check for specific exception handlers
            foreach ($catchHandlers as $exceptionClass => $handler) {
                if ($e instanceof $exceptionClass) {
                    $result = $handler($e);

                    if ($rethrow) {
                        throw $e;
                    }

                    return [$result, $e];
                }
            }

            // Use default handler if provided
            if ($defaultHandler !== null) {
                $result = $defaultHandler($e);

                if ($rethrow) {
                    throw $e;
                }

                return [$result, $e];
            }

            // Log the exception if enabled
            if ($logExceptions) {
                logException($e);
            }

            if ($rethrow) {
                throw $e;
            }

            return [null, $e];
        }
    }
}

if (!function_exists('tryOr')) {
    /**
     * Execute a callback and return a default value on exception.
     *
     * @param callable $callback The code to execute
     * @param mixed $default The default value to return on exception
     * @param bool $logException Whether to log the exception
     * @return mixed The callback result or default value
     *
     * @example
     * ```php
     * $value = tryOr(fn() => json_decode($json, true), []);
     * $user = tryOr(fn() => User::findOrFail($id), null);
     * ```
     */
    function tryOr(callable $callback, mixed $default = null, bool $logException = false): mixed
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            if ($logException) {
                logException($e);
            }

            return is_callable($default) ? $default($e) : $default;
        }
    }
}

if (!function_exists('tryOnce')) {
    /**
     * Execute a callback, silencing any exceptions.
     *
     * @param callable $callback The code to execute
     * @param bool $logException Whether to log the exception
     * @return mixed|null The callback result or null on exception
     *
     * @example
     * ```php
     * tryOnce(fn() => unlink($tempFile));
     * tryOnce(fn() => cache()->forget($key));
     * ```
     */
    function tryOnce(callable $callback, bool $logException = false): mixed
    {
        return tryOr($callback, null, $logException);
    }
}

if (!function_exists('rescue')) {
    /**
     * Execute a callback and catch any exceptions with optional reporting.
     *
     * This is a friendlier name for common try/catch patterns.
     *
     * @param callable $callback The code to execute
     * @param mixed $rescue The value or callback to return on exception
     * @param bool $report Whether to log the exception
     * @return mixed
     *
     * @example
     * ```php
     * $value = rescue(fn() => riskyCode(), 'default');
     * $value = rescue(fn() => riskyCode(), fn($e) => handleError($e));
     * ```
     */
    function rescue(callable $callback, mixed $rescue = null, bool $report = true): mixed
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            if ($report) {
                logException($e);
            }

            return is_callable($rescue) ? $rescue($e) : $rescue;
        }
    }
}

if (!function_exists('logException')) {
    /**
     * Log an exception with context information.
     *
     * @param \Throwable $e The exception to log
     * @param string $prefix Optional prefix for log messages
     * @return void
     */
    function logException(\Throwable $e, string $prefix = ''): void
    {
        $prefix = $prefix ? "{$prefix} " : '';

        if (class_exists(Log::class)) {
            Log::error("{$prefix}🛑 Error: " . $e->getMessage());
            Log::error("{$prefix}📍 File: " . $e->getFile() . ' (Line ' . $e->getLine() . ')');
            Log::debug("{$prefix}🔎 Stack trace:" . PHP_EOL . $e->getTraceAsString());
        } else {
            error_log("{$prefix}Error: " . $e->getMessage());
            error_log("{$prefix}File: " . $e->getFile() . ' (Line ' . $e->getLine() . ')');
        }
    }
}

if (!function_exists('throwIf')) {
    /**
     * Throw an exception if a condition is true.
     *
     * @param bool|mixed $condition The condition to check
     * @param \Throwable|string $exception The exception to throw
     * @param mixed ...$args Arguments for exception constructor if string
     * @return void
     *
     * @example
     * ```php
     * throwIf($user === null, new UserNotFoundException());
     * throwIf(empty($data), InvalidArgumentException::class, 'Data cannot be empty');
     * ```
     */
    function throwIf(mixed $condition, \Throwable|string $exception, mixed ...$args): void
    {
        if ($condition) {
            if (is_string($exception)) {
                throw new $exception(...$args);
            }

            throw $exception;
        }
    }
}

if (!function_exists('throwUnless')) {
    /**
     * Throw an exception unless a condition is true.
     *
     * @param bool|mixed $condition The condition to check
     * @param \Throwable|string $exception The exception to throw
     * @param mixed ...$args Arguments for exception constructor if string
     * @return void
     *
     * @example
     * ```php
     * throwUnless($user->isAdmin(), new UnauthorizedException());
     * throwUnless($valid, ValidationException::class, 'Validation failed');
     * ```
     */
    function throwUnless(mixed $condition, \Throwable|string $exception, mixed ...$args): void
    {
        throwIf(!$condition, $exception, ...$args);
    }
}
