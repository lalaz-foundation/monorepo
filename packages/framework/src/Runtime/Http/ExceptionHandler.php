<?php

declare(strict_types=1);

namespace Lalaz\Runtime\Http;

use Lalaz\Exceptions\Contracts\ContextualExceptionInterface;
use Lalaz\Exceptions\ExceptionResponse;
use Lalaz\Runtime\Http\Exceptions\ExceptionOutputFormatter;
use Lalaz\Runtime\Http\Exceptions\Renderers\FrameworkExceptionRenderer;
use Lalaz\Runtime\Http\Exceptions\Renderers\GenericExceptionRenderer;
use Lalaz\Runtime\Http\Exceptions\Renderers\HttpExceptionRenderer;
use Lalaz\Runtime\Http\Exceptions\Renderers\ValidationExceptionRenderer;
use Lalaz\Runtime\Http\Exceptions\Reporters\PhpErrorLogReporter;
use Lalaz\Runtime\Http\Exceptions\Reporters\PsrLoggerExceptionReporter;
use Lalaz\Web\Http\Contracts\ExceptionRendererInterface;
use Lalaz\Web\Http\Contracts\ExceptionReporterInterface;
use Lalaz\Web\Http\Contracts\ExtensibleExceptionHandlerInterface;
use Lalaz\Web\Http\Request;
use Lalaz\Web\Http\Response;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Extensible HTTP exception handler using strategy pattern.
 *
 * This handler delegates exception rendering to specialized renderers
 * and exception reporting to configurable reporters. It supports:
 * - Multiple renderers with priority ordering
 * - Multiple reporters for logging exceptions
 * - Debug mode for detailed error output
 * - Safe context filtering for production
 *
 * Example usage:
 * ```php
 * $handler = new ExceptionHandler(
 *     debug: env('APP_DEBUG'),
 *     logger: $container->get(LoggerInterface::class)
 * );
 *
 * // Add custom renderer for specific exception types
 * $handler->prependRenderer(new CustomExceptionRenderer());
 *
 * // Handle an exception
 * try {
 *     // ... application code
 * } catch (Throwable $e) {
 *     $handler->render($e, $request, $response);
 * }
 * ```
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
class ExceptionHandler implements ExtensibleExceptionHandlerInterface
{
    /**
     * The exception output formatter.
     *
     * @var ExceptionOutputFormatter
     */
    private ExceptionOutputFormatter $formatter;

    /**
     * Registered exception renderers.
     *
     * @var array<int, ExceptionRendererInterface>
     */
    private array $renderers = [];

    /**
     * Registered exception reporters.
     *
     * @var array<int, ExceptionReporterInterface>
     */
    private array $reporters = [];

    /**
     * Fallback reporter when no other reporters succeed.
     *
     * @var ExceptionReporterInterface
     */
    private ExceptionReporterInterface $fallbackReporter;

    /**
     * Creates a new exception handler instance.
     *
     * @param bool                 $debug           Expose detailed errors when true
     * @param LoggerInterface|null $logger          Optional PSR-3 logger for reporting
     * @param array<int, string>   $safeContextKeys Context keys allowed in production payloads
     */
    public function __construct(
        private bool $debug = false,
        ?LoggerInterface $logger = null,
        private array $safeContextKeys = [
            'field',
            'fields',
            'rule',
            'value',
            'allowed',
            'min',
            'max',
            'statusCode',
        ],
    ) {
        $this->formatter = new ExceptionOutputFormatter(
            $this->debug,
            $this->safeContextKeys,
        );
        $this->registerDefaultRenderers();

        if ($logger !== null) {
            $this->addReporter(new PsrLoggerExceptionReporter($logger));
        }

        $this->fallbackReporter = new PhpErrorLogReporter();
    }

    /**
     * Prepends a renderer to the beginning of the renderer list.
     *
     * Renderers added this way have highest priority and will be
     * checked first when handling exceptions.
     *
     * @param ExceptionRendererInterface $renderer The renderer to prepend
     *
     * @return void
     */
    public function prependRenderer(ExceptionRendererInterface $renderer): void
    {
        array_unshift($this->renderers, $renderer);
    }

