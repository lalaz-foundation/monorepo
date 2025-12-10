<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Fixtures\Fakes;

use Lalaz\Web\Http\Contracts\RequestInterface;
use Lalaz\Web\Http\Contracts\ResponseFactoryInterface;
use Lalaz\Web\Http\Contracts\ResponseInterface;
use Lalaz\Web\Http\Response;

/**
 * Fake implementation of ResponseFactoryInterface for testing.
 *
 * Captures requests used to create responses.
 */
final class FakeResponseFactory implements ResponseFactoryInterface
{
    /** @var array<int, RequestInterface> */
    public array $requests = [];

    public ?RequestInterface $lastRequest = null;

    public function create(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        $this->lastRequest = $request;

        return new Response($request->header('Host', 'localhost') ?? 'localhost');
    }

    /**
     * Reset the factory state for reuse between tests.
     */
    public function reset(): void
    {
        $this->requests = [];
        $this->lastRequest = null;
    }
}
