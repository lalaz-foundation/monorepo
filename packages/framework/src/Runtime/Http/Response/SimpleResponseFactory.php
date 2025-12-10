<?php

declare(strict_types=1);

namespace Lalaz\Runtime\Http\Response;

use Lalaz\Web\Http\Contracts\RequestInterface;
use Lalaz\Web\Http\Contracts\ResponseFactoryInterface;
use Lalaz\Web\Http\Contracts\ResponseInterface;
use Lalaz\Web\Http\Response;

/**
 * Simple response factory implementation.
 *
 * Creates Response objects initialized with the Host header from
 * the incoming request. Provides a minimal factory for standard
 * HTTP response creation.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
final class SimpleResponseFactory implements ResponseFactoryInterface
{
    /**
     * Create a new Response instance for the given request.
     *
     * Extracts the Host header from the request to initialize
     * the response with the appropriate host context.
     *
     * @param RequestInterface $request The incoming HTTP request.
     * @return ResponseInterface The created response instance.
     */
    public function create(RequestInterface $request): ResponseInterface
    {
        $hostHeader = $request->header('Host', 'localhost');
        $host = is_string($hostHeader) && $hostHeader !== ''
            ? $hostHeader
            : 'localhost';

        return new Response($host);
    }
}
