<?php

declare(strict_types=1);

namespace Lalaz\Web\Http\Contracts;

use Lalaz\Exceptions\ExceptionResponse;
use Lalaz\Web\Http\Request;
use Throwable;

/**
 * Strategy interface that renders a throwable into an HTTP-friendly response.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 */
interface ExceptionRendererInterface
{
    /**
     * Determines whether this renderer can handle the throwable.
     *
     * @param Throwable $e
     * @param Request $request
     * @return bool
     */
    public function canRender(Throwable $e, Request $request): bool;

    /**
     * Produces an ExceptionResponse for the given throwable/request pair.
     *
     * @param Throwable $e
     * @param Request $request
     * @return ExceptionResponse
     */
    public function render(Throwable $e, Request $request): ExceptionResponse;
}
