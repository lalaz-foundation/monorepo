<?php

declare(strict_types=1);

namespace Lalaz\Queue;

/**
 * Runs the queue processor once, delegated to QueueManager.
 */
class JobRunner
{
    private QueueManager $queueManager;

    public function __construct(QueueManager $queueManager)
    {
        $this->queueManager = $queueManager;
    }

    public function run(): void
    {
        $this->queueManager->processJobs();
    }
}
