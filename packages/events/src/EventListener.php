<?php

declare(strict_types=1);

namespace Lalaz\Events;

/**
 * Base class for event listeners.
 * Implementors define subscribed events and handle payloads.
 */
abstract class EventListener
{
    /**
     * Return an array of event names this listener subscribes to.
     *
     * @return array<int, string>
     */
    abstract public function subscribers(): array;

    /**
     * Handle an event payload.
     *
     * @param mixed $event
     */
    abstract public function handle(mixed $event): void;
}
