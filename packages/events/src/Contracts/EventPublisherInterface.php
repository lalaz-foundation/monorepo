<?php

declare(strict_types=1);

namespace Lalaz\Events\Contracts;

/**
 * Interface for publishing/triggering events.
 *
 * Segregated interface following ISP - clients that only need to
 * publish events don't need to know about registration methods.
 */
interface EventPublisherInterface
{
    /**
     * Trigger an event (async by default if driver supports it).
     *
     * @param string $event The event name
     * @param mixed $data The event payload
     */
    public function trigger(string $event, mixed $data): void;

    /**
     * Trigger an event synchronously.
     *
     * @param string $event The event name
     * @param mixed $data The event payload
     */
    public function triggerSync(string $event, mixed $data): void;
}
