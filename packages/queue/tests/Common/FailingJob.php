<?php declare(strict_types=1);

namespace Lalaz\Queue\Tests\Common;

use Lalaz\Queue\Contracts\JobInterface;
use RuntimeException;

/**
 * Failing job for testing error handling.
 *
 * Always throws an exception when executed.
 *
 * @package lalaz/queue
 */
class FailingJob implements JobInterface
{
    /**
     * Static storage for tracking attempts.
     *
     * @var array<int, array>
     */
    public static array $attemptedPayloads = [];

    /**
     * Exception message to throw.
     */
    private string $errorMessage;

    /**
     * Number of times to fail before succeeding (0 = always fail).
     */
    private int $failCount;

    /**
     * Current attempt number.
     */
    private static int $currentAttempt = 0;

    /**
     * Create a new failing job.
     *
     * @param string $errorMessage Exception message
     * @param int $failCount Number of times to fail (0 = always)
     */
    public function __construct(string $errorMessage = 'Job failed', int $failCount = 0)
    {
        $this->errorMessage = $errorMessage;
        $this->failCount = $failCount;
    }

    /**
     * Reset static state.
     */
    public static function reset(): void
    {
        self::$attemptedPayloads = [];
        self::$currentAttempt = 0;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(array $payload): void
    {
        self::$attemptedPayloads[] = $payload;
        self::$currentAttempt++;

        if ($this->failCount === 0 || self::$currentAttempt <= $this->failCount) {
            throw new RuntimeException($this->errorMessage);
        }
    }

    /**
     * Get attempted payloads.
     *
     * @return array<int, array>
     */
    public static function getAttemptedPayloads(): array
    {
        return self::$attemptedPayloads;
    }

    /**
     * Get current attempt count.
     */
    public static function getCurrentAttempt(): int
    {
        return self::$currentAttempt;
    }
}
