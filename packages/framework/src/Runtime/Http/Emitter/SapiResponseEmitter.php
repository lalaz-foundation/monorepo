<?php

declare(strict_types=1);

namespace Lalaz\Runtime\Http\Emitter;

use Lalaz\Web\Http\Contracts\ResponseEmitterInterface;
use Lalaz\Web\Http\Response;

/**
 * SAPI (Server API) response emitter implementation.
 *
 * This emitter sends HTTP responses to the client through PHP's
 * standard output mechanism. It handles headers, status codes,
 * and body content with support for streaming and FastCGI finish.
 *
 * Features:
 * - Sets HTTP response headers
 * - Sets HTTP status code
 * - Outputs response body with proper flushing
 * - Supports FastCGI early request termination
 * - Auto-adds Content-Type if not present
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
final class SapiResponseEmitter implements ResponseEmitterInterface
{
    /**
     * Emit an HTTP response to the client.
     *
     * Sends all headers, sets the status code, outputs the body,
     * and optionally finishes the request for FastCGI environments.
     *
     * @param Response $response The response to emit.
     * @return void
     */
    public function emit(Response $response): void
    {
        $headers = $this->ensureContentType($response->headers());

        foreach ($headers as $name => $value) {
            header($name . ': ' . $value, true);
        }

        http_response_code($response->getStatusCode());

        $response->sendBody($this);

        if (
            PHP_SAPI !== 'cli' &&
            PHP_SAPI !== 'phpdbg' &&
            function_exists('fastcgi_finish_request')
        ) {
            fastcgi_finish_request();
        }
    }

    /**
     * Write a chunk of content to the output buffer.
     *
     * Used by the response's sendBody method to stream content.
     * Flushes output buffers after each write for streaming support.
     *
     * @param string $chunk The content chunk to write.
     * @return void
     */
    public function write(string $chunk): void
    {
        if ($chunk === '') {
            return;
        }

        echo $chunk;

        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }

    /**
     * Ensure Content-Type header is present.
     *
     * If no Content-Type header exists, adds a default text/plain with UTF-8.
     *
     * @param array<string, string> $headers The current headers.
     * @return array<string, string> The headers with Content-Type ensured.
     */
    private function ensureContentType(array $headers): array
    {
        $normalized = array_change_key_case($headers, CASE_LOWER);

        if (!isset($normalized['content-type'])) {
            $headers['Content-Type'] = 'text/plain; charset=utf-8';
        }

        return $headers;
    }
}
