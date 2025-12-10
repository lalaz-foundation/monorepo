<?php

declare(strict_types=1);

namespace Lalaz\Events;

use Lalaz\Events\Contracts\EventDispatcherInterface;
use Lalaz\Runtime\Application;

/**
 * Static helper facade for registering and triggering events.
 *
 * Provides a convenient static API to the EventHub instance.
 */
class Events
{
    private static ?EventDispatcherInterface $instance = null;

    /**
     * Set the event dispatcher instance.
     */
    public static function setInstance(?EventDispatcherInterface $instance): void
    {
        self::$instance = $instance;
    }

    /**
     * Get the event dispatcher instance.
     */
    public static function getInstance(): ?EventDispatcherInterface
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        // Fallback to Application context
        if (class_exists(Application::class)) {
            $context = Application::context();
            $events = $context->events();
            if ($events instanceof EventDispatcherInterface) {
                return $events;
            }
        }

        return null;
    }

    /**
     * Register a listener for an event name.
     *
     * @param string $eventName The event name
     * @param callable|EventListener|string $listener The listener
     * @param int $priority Higher priority executes first (default: 0)
     */
    public static function register(
        string $eventName,
        callable|EventListener|string $listener,
        int $priority = 0,
    ): void {
        $hub = self::getInstance();

        if ($hub !== null) {
            $hub->register($eventName, $listener, $priority);
        }
    }

    /**
     * Remove a listener from an event.
     */
    public static function forget(
        string $eventName,
        callable|EventListener|string|null $listener = null
    ): void {
        $hub = self::getInstance();

        if ($hub !== null) {
            $hub->forget($eventName, $listener);
        }
    }

    /**
     * Trigger an event asynchronously (queued if enabled).
     */
    public static function trigger(string $eventName, mixed $event): void
    {
        $hub = self::getInstance();

        if ($hub !== null) {
            $hub->trigger($eventName, $event);
        }
    }

    /**
     * Trigger an event synchronously.
     */
    public static function triggerSync(string $eventName, mixed $event): void
    {
        $hub = self::getInstance();

        if ($hub !== null) {
            $hub->triggerSync($eventName, $event);
        }
    }

    /**
     * Check if an event has any listeners.
     */
    public static function hasListeners(string $eventName): bool
    {
        $hub = self::getInstance();

        return $hub !== null && $hub->hasListeners($eventName);
    }

    /**
     * Get all listeners for an event.
     *
     * @return array<callable|EventListener|string>
     */
    public static function getListeners(string $eventName): array
    {
        $hub = self::getInstance();

        if ($hub === null) {
            return [];
        }

        return $hub->getListeners($eventName);
    }
}
