<?php

declare(strict_types=1);

namespace Lalaz\Exceptions;

use Lalaz\Web\Http\Response;

/**
 * Value-object representing how to respond to an exception.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 */
final class ExceptionResponse
{
    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>|string $body
     */
    public function __construct(
        private int $statusCode,
        private array $headers,
        private array|string $body,
        private bool $json = true,
    ) {
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * @return array<string, mixed>|string
     */
    public function body(): array|string
    {
        return $this->body;
    }

    public function isJson(): bool
    {
        return $this->json;
    }

    /**
     * Emit the response using the provided HTTP response object.
     */
    public function emit(Response $response): void
    {
        $response->withHeaders($this->headers);

        if ($this->json) {
            $payload = is_array($this->body)
                ? $this->body
                : ['message' => (string) $this->body];
            $response->json($payload, $this->statusCode);
            return;
        }

        $content = is_string($this->body)
            ? $this->body
            : json_encode(
                $this->body,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );

        $response->send($content ?? '', $this->statusCode);
    }
}
