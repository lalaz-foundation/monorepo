<?php

declare(strict_types=1);

namespace Lalaz\Web\Http\Contracts;

use Lalaz\Web\Http\Request;
use Throwable;

/**
 * Reporter hook executed whenever the HTTP exception handler catches an error.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 */
interface ExceptionReporterInterface
{
    /**
     * Report the throwable to logging/monitoring backends.
     *
     * @param Throwable $e
     * @param Request $request
     * @param array<string, mixed> $context
     * @return void
     */
    public function report(
        Throwable $e,
        Request $request,
        array $context,
    ): void;
}
