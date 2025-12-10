<?php

declare(strict_types=1);

namespace Lalaz\Events;

use Lalaz\Events\Contracts\EventJobFactoryInterface;

/**
 * Default factory for creating EventJob pending dispatches.
 *
 * Following DIP - QueueDriver depends on this factory interface,
 * not directly on EventJob class.
 */
class EventJobFactory implements EventJobFactoryInterface
{
    /** @var class-string<EventJob> */
    private string $jobClass;

    /**
     * @param class-string<EventJob>|null $jobClass The job class to use
     */
    public function __construct(?string $jobClass = null)
    {
        $this->jobClass = $jobClass ?? EventJob::class;
    }

    /**
     * {@inheritdoc}
     */
    public function create(string $queue, int $priority, ?int $delay = null): object
    {
        $jobClass = $this->jobClass;
        $dispatch = $jobClass::onQueue($queue)->priority($priority);

        if ($delay !== null) {
            $dispatch->delay($delay);
        }

        return $dispatch;
    }
}
