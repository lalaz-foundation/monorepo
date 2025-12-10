<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Fixtures\Fakes;

use Lalaz\Web\Http\Contracts\RequestFactoryInterface;
use Lalaz\Web\Http\Contracts\RequestInterface;
use Lalaz\Web\Http\Request;

/**
 * Fake implementation of RequestFactoryInterface for testing.
 *
 * Allows tests to inject pre-configured Request objects.
 */
final class FakeRequestFactory implements RequestFactoryInterface
{
    public ?RequestInterface $next = null;

    public function fromGlobals(): RequestInterface
    {
        if ($this->next === null) {
            throw new \RuntimeException('No fake request configured.');
        }

        return $this->next;
    }

    /**
     * Convenience method to set the next request.
     */
    public function willReturn(RequestInterface $request): self
    {
        $this->next = $request;
        return $this;
    }
}
