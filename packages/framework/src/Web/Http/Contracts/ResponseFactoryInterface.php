<?php

declare(strict_types=1);

namespace Lalaz\Web\Http\Contracts;

/**
 * Creates HTTP responses tailored to the current runtime.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 */
interface ResponseFactoryInterface
{
    /**
     * Builds a base response instance bound to the provided request context.
     *
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    public function create(RequestInterface $request): ResponseInterface;
}
