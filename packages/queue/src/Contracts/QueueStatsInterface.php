<?php

declare(strict_types=1);

namespace Lalaz\Queue\Contracts;

/**
 * Interface for retrieving queue statistics.
 *
 * Segregated interface following ISP - clients that only need
 * statistics don't need to know about job operations.
 */
interface QueueStatsInterface
{
    /**
     * Get queue statistics.
     *
     * @param string|null $queue Specific queue name or null for all queues
     * @return array Statistics containing counts by status
     */
    public function getStats(?string $queue = null): array;
}
