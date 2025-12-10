<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Fixtures\Stubs;

use Lalaz\Web\Http\Contracts\MiddlewareInterface;
use Lalaz\Web\Http\Contracts\RequestInterface;
use Lalaz\Web\Http\Contracts\ResponseInterface;

/**
 * Stub middleware that tracks invocation order for testing.
 */
class StubMiddleware implements MiddlewareInterface
{
    /** @var array<int, string> */
    public static array $calls = [];

    public function __construct(private string $name)
    {
    }

    public static function reset(): void
    {
        self::$calls = [];
    }

    public static function log(string $label): void
    {
        self::$calls[] = $label;
    }

    public function handle(RequestInterface $request, ResponseInterface $response, callable $next): mixed
    {
        self::$calls[] = $this->name . ':before';
        $result = $next($request, $response);
        self::$calls[] = $this->name . ':after';
        return $result;
    }
}

/**
 * Stub middleware that short-circuits the request.
 */
class StubShortCircuitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private int $statusCode = 403,
        private string $message = 'Blocked',
    ) {
    }

    public function handle(RequestInterface $request, ResponseInterface $response, callable $next): mixed
    {
        $response->json(['message' => $this->message], $this->statusCode);
        // Does not call $next - short-circuits the pipeline
        return null;
    }
}
