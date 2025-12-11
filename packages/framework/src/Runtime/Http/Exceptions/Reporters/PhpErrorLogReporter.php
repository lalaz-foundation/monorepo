<?php

declare(strict_types=1);

namespace Lalaz\Runtime\Http\Exceptions\Reporters;

use Lalaz\Web\Http\Contracts\ExceptionReporterInterface;
use Lalaz\Web\Http\Request;
use Throwable;

/**
 * Exception reporter using PHP's error_log function.
 *
 * This is the fallback reporter used when no other reporters are
 * configured or when other reporters fail. Logs exceptions to PHP's
 * configured error log destination.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
final class PhpErrorLogReporter implements ExceptionReporterInterface
{
    /**
     * Report an exception to PHP's error log.
     *
     * Formats the exception as: [Lalaz] ExceptionClass: message in file:line
     *
     * @param Throwable $e The exception to report.
     * @param Request $request The current request context.
     * @param array<string, mixed> $context Additional context data (unused).
     * @return void
     */
    public function report(Throwable $e, Request $request, array $context): void
    {
        error_log(
            sprintf(
                '[Lalaz] %s: %s in %s:%d',
                get_class($e),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
            ),
        );
    }
}
