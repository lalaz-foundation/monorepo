<?php

declare(strict_types=1);

namespace Lalaz\Wire\HttpClient;

final class HttpResponse
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public int $statusCode,
        public array $headers,
        public mixed $body,
        public float $durationMs,
    ) {
    }
}
