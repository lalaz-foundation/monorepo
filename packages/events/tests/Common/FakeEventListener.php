<?php declare(strict_types=1);

namespace Lalaz\Events\Tests\Common;

use Lalaz\Events\EventListener;

/**
 * Fake event listener for testing.
 *
 * Records all received events for later assertion.
 *
 * @package lalaz/events
 */
class FakeEventListener extends EventListener
{
    /**
     * @var array<int, string>
     */
    private array $subscribedEvents;

    /**
     * @var array<int, mixed>
     */
    private array $receivedEvents = [];

    /**
     * @var int
     */
    private int $handleCount = 0;

    /**
     * Create a new fake listener.
     *
     * @param array<int, string> $events Events to subscribe to
     */
    public function __construct(array $events = ['test.event'])
    {
        $this->subscribedEvents = $events;
    }

    /**
     * {@inheritdoc}
     */
    public function subscribers(): array
    {
        return $this->subscribedEvents;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(mixed $event): void
    {
        $this->receivedEvents[] = $event;
        $this->handleCount++;
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
     * Get the last received event.
     */
    public function getLastEvent(): mixed
    {
        return end($this->receivedEvents) ?: null;
    }

    /**
     * Get the number of times handle() was called.
     */
    public function getHandleCount(): int
    {
        return $this->handleCount;
    }

    /**
     * Check if a specific event data was received.
     */
    public function hasReceived(mixed $data): bool
    {
        return in_array($data, $this->receivedEvents, true);
    }

    /**
     * Reset the listener state.
     */
    public function reset(): void
    {
        $this->receivedEvents = [];
        $this->handleCount = 0;
    }
}
