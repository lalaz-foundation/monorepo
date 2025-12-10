<?php declare(strict_types=1);

namespace Lalaz\Queue\Tests\Common;

use Lalaz\Queue\Contracts\JobInterface;

/**
 * Fake job for testing.
 *
 * A simple job implementation that records execution for assertions.
 *
 * @package lalaz/queue
 */
class FakeJob implements JobInterface
{
    /**
     * Static storage for payloads received (for stateless assertions).
     *
     * @var array<int, array>
     */
    public static array $handledPayloads = [];

    /**
     * Instance storage for payloads.
     *
     * @var array<int, array>
     */
    private array $instancePayloads = [];

    /**
     * Execution count.
     */
    private int $executionCount = 0;

    /**
     * Reset static state (call in setUp).
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
        $this->instancePayloads[] = $payload;
        $this->executionCount++;
    }

    /**
     * Get all handled payloads from static storage.
     *
     * @return array<int, array>
     */
    public static function getHandledPayloads(): array
    {
        return self::$handledPayloads;
    }

    /**
     * Get last handled payload.
     */
    public static function getLastPayload(): ?array
    {
        return self::$handledPayloads[count(self::$handledPayloads) - 1] ?? null;
    }

    /**
     * Check if any job was handled.
     */
    public static function wasHandled(): bool
    {
        return !empty(self::$handledPayloads);
    }

    /**
     * Get execution count.
     */
    public function getExecutionCount(): int
    {
        return $this->executionCount;
    }

    /**
     * Get instance payloads.
     *
     * @return array<int, array>
     */
    public function getInstancePayloads(): array
    {
        return $this->instancePayloads;
    }
}
