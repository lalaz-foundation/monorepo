<?php

declare(strict_types=1);

/**
 * Event helper functions.
 *
 * Provides global helper functions for dispatching events.
 *
 * @package lalaz/events
 * @author Lalaz Framework <hello@lalaz.dev>
 * @link https://lalaz.dev
 */

use Lalaz\Events\Events;

if (!function_exists('dispatch')) {
    /**
     * Dispatch an event asynchronously (queued if enabled).
     *
     * @param string $event The event name
     * @param mixed $payload The event payload/data
     * @return void
     *
     * @example
     * ```php
     * // Simple event
     * dispatch('user.registered', $user);
     *
     * // With array payload
     * dispatch('order.created', [
     *     'order' => $order,
     *     'user' => $user,
     * ]);
     *
     * // Event object
     * dispatch('payment.received', new PaymentReceivedEvent($payment));
     * ```
     */
    function dispatch(string $event, mixed $payload = null): void
    {
        Events::trigger($event, $payload);
    }
}

if (!function_exists('dispatchSync')) {
    /**
     * Dispatch an event synchronously (runs immediately).
     *
     * @param string $event The event name
     * @param mixed $payload The event payload/data
     * @return void
     *
     * @example
     * ```php
     * // Synchronous dispatch - waits for all listeners
     * dispatchSync('cache.cleared', ['keys' => $keys]);
     *
     * // Critical events that must run immediately
     * dispatchSync('security.breach', $alertData);
     * ```
     */
    function dispatchSync(string $event, mixed $payload = null): void
    {
        Events::triggerSync($event, $payload);
    }
}

if (!function_exists('event')) {
    /**
     * Dispatch an event (alias for dispatch).
     *
     * @param string $event The event name
     * @param mixed $payload The event payload/data
     * @return void
     *
     * @example
     * ```php
     * event('user.logged_in', $user);
     * ```
     */
    function event(string $event, mixed $payload = null): void
    {
        Events::trigger($event, $payload);
    }
}

if (!function_exists('listen')) {
    /**
     * Register an event listener.
     *
     * @param string $event The event name
     * @param callable|string $listener The listener callback or class
     * @param int $priority Higher priority executes first (default: 0)
     * @return void
     *
     * @example
     * ```php
     * // Closure listener
     * listen('user.registered', function ($user) {
     *     sendWelcomeEmail($user);
     * });
     *
     * // Class listener
     * listen('order.created', OrderCreatedListener::class);
     *
     * // With priority
     * listen('payment.received', AuditListener::class, priority: 100);
     * ```
     */
    function listen(string $event, callable|string $listener, int $priority = 0): void
    {
        Events::register($event, $listener, $priority);
    }
}

if (!function_exists('forget_event')) {
    /**
     * Remove event listener(s).
     *
     * @param string $event The event name
     * @param callable|string|null $listener Specific listener to remove (null = all)
     * @return void
     *
     * @example
     * ```php
     * // Remove specific listener
     * forget_event('user.registered', SendWelcomeEmail::class);
     *
     * // Remove all listeners for event
     * forget_event('user.registered');
     * ```
     */
    function forget_event(string $event, callable|string|null $listener = null): void
    {
        Events::forget($event, $listener);
    }
}

if (!function_exists('has_listeners')) {
    /**
     * Check if an event has any listeners.
     *
     * @param string $event The event name
     * @return bool
     *
     * @example
     * ```php
     * if (has_listeners('order.created')) {
     *     dispatch('order.created', $order);
     * }
     * ```
     */
    function has_listeners(string $event): bool
    {
        return Events::hasListeners($event);
    }
}
