<?php

declare(strict_types=1);

namespace Lalaz\Queue\Tests\Common;

use Lalaz\Queue\Job;

/**
 * Dispatchable job for testing dispatch functionality.
 *
 * Extends the abstract Job class for testing static dispatch methods.
 *
 * @package lalaz/queue
 */
class DispatchableJob extends Job
{
    /**
     * Static storage for payloads received.
     *
     * @var array<int, array>
     */
    public static array $handledPayloads = [];

    /**
     * The payload for this job instance.
     */
    protected array $payload = [];

    /**
     * Create a new job instance.
     */
    public function __construct(array $payload = [])
    {
        $this->payload = $payload;
    }

    /**
     * Reset static state.
     */
    public static function reset(): void
    {
        self::$handledPayloads = [];
    }

    /**
     * {@inheritdoc}
     */
    public function handle(array $payload): void
    {
        self::$handledPayloads[] = $payload;
    }

    /**
     * Get the payload.
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * Get handled payloads.
     *
     * @return array<int, array>
     */
    public static function getHandledPayloads(): array
    {
        return self::$handledPayloads;
    }

    /**
     * Check if job was handled.
     */
    public static function wasHandled(): bool
    {
        return !empty(self::$handledPayloads);
    }
}