    /**
     * Adds a renderer to the end of the renderer list.
     *
     * @param ExceptionRendererInterface $renderer The renderer to add
     *
     * @return void
     */
    public function addRenderer(ExceptionRendererInterface $renderer): void
    {
        $this->renderers[] = $renderer;
    }

    /**
     * Adds an exception reporter.
     *
     * Reporters are called to log/report exceptions before rendering.
     *
     * @param ExceptionReporterInterface $reporter The reporter to add
     *
     * @return void
     */
    public function addReporter(ExceptionReporterInterface $reporter): void
    {
        $this->reporters[] = $reporter;
    }

    /**
     * Handles an exception and returns an exception response.
     *
     * Reports the exception to all reporters, then finds an appropriate
     * renderer to create the response.
     *
     * @param Throwable $e       The exception to handle
     * @param Request   $request The current request
     *
     * @return ExceptionResponse The rendered exception response
     */
    public function handle(Throwable $e, Request $request): ExceptionResponse
    {
        $this->report($e, $request);

        foreach ($this->renderers as $renderer) {
            if ($renderer->canRender($e, $request)) {
                return $renderer->render($e, $request);
            }
        }

        // The generic renderer should always render as a final fallback.
        $generic = new GenericExceptionRenderer($this->formatter);
        return $generic->render($e, $request);
    }

    /**
     * Handles an exception and emits the response.
     *
     * Convenience method that handles the exception and immediately
     * emits the response.
     *
     * @param Throwable $e        The exception to handle
     * @param Request   $request  The current request
     * @param Response  $response The response to emit
     *
     * @return void
     */
    public function render(
        Throwable $e,
        Request $request,
        Response $response,
    ): void {
        $this->handle($e, $request)->emit($response);
    }

    /**
     * Reports an exception to all registered reporters.
     *
     * If no reporters succeed, falls back to the default reporter.
     *
     * @param Throwable $e       The exception to report
     * @param Request   $request The current request
     *
     * @return void
     */
    private function report(Throwable $e, Request $request): void
    {
        $context = $this->buildLogContext($e);
        $reported = false;

        foreach ($this->reporters as $reporter) {
            try {
                $reporter->report($e, $request, $context);
                $reported = true;
            } catch (Throwable $reporterError) {
                error_log(
                    sprintf(
                        '[Lalaz] Failed reporting exception via %s: %s (%s)',
                        get_class($reporter),
                        $reporterError->getMessage(),
                        get_class($reporterError),
                    ),
                );
            }
        }

        if (!$reported) {
            $this->fallbackReporter->report($e, $request, $context);
        }
    }

    /**
     * Registers the default exception renderers.
     *
     * Default order:
     * 1. HttpExceptionRenderer - For HTTP exceptions
     * 2. ValidationExceptionRenderer - For validation errors
     * 3. FrameworkExceptionRenderer - For framework exceptions
     * 4. GenericExceptionRenderer - Catch-all fallback
     *
     * @return void
     */
    private function registerDefaultRenderers(): void
    {
        $this->renderers = [
            new HttpExceptionRenderer($this->formatter),
            new ValidationExceptionRenderer($this->formatter),
            new FrameworkExceptionRenderer($this->formatter),
            new GenericExceptionRenderer($this->formatter),
        ];
    }

    /**
     * Builds the logging context for an exception.
     *
     * Includes exception class, message, file, line, and optionally
     * the stack trace (in debug mode or for non-HTTP exceptions).
     *
     * @param Throwable $e The exception
     *
     * @return array<string, mixed> The logging context
     */
    private function buildLogContext(Throwable $e): array
    {
        $context = [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ];

        $isHttpException = $e instanceof \Lalaz\Exceptions\HttpException;

        if ($this->debug || !$isHttpException) {
            $context['trace'] = $e->getTraceAsString();
        }

        if ($e instanceof ContextualExceptionInterface) {
            $context['exception_context'] = $e->getContext();
        }

        return $context;
    }
}
