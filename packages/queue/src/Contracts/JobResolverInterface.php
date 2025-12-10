<?php

declare(strict_types=1);

namespace Lalaz\Queue\Contracts;

/**
 * Interface for resolving job class instances.
 *
 * Following DIP - job execution depends on this abstraction
 * instead of directly using container or instantiation.
 */
interface JobResolverInterface
{
    /**
     * Resolve a job class to an instance.
     *
     * @param string $jobClass The fully qualified class name
     * @return JobInterface The resolved job instance
     * @throws \RuntimeException If resolution fails
     */
    public function resolve(string $jobClass): JobInterface;
}
