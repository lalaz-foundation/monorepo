<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Fixtures\Stubs;

use Lalaz\Web\Http\Contracts\ExceptionRendererInterface;
use Lalaz\Web\Http\Contracts\ExceptionReporterInterface;
use Lalaz\Exceptions\ExceptionResponse;
use Lalaz\Web\Http\Request;
use Throwable;

/**
 * Stub exception for testing custom exception handling.
 */
class StubException extends \RuntimeException
{
}

/**
 * Stub exception renderer for testing custom renderers.
 */
class StubExceptionRenderer implements ExceptionRendererInterface
{
    public function canRender(Throwable $e, Request $request): bool
    {
        return $e instanceof StubException;
    }

    public function render(Throwable $e, Request $request): ExceptionResponse
    {
        return new ExceptionResponse(
            555,
            [],
            'stub-rendered',
            false,
        );
    }
}

/**
 * Stub exception reporter for testing custom reporters.
 */
class StubExceptionReporter implements ExceptionReporterInterface
{
    /** @var array<int, array{exception: Throwable, context: array<string, mixed>}> */
    public static array $reported = [];

    public static function reset(): void
    {
        self::$reported = [];
    }

    public function report(Throwable $e, Request $request, array $context = []): void
    {
        self::$reported[] = [
            'exception' => $e,
            'context' => $context,
        ];
    }
}

/**
 * Misconfigured renderer stub (does not implement interface).
 */
class MisconfiguredRendererStub
{
}

/**
 * Misconfigured reporter stub (does not implement interface).
 */
class MisconfiguredReporterStub
{
}
