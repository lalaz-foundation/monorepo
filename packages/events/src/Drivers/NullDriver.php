<?php

declare(strict_types=1);

namespace Lalaz\Events\Drivers;

use Lalaz\Events\Contracts\EventDriverInterface;

/**
 * Null event driver - does nothing with events.
 *
 * Useful for:
 * - Testing (when you don't want side effects)
 * - Disabling events in certain environments
 * - Mocking in unit tests
 */
class NullDriver implements EventDriverInterface
{
    /** @var array<array{event: string, data: mixed, options: array}> */
    private array $published = [];

    private bool $recordEvents;

    public function __construct(bool $recordEvents = false)
    {
        $this->recordEvents = $recordEvents;
    }

    /**
     * {@inheritdoc}
     */
    public function publish(string $event, mixed $data, array $options = []): void
    {
        if ($this->recordEvents) {
            $this->published[] = [
                'event' => $event,
                'data' => $data,
                'options' => $options,
            ];
        }
        // Do nothing - that's the point!
    }

    /**
     * {@inheritdoc}
     */
    public function isAvailable(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'null';
    }

    /**
     * Get all recorded events (only works if recordEvents is true).
     *
     * @return array<array{event: string, data: mixed, options: array}>
     */
    public function getPublished(): array
    {
        return $this->published;
    }

    /**
     * Check if a specific event was published.
     */
    public function wasPublished(string $event): bool
    {
        foreach ($this->published as $record) {
            if ($record['event'] === $event) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get all publications of a specific event.
     *
     * @return array<array{event: string, data: mixed, options: array}>
     */
    public function getPublicationsOf(string $event): array
    {
        return array_filter(
            $this->published,
            fn ($record) => $record['event'] === $event
        );
    }

    /**
     * Clear all recorded events.
     */
    public function clear(): void
    {
        $this->published = [];
    }

    /**
     * Get the count of published events.
     */
    public function count(): int
    {
        return count($this->published);
    }
}
