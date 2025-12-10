<?php

declare(strict_types=1);

namespace Lalaz\Events\Tests\Common;

use Lalaz\Events\EventListener;

/**
 * Fake multi-event listener for testing scenarios with multiple subscriptions.
 *
 * @package lalaz/events
 */
class FakeMultiEventListener extends EventListener
{
    /**
     * @var array<int, mixed>
     */
    private array $receivedEvents = [];

    /**
     * @var array<string, array<int, mixed>>
     */
    private array $receivedByEvent = [];

    /**
     * {@inheritdoc}
     */
    public function subscribers(): array
    {
        return ['user.created', 'user.updated', 'user.deleted'];
    }

    /**
     * {@inheritdoc}
     */
    public function handle(mixed $event): void
    {
        $this->receivedEvents[] = $event;

        // Note: In real usage, we'd need to know which event triggered this
        // For testing, we store by event data key if available
        $eventName = $event['__event_name'] ?? 'unknown';
        $this->receivedByEvent[$eventName][] = $event;
    }

    /**
     * Get all received events.
     *
     * @return array<int, mixed>
     */
    public function getReceivedEvents(): array
    {
        return $this->receivedEvents;
    }

    /**
     * Get events received for a specific event name.
     *
     * @return array<int, mixed>
     */
    public function getEventsFor(string $eventName): array
    {
        return $this->receivedByEvent[$eventName] ?? [];
    }

    /**
     * Get all received events by event name.
     *
     * @return array<string, array<int, mixed>>
     */
    public function getAllReceived(): array
    {
        return $this->receivedByEvent;
    }

    /**
     * Get total count of all received events.
     */
    public function getTotalCount(): int
    {
        return count($this->receivedEvents);
    }

    /**
     * Reset the listener state.
     */
    public function reset(): void
    {
        $this->receivedEvents = [];
        $this->receivedByEvent = [];
    }
}
