<?php

declare(strict_types=1);

namespace Lalaz\Events;

use Lalaz\Events\Contracts\QueueAvailabilityCheckerInterface;
use Lalaz\Queue\QueueManager;

/**
 * Default queue availability checker using QueueManager.
 *
 * Following DIP - QueueDriver depends on the interface,
 * not directly on QueueManager static methods.
 */
class QueueAvailabilityChecker implements QueueAvailabilityCheckerInterface
{
    /**
     * {@inheritdoc}
     */
    public function isAvailable(): bool
    {
        return class_exists(QueueManager::class) && QueueManager::isEnabled();
    }
}
