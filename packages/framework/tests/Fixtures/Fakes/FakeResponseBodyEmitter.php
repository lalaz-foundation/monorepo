<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Fixtures\Fakes;

use Lalaz\Web\Http\Contracts\ResponseBodyEmitterInterface;

/**
 * Fake implementation of ResponseBodyEmitterInterface for testing.
 *
 * Captures written chunks for assertions.
 */
final class FakeResponseBodyEmitter implements ResponseBodyEmitterInterface
{
    /** @var array<int, string> */
    public array $chunks = [];

    public function write(string $chunk): void
    {
        $this->chunks[] = $chunk;
    }

    /**
     * Get all chunks as a single string.
     */
    public function content(): string
    {
        return implode('', $this->chunks);
    }

    /**
     * Reset the emitter state for reuse between tests.
     */
    public function reset(): void
    {
        $this->chunks = [];
    }
}
