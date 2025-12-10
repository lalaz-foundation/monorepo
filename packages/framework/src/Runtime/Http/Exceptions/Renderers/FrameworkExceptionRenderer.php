<?php

declare(strict_types=1);

namespace Lalaz\Runtime\Http\Exceptions\Renderers;

use Lalaz\Exceptions\ExceptionResponse;
use Lalaz\Exceptions\FrameworkException;
use Lalaz\Runtime\Http\Exceptions\ExceptionOutputFormatter;
use Lalaz\Web\Http\Contracts\ExceptionRendererInterface;
use Lalaz\Web\Http\Request;
use Throwable;

/**
 * Renderer for framework-specific exceptions.
 *
 * Handles FrameworkException instances (and subclasses) by rendering
 * them as 500 Internal Server Error responses. In production, shows
 * a generic error message; in debug mode, exposes the actual message.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
final class FrameworkExceptionRenderer implements ExceptionRendererInterface
{
    /**
     * Create a new framework exception renderer.
     *
     * @param ExceptionOutputFormatter $formatter The output formatter.
     */
    public function __construct(private ExceptionOutputFormatter $formatter)
    {
    }

    /**
     * Check if this renderer can handle the given exception.
     *
     * @param Throwable $e The exception to check.
     * @param Request $request The current request.
     * @return bool True if the exception is a FrameworkException.
     */
    public function canRender(Throwable $e, Request $request): bool
    {
        return $e instanceof FrameworkException;
    }

    /**
     * Render the framework exception into a response.
     *
     * Returns HTTP 500 Internal Server Error. In debug mode, includes
     * the actual exception message and context. In production, shows
     * a generic "internal error" message.
     *
     * @param Throwable $e The exception to render (must be FrameworkException).
     * @param Request $request The current request.
     * @return ExceptionResponse The rendered exception response.
     */
    public function render(Throwable $e, Request $request): ExceptionResponse
    {
        \assert($e instanceof FrameworkException);

        $context = $this->formatter->filterContext($e->getContext());
        $message = $this->formatter->isDebug()
            ? $e->getMessage()
            : 'An internal error occurred';

        if ($request->wantsJson()) {
            $payload = [
                'error' => true,
                'message' => $message,
                'statusCode' => 500,
                'context' => $context,
            ];

            return new ExceptionResponse(500, [], $payload, true);
        }

        return new ExceptionResponse(
            500,
            [],
            $this->formatter->buildPlainMessage($message, $context),
            false,
        );
    }
}
