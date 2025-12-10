<?php

declare(strict_types=1);

namespace Lalaz\Web\Http\Contracts;

use Lalaz\Exceptions\ExceptionResponse;
use Lalaz\Web\Http\Request;
use Lalaz\Web\Http\Response;
use Throwable;

/**
 * Contract for HTTP exception handlers capable of rendering responses.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 */
interface ExceptionHandlerInterface
{
    /**
     * Handles a throwable and returns a structured exception response.
     *
     * @param Throwable $e
     * @param Request $request
     * @return ExceptionResponse
     */
    public function handle(Throwable $e, Request $request): ExceptionResponse;

    /**
     * Renders the throwable into the provided HTTP response.
     *
     * @param Throwable $e
     * @param Request $request
     * @param Response $response
     * @return void
     */
    public function render(
        Throwable $e,
        Request $request,
        Response $response,
    ): void;
}
