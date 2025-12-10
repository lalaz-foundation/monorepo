<?php

declare(strict_types=1);

namespace Lalaz\Queue\Contracts;

/**
 * Interface for dispatching jobs to the queue.
 *
 * Segregated interface following ISP - clients that only need to
 * add jobs don't need to know about processing or stats methods.
 */
interface JobDispatcherInterface
{
    /**
     * Add a job to the queue.
     *
     * @param string $jobClass Class name implementing JobInterface
     * @param array $payload Arbitrary payload passed to handle()
     * @param string $queue Queue name
     * @param int $priority Lower number = higher priority
     * @param int|null $delay Delay in seconds before job becomes available
     * @param array $options Driver-specific options
     * @return bool True when enqueued successfully
     */
    public function add(
        string $jobClass,
        array $payload = [],
        string $queue = 'default',
        int $priority = 5,
        ?int $delay = null,
        array $options = [],
    ): bool;
}
