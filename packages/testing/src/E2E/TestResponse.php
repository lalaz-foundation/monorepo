<?php

declare(strict_types=1);

namespace Lalaz\Testing\E2E;

/**
 * TestResponse - Wrapper for HTTP responses in E2E tests
 *
 * Provides a fluent interface for asserting and inspecting
 * HTTP responses in end-to-end tests.
 *
 * @package lalaz/testing
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
final class TestResponse
{
    /**
     * @param int $statusCode HTTP status code
     * @param array<string, string|array<string>> $headers Response headers
     * @param string $body Response body
     * @param array<string, mixed> $request Original request data
     */
    public function __construct(
        private readonly int $statusCode,
        private readonly array $headers,
        private readonly string $body,
        private readonly array $request = [],
    ) {
    }

    /**
     * Get the HTTP status code.
     *
     * @return int
     */
    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get all headers.
     *
     * @return array<string, string|array<string>>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Get a specific header value.
     *
     * @param string $name Header name
     * @return string|null
     */
    public function header(string $name): ?string
    {
        $value = $this->headers[$name] ?? $this->headers[strtolower($name)] ?? null;

        if (is_array($value)) {
            return $value[0] ?? null;
        }

        return $value;
    }

    /**
     * Get the response body.
     *
     * @return string
     */
    public function body(): string
    {
        return $this->body;
    }

    /**
     * Get the response body as JSON.
     *
     * @return array<string, mixed>
     */
    public function json(): array
    {
        $decoded = json_decode($this->body, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Get the original request data.
     *
     * @return array<string, mixed>
     */
    public function request(): array
    {
        return $this->request;
    }

    /**
     * Check if response is successful (2xx).
     *
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * Check if response is a redirect (3xx).
     *
     * @return bool
     */
    public function isRedirect(): bool
    {
        return $this->statusCode >= 300 && $this->statusCode < 400;
    }

    /**
     * Check if response is a client error (4xx).
     *
     * @return bool
     */
    public function isClientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    /**
     * Check if response is a server error (5xx).
     *
     * @return bool
     */
    public function isServerError(): bool
    {
        return $this->statusCode >= 500;
    }

    /**
     * Check if response is JSON.
     *
     * @return bool
     */
    public function isJson(): bool
    {
        $contentType = $this->header('Content-Type') ?? '';
        return str_contains($contentType, 'application/json');
    }

    /**
     * Check if response is OK (200).
     *
     * @return bool
     */
    public function isOk(): bool
    {
        return $this->statusCode === 200;
    }

    /**
     * Check if response is Created (201).
     *
     * @return bool
     */
    public function isCreated(): bool
    {
        return $this->statusCode === 201;
    }

    /**
     * Check if response is No Content (204).
     *
     * @return bool
     */
    public function isNoContent(): bool
    {
        return $this->statusCode === 204;
    }

    /**
     * Check if response is Not Found (404).
     *
     * @return bool
     */
    public function isNotFound(): bool
    {
        return $this->statusCode === 404;
    }

    /**
     * Check if response is Unauthorized (401).
     *
     * @return bool
     */
    public function isUnauthorized(): bool
    {
        return $this->statusCode === 401;
    }

    /**
     * Check if response is Forbidden (403).
     *
     * @return bool
     */
    public function isForbidden(): bool
    {
        return $this->statusCode === 403;
    }
}
