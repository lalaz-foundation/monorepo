<?php

declare(strict_types=1);

namespace Lalaz\Web\Http\Contracts;

/**
 * Exception handler extension points for registering renderers/reporters.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 */
interface ExtensibleExceptionHandlerInterface extends ExceptionHandlerInterface
{
    /**
     * Registers a renderer with highest priority (runs before default ones).
     *
     * @param ExceptionRendererInterface $renderer
     * @return void
     */
    public function prependRenderer(ExceptionRendererInterface $renderer): void;

    /**
     * Appends a renderer after the built-in/default ones.
     *
     * @param ExceptionRendererInterface $renderer
     * @return void
     */
    public function addRenderer(ExceptionRendererInterface $renderer): void;

    /**
     * Registers a reporter hook executed whenever a throwable is handled.
     *
     * @param ExceptionReporterInterface $reporter
     * @return void
     */
    public function addReporter(ExceptionReporterInterface $reporter): void;
}
