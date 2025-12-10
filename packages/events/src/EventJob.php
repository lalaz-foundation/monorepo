<?php

declare(strict_types=1);

namespace Lalaz\Events;

use Lalaz\Events\Contracts\EventPublisherInterface;
use Lalaz\Queue\Job;
use Lalaz\Runtime\Application;

/**
 * Queue job wrapper to dispatch events asynchronously via the queue system.
 *
 * Following SOLID:
 * - DIP: Can inject EventPublisherInterface instead of using service locator
 * - SRP: Only handles dispatching event from queue payload
 */
class EventJob extends Job
{
    protected string $queue = 'events';

    protected int $priority = 9;

    protected int $maxAttempts = 5;

    protected int $timeout = 60;

    protected int $retryDelay = 30;

    /**
     * Optional injected publisher for testing.
     */
    private static ?EventPublisherInterface $testPublisher = null;

    /**
     * Resolver callable for getting the event publisher.
     *
     * @var callable|null
     */
    private static $publisherResolver = null;

    public function handle(array $payload): void
    {
        $eventName = $payload['event_name'] ?? null;

        $eventData =
            isset($payload['event_data']) && \is_string($payload['event_data'])
                ? json_decode($payload['event_data'], true)
                : null;

        if ($eventName === null) {
            return;
        }

        $publisher = $this->resolvePublisher();
        if ($publisher !== null) {
            $publisher->triggerSync($eventName, $eventData ?? []);
        }
    }

    /**
     * Resolve the event publisher.
     */
    protected function resolvePublisher(): ?EventPublisherInterface
    {
        // 1. Use test publisher if set
        if (self::$testPublisher !== null) {
            return self::$testPublisher;
        }

        // 2. Use custom resolver if set
        if (self::$publisherResolver !== null) {
            return (self::$publisherResolver)();
        }

        // 3. Fall back to Application context
        if (!class_exists(Application::class)) {
            return null;
        }

        $hub = Application::context()->events();
        return $hub instanceof EventPublisherInterface ? $hub : null;
    }

    /**
     * Set a test publisher for testing purposes.
     *
     * @param EventPublisherInterface|null $publisher
     */
    public static function setTestPublisher(?EventPublisherInterface $publisher): void
    {
        self::$testPublisher = $publisher;
    }

    /**
     * Set a custom publisher resolver.
     *
     * @param callable|null $resolver fn(): EventPublisherInterface|null
     */
    public static function setPublisherResolver(?callable $resolver): void
    {
        self::$publisherResolver = $resolver;
    }

    /**
     * Reset test state (call in test tearDown).
     */
    public static function resetTestState(): void
    {
        self::$testPublisher = null;
        self::$publisherResolver = null;
    }
}
