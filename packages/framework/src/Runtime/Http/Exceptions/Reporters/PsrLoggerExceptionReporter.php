<?php

declare(strict_types=1);

namespace Lalaz\Runtime\Http\Exceptions\Reporters;

use Lalaz\Web\Http\Contracts\ExceptionReporterInterface;
use Lalaz\Web\Http\Request;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Exception reporter using PSR-3 compatible logger.
 *
 * Reports exceptions through any PSR-3 logger implementation,
 * enabling integration with various logging backends like Monolog,
 * the framework's Logger, or third-party services.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
final class PsrLoggerExceptionReporter implements ExceptionReporterInterface
{
    /**
     * Create a new PSR logger exception reporter.
     *
     * @param LoggerInterface $logger The PSR-3 compatible logger.
     */
    public function __construct(private LoggerInterface $logger)
    {
    }

    /**
     * Report an exception through the PSR-3 logger.
     *
     * Logs the exception message at error level with full context.
     *
     * @param Throwable $e The exception to report.
     * @param Request $request The current request context.
     * @param array<string, mixed> $context Additional context data.
     * @return void
     */
    public function report(Throwable $e, Request $request, array $context): void
    {
        $this->logger->error($e->getMessage(), $context);
    }
}
