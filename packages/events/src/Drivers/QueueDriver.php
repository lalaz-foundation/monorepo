<?php

declare(strict_types=1);

namespace Lalaz\Events\Drivers;

use Lalaz\Events\Contracts\EventDriverInterface;
use Lalaz\Events\Contracts\EventJobFactoryInterface;
use Lalaz\Events\Contracts\QueueAvailabilityCheckerInterface;
use Lalaz\Events\EventJobFactory;
use Lalaz\Events\QueueAvailabilityChecker;

/**
 * Queue-based event driver - dispatches events via the Queue package.
 *
 * Events are enqueued as jobs and processed asynchronously by queue workers.
 * This is useful for:
 * - Offloading heavy event processing
 * - Ensuring event delivery with retries
 * - Scaling event processing across multiple workers
 *
 * Following SOLID:
 * - DIP: Depends on EventJobFactoryInterface and QueueAvailabilityCheckerInterface
 * - OCP: Behavior can be extended via dependency injection
 */
class QueueDriver implements EventDriverInterface
{
    private string $queue;
    private int $priority;
    private ?int $delay;
    private EventJobFactoryInterface $jobFactory;
    private QueueAvailabilityCheckerInterface $availabilityChecker;

    /** @var callable|null Custom dispatcher for testing */
    private $dispatcher;

    public function __construct(
        string $queue = 'events',
        int $priority = 9,
        ?int $delay = null,
        ?callable $dispatcher = null,
        ?EventJobFactoryInterface $jobFactory = null,
        ?QueueAvailabilityCheckerInterface $availabilityChecker = null
    ) {
        $this->queue = $queue;
        $this->priority = $priority;
        $this->delay = $delay;
        $this->dispatcher = $dispatcher;
        $this->jobFactory = $jobFactory ?? new EventJobFactory();
        $this->availabilityChecker = $availabilityChecker ?? new QueueAvailabilityChecker();
    }

    /**
     * {@inheritdoc}
     */
    public function publish(string $event, mixed $data, array $options = []): void
    {
        $queue = $options['queue'] ?? $this->queue;
        $priority = $options['priority'] ?? $this->priority;
        $delay = $options['delay'] ?? $this->delay;

        $payload = $this->buildPayload($event, $data);

        if ($this->dispatcher !== null) {
            ($this->dispatcher)($payload, $queue, $priority, $delay);
            return;
        }

        $this->dispatchToQueue($payload, $queue, $priority, $delay);
    }

    /**
     * Build the event payload.
     *
     * @return array{event_name: string, event_data: string, published_at: string}
     */
    protected function buildPayload(string $event, mixed $data): array
    {
        return [
            'event_name' => $event,
            'event_data' => \is_string($data) ? $data : json_encode($data),
            'published_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Dispatch the payload to the queue system.
     */
    protected function dispatchToQueue(array $payload, string $queue, int $priority, ?int $delay): void
    {
        $dispatch = $this->jobFactory->create($queue, $priority, $delay);
        $dispatch->dispatch($payload);
    }

    /**
     * {@inheritdoc}
     */
    public function isAvailable(): bool
    {
        return $this->availabilityChecker->isAvailable();
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'queue';
    }

    /**
     * Get the queue name used by this driver.
     */
    public function getQueue(): string
    {
        return $this->queue;
    }

    /**
     * Get the default priority.
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Get the default delay.
     */
    public function getDelay(): ?int
    {
        return $this->delay;
    }

    /**
     * Get the job factory instance.
     */
    public function getJobFactory(): EventJobFactoryInterface
    {
        return $this->jobFactory;
    }

    /**
     * Get the availability checker instance.
     */
    public function getAvailabilityChecker(): QueueAvailabilityCheckerInterface
    {
        return $this->availabilityChecker;
    }
}
