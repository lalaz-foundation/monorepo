<?php

declare(strict_types=1);

namespace Lalaz\Events;

use Lalaz\Events\Contracts\ListenerRegistryInterface;

/**
 * In-memory storage for event listeners.
 *
 * Following SRP - this class has a single responsibility:
 * managing the storage and retrieval of event listeners.
 */
class ListenerRegistry implements ListenerRegistryInterface
{
    /** @var array<string, array<int, array{listener: callable|EventListener|string, priority: int}>> */
    private array $listeners = [];

    /**
     * {@inheritdoc}
     */
    public function add(
        string $event,
        callable|EventListener|string $listener,
        int $priority = 0
    ): void {
        $this->listeners[$event][] = [
            'listener' => $listener,
            'priority' => $priority,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function remove(string $event, callable|EventListener|string|null $listener = null): void
    {
        if (!isset($this->listeners[$event])) {
            return;
        }

        if ($listener === null) {
            unset($this->listeners[$event]);
            return;
        }

        $this->listeners[$event] = array_values(array_filter(
            $this->listeners[$event],
            fn ($entry) => $entry['listener'] !== $listener
        ));

        // Clean up empty arrays
        if (empty($this->listeners[$event])) {
            unset($this->listeners[$event]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $event): bool
    {
        return !empty($this->listeners[$event]);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $event): array
    {
        return array_map(
            fn ($entry) => $entry['listener'],
            $this->getSorted($event)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getWithMetadata(string $event): array
    {
        return $this->getSorted($event);
    }

    /**
     * {@inheritdoc}
     */
    public function clear(?string $event = null): void
    {
        if ($event === null) {
            $this->listeners = [];
        } else {
            unset($this->listeners[$event]);
        }
    }

    /**
     * Get listeners sorted by priority (higher first).
     *
     * @return array<array{listener: callable|EventListener|string, priority: int}>
     */
    private function getSorted(string $event): array
    {
        if (!isset($this->listeners[$event])) {
            return [];
        }

        $listeners = $this->listeners[$event];
        usort($listeners, fn ($a, $b) => $b['priority'] <=> $a['priority']);

        return $listeners;
    }

    /**
     * Get count of listeners for an event.
     */
    public function count(string $event): int
    {
        return count($this->listeners[$event] ?? []);
    }

    /**
     * Get all registered events.
     *
     * @return array<string>
     */
    public function getEvents(): array
    {
        return array_keys($this->listeners);
    }
}
