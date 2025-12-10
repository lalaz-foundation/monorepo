<?php

declare(strict_types=1);

namespace Lalaz\Runtime\Http\Exceptions\Renderers;

use Lalaz\Exceptions\ExceptionResponse;
use Lalaz\Exceptions\HttpException;
use Lalaz\Runtime\Http\Exceptions\ExceptionOutputFormatter;
use Lalaz\Web\Http\Contracts\ExceptionRendererInterface;
use Lalaz\Web\Http\Request;
use Throwable;

/**
 * Renderer for HTTP-specific exceptions.
 *
 * Handles HttpException instances by extracting their status code,
 * headers, and context to build appropriate error responses.
 * Supports both JSON and plain text output based on request Accept header.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
final class HttpExceptionRenderer implements ExceptionRendererInterface
{
    /**
     * Create a new HTTP exception renderer.
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
     * @return bool True if the exception is an HttpException.
     */
    public function canRender(Throwable $e, Request $request): bool
    {
        return $e instanceof HttpException;
    }

    /**
     * Render the HTTP exception into a response.
     *
     * Builds a response with the exception's status code and headers.
     * Returns JSON or plain text based on the request's Accept header.
     *
     * @param Throwable $e The exception to render (must be HttpException).
     * @param Request $request The current request.
     * @return ExceptionResponse The rendered exception response.
     */
    public function render(Throwable $e, Request $request): ExceptionResponse
    {
        \assert($e instanceof HttpException);

        $context = $this->formatter->filterContext($e->getContext());
        $wantsJson = $request->wantsJson();

        if ($wantsJson) {
            $payload = [
                'error' => true,
                'message' => $e->getMessage(),
                'statusCode' => $e->getStatusCode(),
                'context' => $context,
            ];

            return new ExceptionResponse(
                $e->getStatusCode(),
                $e->getHeaders(),
                $payload,
                true,
            );
        }

        return new ExceptionResponse(
            $e->getStatusCode(),
            $e->getHeaders(),
            $this->formatter->buildPlainMessage($e->getMessage(), $context),
            false,
        );
    }
}
