<?php

declare(strict_types=1);

namespace Lalaz\Scheduler;

use Lalaz\Scheduler\Mutex\MutexInterface;

/**
 * ScheduledJob - A scheduled queue job.
 *
 * @package lalaz/scheduler
 */
class ScheduledJob extends ScheduledEvent
{
    /**
     * The job instance.
     */
    private object $job;

    /**
     * The queue name.
     */
    private ?string $queue;

    /**
     * Creates a new scheduled job.
     *
     * @param object $job The job instance
     * @param string|null $queue The queue name
     * @param MutexInterface|null $mutex
     * @param bool $distributedMode
     */
    public function __construct(
        object $job,
        ?string $queue = null,
        ?MutexInterface $mutex = null,
        bool $distributedMode = false
    ) {
        parent::__construct($mutex, $distributedMode);

        $this->job = $job;
        $this->queue = $queue;
    }

    /**
     * {@inheritdoc}
     */
    public function run(): mixed
    {
        // Dispatch the job to the queue
        if (function_exists('dispatch')) {
            $dispatch = dispatch($this->job);

            if ($this->queue !== null && method_exists($dispatch, 'onQueue')) {
                $dispatch->onQueue($this->queue);
            }

            return $dispatch;
        }

        // Fallback: execute job directly if no queue system
        if (method_exists($this->job, 'handle')) {
            return $this->job->handle();
        }

        throw new \RuntimeException(
            'Unable to dispatch job: no queue system available and job has no handle() method'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function mutexName(): string
    {
        return 'job:' . sha1(get_class($this->job) . serialize($this->job));
    }

    /**
     * {@inheritdoc}
     */
    public function getSummary(): string
    {
        $className = get_class($this->job);
        $shortName = class_basename($className);

        $summary = "Job: {$shortName}";

        if ($this->queue !== null) {
            $summary .= " (queue: {$this->queue})";
        }

        return $summary;
    }

    /**
     * Get the job instance.
     *
     * @return object
     */
    public function getJob(): object
    {
        return $this->job;
    }

    /**
     * Get the queue name.
     *
     * @return string|null
     */
    public function getQueue(): ?string
    {
        return $this->queue;
    }
}

/**
 * Get the class basename.
 *
 * @param string $class
 * @return string
 */
function class_basename(string $class): string
{
    $parts = explode('\\', $class);
    return end($parts);
}
