<?php

declare(strict_types=1);

namespace Lalaz\Runtime\Http\Exceptions\Renderers;

use Lalaz\Exceptions\ExceptionResponse;
use Lalaz\Runtime\Http\Exceptions\ExceptionOutputFormatter;
use Lalaz\Web\Http\Contracts\ExceptionRendererInterface;
use Lalaz\Web\Http\Request;
use Throwable;

/**
 * Fallback renderer for any unhandled exceptions.
 *
 * This renderer acts as a catch-all for exceptions not handled by
 * more specific renderers. Returns a generic 500 Internal Server Error
 * with minimal information in production, or detailed debug info
 * when debug mode is enabled.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
final class GenericExceptionRenderer implements ExceptionRendererInterface
{
    /**
     * Create a new generic exception renderer.
     *
     * @param ExceptionOutputFormatter $formatter The output formatter.
     */
    public function __construct(private ExceptionOutputFormatter $formatter)
    {
    }

    /**
     * Check if this renderer can handle the given exception.
     *
     * Always returns true as this is the fallback renderer.
     *
     * @param Throwable $e The exception to check.
     * @param Request $request The current request.
     * @return bool Always true.
     */
    public function canRender(Throwable $e, Request $request): bool
    {
        return true;
    }

    /**
     * Render a generic exception into a response.
     *
     * Returns a 500 Internal Server Error response. In debug mode,
     * includes exception type and message. In production, shows
     * only a generic error message.
     *
     * @param Throwable $e The exception to render.
     * @param Request $request The current request.
     * @return ExceptionResponse The rendered exception response.
     */
    public function render(Throwable $e, Request $request): ExceptionResponse
    {
        $debug = $this->formatter->isDebug();
        $message = $debug
            ? 'Unexpected exception: ' . get_class($e)
            : 'An unexpected error occurred';

        if ($request->wantsJson()) {
            $payload = [
                'error' => true,
                'message' => $message,
                'statusCode' => 500,
            ];

            if ($debug) {
                $payload['exception'] = [
                    'type' => get_class($e),
                    'message' => $e->getMessage(),
                ];
            }

            return new ExceptionResponse(500, [], $payload, true);
        }

        $plain = $message;
        if ($debug) {
            $plain .= PHP_EOL . $e->getMessage();
        }

        return new ExceptionResponse(500, [], $plain, false);
    }
}
