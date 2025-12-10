<?php

declare(strict_types=1);

namespace Lalaz\Runtime\Http\Exceptions\Renderers;

use Lalaz\Exceptions\ExceptionResponse;
use Lalaz\Exceptions\ValidationException;
use Lalaz\Runtime\Http\Exceptions\ExceptionOutputFormatter;
use Lalaz\Web\Http\Contracts\ExceptionRendererInterface;
use Lalaz\Web\Http\Request;
use Throwable;

/**
 * Renderer for validation exceptions.
 *
 * Handles ValidationException instances by extracting validation errors
 * and formatting them for the client. Returns HTTP 422 Unprocessable Entity
 * with detailed error information.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
final class ValidationExceptionRenderer implements ExceptionRendererInterface
{
    /**
     * Create a new validation exception renderer.
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
     * @return bool True if the exception is a ValidationException.
     */
    public function canRender(Throwable $e, Request $request): bool
    {
        return $e instanceof ValidationException;
    }

    /**
     * Render the validation exception into a response.
     *
     * Returns HTTP 422 with validation errors. JSON responses include
     * a structured 'errors' array. Plain text responses list errors
     * in a human-readable format.
     *
     * @param Throwable $e The exception to render (must be ValidationException).
     * @param Request $request The current request.
     * @return ExceptionResponse The rendered exception response.
     */
    public function render(Throwable $e, Request $request): ExceptionResponse
    {
        \assert($e instanceof ValidationException);

        $context = $this->formatter->filterContext($e->getContext());
        $wantsJson = $request->wantsJson();

        if ($wantsJson) {
            $payload = [
                'error' => true,
                'message' => $e->getMessage(),
                'statusCode' => 422,
                'errors' => $e->getErrors(),
                'context' => $context,
            ];

            return new ExceptionResponse(422, [], $payload, true);
        }

        $body =
            $e->getMessage() .
            PHP_EOL .
            $this->formatter->formatArray($e->getErrors()) .
            ($context === [] ? '' : PHP_EOL . $this->formatter->formatArray($context));

        return new ExceptionResponse(422, [], $body, false);
    }
}
