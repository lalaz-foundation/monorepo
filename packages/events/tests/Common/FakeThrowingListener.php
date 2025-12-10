<?php declare(strict_types=1);

namespace Lalaz\Events\Tests\Common;

use Lalaz\Events\EventListener;

/**
 * Fake listener that throws an exception for testing error handling.
 *
 * @package lalaz/events
 */
class FakeThrowingListener extends EventListener
{
    private string $message;
    private string $exceptionClass;

    /**
     * Create a new throwing listener.
     */
    public function __construct(
        string $message = 'Test error',
        string $exceptionClass = \RuntimeException::class
    ) {
        $this->message = $message;
        $this->exceptionClass = $exceptionClass;
    }

    /**
     * {@inheritdoc}
     */
    public function subscribers(): array
    {
        return ['error.event'];
    }

    /**
     * {@inheritdoc}
     */
    public function handle(mixed $event): void
    {
        throw new ($this->exceptionClass)($this->message);
    }
}
