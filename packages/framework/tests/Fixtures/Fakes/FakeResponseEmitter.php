<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Fixtures\Fakes;

use Lalaz\Web\Http\Contracts\ResponseBodyEmitterInterface;
use Lalaz\Web\Http\Contracts\ResponseEmitterInterface;
use Lalaz\Web\Http\Response;

/**
 * Fake implementation of ResponseEmitterInterface for testing.
 *
 * Captures emitted responses for assertions.
 */
final class FakeResponseEmitter implements ResponseEmitterInterface, ResponseBodyEmitterInterface
{
    public ?Response $lastResponse = null;

    /** @var array<int, string> */
    public array $chunks = [];

    /** @var array<int, array{status: int, headers: array<string, string>, body: string}> */
    public array $emissions = [];

    public function emit(Response $response): void
    {
        $this->lastResponse = $response;
        $response->sendBody($this);
        $this->emissions[] = [
            'status' => $response->getStatusCode(),
            'headers' => $response->headers(),
            'body' => $response->body(),
        ];
    }

    public function write(string $chunk): void
    {
        $this->chunks[] = $chunk;
    }

    /**
     * Reset the emitter state for reuse between tests.
     */
    public function reset(): void
    {
        $this->lastResponse = null;
        $this->chunks = [];
        $this->emissions = [];
    }

    /**
     * Get the last emitted status code.
     */
    public function lastStatusCode(): ?int
    {
        if ($this->emissions === []) {
            return null;
        }

        return $this->emissions[array_key_last($this->emissions)]['status'];
    }

    /**
     * Get the last emitted body content.
     */
    public function lastBody(): string
    {
        return implode('', $this->chunks);
    }
}
