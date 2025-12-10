<?php

declare(strict_types=1);

namespace Lalaz\Queue;

use Lalaz\Queue\Contracts\JobDispatcherInterface;
use Lalaz\Queue\Contracts\JobInterface;

/**
 * Base job class with fluent dispatch API.
 *
 * Following DIP - supports injectable dispatcher for testing.
 */
abstract class Job implements JobInterface
{
    protected string $queue = 'default';

    protected int $priority = 5;

    protected int $maxAttempts = 3;

    protected int $timeout = 300;

    protected string $backoffStrategy = 'exponential';

    protected int $retryDelay = 60;

    protected array $tags = [];

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

    /**
     * Set a test dispatcher for all jobs.
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
    protected static function getDispatcher(): QueueManager|JobDispatcherInterface
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

    /**
     * Dispatch the job asynchronously using QueueManager defaults for queue/priority/etc.
     */
    public static function dispatch(array $payload = []): bool
    {
        $instance = new static();
        $dispatcher = static::getDispatcher();

        // If dispatcher only implements JobDispatcherInterface (not QueueManager)
        if ($dispatcher instanceof JobDispatcherInterface && !method_exists($dispatcher, 'addJob')) {
            return $dispatcher->add(
                static::class,
                $payload,
                $instance->queue,
                $instance->priority,
                null,
                [
                    'max_attempts' => $instance->maxAttempts,
                    'timeout' => $instance->timeout,
                    'backoff_strategy' => $instance->backoffStrategy,
                    'retry_delay' => $instance->retryDelay,
                    'tags' => $instance->tags,
                ]
            );
        }

        return $dispatcher->addJob(
            jobClass: static::class,
            payload: $payload,
            queue: $instance->queue,
            priority: $instance->priority,
            delay: null,
            options: [
                'max_attempts' => $instance->maxAttempts,
                'timeout' => $instance->timeout,
                'backoff_strategy' => $instance->backoffStrategy,
                'retry_delay' => $instance->retryDelay,
                'tags' => $instance->tags,
            ],
        );
    }

    public static function onQueue(string $queue): PendingDispatch
    {
        return new PendingDispatch(static::class, $queue);
    }

    /**
     * Create a pending dispatch with custom priority.
     */
    public static function withPriority(int $priority): PendingDispatch
    {
        $dispatch = new PendingDispatch(static::class);
        return $dispatch->priority($priority);
    }

    /**
     * Create a pending dispatch with delay in seconds.
     */
    public static function later(int $seconds): PendingDispatch
    {
        $dispatch = new PendingDispatch(static::class);
        return $dispatch->delay($seconds);
    }

    /**
     * Execute the job immediately in the current process (no queue required).
     */
    public static function dispatchSync(array $payload = []): bool
    {
        if (!class_exists(static::class)) {
            error_log("Job class '" . static::class . "' does not exist");
            return false;
        }

        $jobInstance = new static();

        if (!($jobInstance instanceof JobInterface)) {
            error_log(
                "Job class '" . static::class . "' must implement JobInterface",
            );
            return false;
        }

        if (!method_exists($jobInstance, 'handle')) {
            error_log(
                "Job class '" .
                    static::class .
                    "' must implement handle() method",
            );
            return false;
        }

        try {
            $jobInstance->handle($payload);
            return true;
        } catch (\Throwable $e) {
            error_log(
                "Failed to execute job '" .
                    static::class .
                    "' synchronously: " .
                    $e->getMessage(),
            );
            return false;
        }
    }
}
