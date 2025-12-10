<?php

declare(strict_types=1);

namespace Lalaz\Queue;

use Lalaz\Queue\Contracts\JobDispatcherInterface;

/**
 * Fluent builder for dispatching jobs with custom options.
 *
 * Following DIP - supports injectable dispatcher for testing.
 */
class PendingDispatch
{
    private string $queue = 'default';
    private int $priority = 5;
    private ?int $delay = null;
    private array $options = [];

    /**
     * Custom dispatcher for testing (DIP).
     */
    private static ?JobDispatcherInterface $testDispatcher = null;

    /**
     * Custom dispatcher resolver for testing.
     *
     * @var callable|null
     */
    private static $dispatcherResolver = null;

    public function __construct(private string $jobClass, ?string $queue = null)
    {
        if ($queue) {
            $this->queue = $queue;
        }
    }

    /**
     * Set a test dispatcher for all pending dispatches.
     */
    public static function setTestDispatcher(?JobDispatcherInterface $dispatcher): void
    {
        self::$testDispatcher = $dispatcher;
    }

    /**
     * Set a dispatcher resolver callable.
     *
     * @param callable|null $resolver fn(): QueueManager|JobDispatcherInterface
     */
    public static function setDispatcherResolver(?callable $resolver): void
    {
        self::$dispatcherResolver = $resolver;
    }

    /**
     * Get the dispatcher instance.
     */
    protected function getDispatcher(): QueueManager|JobDispatcherInterface
    {
        // 1. Test dispatcher (for unit tests)
        if (self::$testDispatcher !== null) {
            return self::$testDispatcher;
        }

        // 2. Custom resolver (for DI containers)
        if (self::$dispatcherResolver !== null) {
            return (self::$dispatcherResolver)();
        }

        // 3. Default: use container
        return resolve(QueueManager::class);
    }

    public function onQueue(string $queue): self
    {
        $this->queue = $queue;
        return $this;
    }

    public function priority(int $priority): self
    {
        $this->priority = max(0, min(10, $priority));
        return $this;
    }

    public function delay(int $seconds): self
    {
        $this->delay = $seconds;
        return $this;
    }

    public function withOptions(array $options): self
    {
        $this->options = array_merge($this->options, $options);
        return $this;
    }

    public function maxAttempts(int $attempts): self
    {
        $this->options['max_attempts'] = $attempts;
        return $this;
    }

    public function timeout(int $seconds): self
    {
        $this->options['timeout'] = $seconds;
        return $this;
    }

    public function backoff(string $strategy): self
    {
        $this->options['backoff_strategy'] = $strategy;
        return $this;
    }

    public function retryAfter(int $seconds): self
    {
        $this->options['retry_delay'] = $seconds;
        return $this;
    }

    public function tags(array $tags): self
    {
        $this->options['tags'] = $tags;
        return $this;
    }

    public function dispatch(array $payload = []): bool
    {
        $dispatcher = $this->getDispatcher();

        // If dispatcher only implements JobDispatcherInterface (not QueueManager)
        if ($dispatcher instanceof JobDispatcherInterface && !method_exists($dispatcher, 'addJob')) {
            return $dispatcher->add(
                $this->jobClass,
                $payload,
                $this->queue,
                $this->priority,
                $this->delay,
                $this->options
            );
        }

        return $dispatcher->addJob(
            jobClass: $this->jobClass,
            payload: $payload,
            queue: $this->queue,
            priority: $this->priority,
            delay: $this->delay,
            options: $this->options,
        );
    }
}
