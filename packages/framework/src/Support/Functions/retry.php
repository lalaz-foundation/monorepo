<?php

declare(strict_types=1);

/**
 * Retry helper functions.
 *
 * Provides convenient global functions for retry operations
 * with configurable attempts and delays.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */

use Lalaz\Support\Resilience\Retry;

if (!function_exists('retry')) {
    /**
     * Retries a callback operation multiple times on failure.
     *
     * @param callable $callback The operation to retry
     * @param int      $times    Maximum number of attempts
     * @param int      $delayMs  Delay between attempts in milliseconds
     *
     * @return mixed The callback result on success
     *
     * @throws \Throwable The last exception if all attempts fail
     *
     * @example
     * ```php
     * $result = retry(function() {
     *     return $httpClient->request('GET', $url);
     * }, times: 3, delayMs: 500);
     * ```
     */
    function retry(
        callable $callback,
        int $times = 3,
        int $delayMs = 100,
    ): mixed {
        return Retry::times($times)
            ->withDelay($delayMs)
            ->execute($callback);
    }
}

if (!function_exists('retry_on')) {
    /**
     * Retries a callback only on specific exception types.
     *
     * @param callable             $callback   The operation to retry
     * @param array<class-string>  $exceptions Exception classes to retry on
     * @param int                  $times      Maximum number of attempts
     * @param int                  $delayMs    Delay between attempts in milliseconds
     *
     * @return mixed The callback result on success
     *
     * @throws \Throwable The last exception if all attempts fail
     *
     * @example
     * ```php
     * $result = retry_on(
     *     fn() => $api->call(),
     *     [ConnectionException::class, TimeoutException::class],
     *     times: 5,
     *     delayMs: 1000
     * );
     * ```
     */
    function retry_on(
        callable $callback,
        array $exceptions,
        int $times = 3,
        int $delayMs = 100,
    ): mixed {
        return Retry::times($times)
            ->withDelay($delayMs)
            ->onExceptions($exceptions)
            ->execute($callback);
    }
}
