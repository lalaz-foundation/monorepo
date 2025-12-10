<?php

declare(strict_types=1);

namespace Lalaz\Wire\HttpClient;

final class HttpRequest
{
    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $query
     */
    public function __construct(
        public string $method,
        public string $url,
        public array $headers = [],
        public array $query = [],
        public mixed $body = null,
        public int $timeout = 10,
        public int $connectTimeout = 5,
        public bool $skipSsl = false,
        public bool $followRedirects = true,
        public int $maxRedirects = 5,
    ) {
    }
}
