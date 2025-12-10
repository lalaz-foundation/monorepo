<?php

declare(strict_types=1);

namespace Lalaz\Web\Http\Contracts;

/**
 * Contract for HTTP Response objects.
 *
 * Provides a clean abstraction for building and sending HTTP responses,
 * supporting various response types including JSON, file downloads,
 * streaming, and redirects.
 *
 * This interface enables dependency inversion, allowing components
 * to depend on the abstraction rather than the concrete Response class,
 * which improves testability and decoupling.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
interface ResponseInterface
{
    /**
     * Set the HTTP status code.
     *
     * @param int $code The HTTP status code.
     * @return self
     */
    public function status(int $code): self;

    /**
     * Get the current HTTP status code.
     *
     * @return int
     */
    public function getStatusCode(): int;

    /**
     * Add a header to the response.
     *
     * @param string $name Header name.
     * @param string $value Header value.
     * @return self
     */
    public function addHeader(string $name, string $value): self;

    /**
     * Add a header to the response (alias for addHeader).
     *
     * @param string $name Header name.
     * @param string $value Header value.
     * @return self
     */
    public function header(string $name, string $value): self;

    /**
     * Add multiple headers to the response.
     *
     * @param array<string, string> $headers Associative array of headers.
     * @return self
     */
    public function withHeaders(array $headers): self;

    /**
     * Get all response headers.
     *
     * @return array<string, string>
     */
    public function headers(): array;

    /**
     * Get the response body content.
     *
     * @return string
     */
    public function body(): string;

    /**
     * Check if this response uses streaming.
     *
     * @return bool True if a stream callback is set.
     */
    public function isStreamed(): bool;

    /**
     * Send the response body using the provided emitter.
     *
     * @param ResponseBodyEmitterInterface $emitter The emitter to write to.
     * @return void
     */
    public function sendBody(ResponseBodyEmitterInterface $emitter): void;

    /**
     * Set the response body content.
     *
     * @param string $content The body content.
     * @return self
     */
    public function setBody(string $content): self;

    /**
     * Append content to the response body.
     *
     * @param string $content Content to append.
     * @return self
     */
    public function append(string $content): self;

    /**
     * Redirect to a URL.
     *
     * @param string $url The URL to redirect to.
     * @param bool $allowExternal Whether to allow external redirects.
     * @return void
     */
    public function redirect(string $url, bool $allowExternal = false): void;

    /**
     * Send a 204 No Content response.
     *
     * @param array<string, string> $headers Optional headers to include.
     * @return void
     */
    public function noContent(array $headers = []): void;

    /**
     * Send a 201 Created response.
     *
     * @param string $location The URL of the created resource.
     * @param mixed $data Optional data to return as JSON.
     * @return void
     */
    public function created(string $location, mixed $data = null): void;

    /**
     * Send a file download response.
     *
     * @param string $filePath Path to the file to download.
     * @param string|null $fileName Optional custom filename for download.
     * @param array<string, string> $headers Optional additional headers.
     * @return void
     */
    public function download(
        string $filePath,
        ?string $fileName = null,
        array $headers = [],
    ): void;

    /**
     * Send a streaming response.
     *
     * @param callable $callback Callback that produces stream content.
     * @param int $statusCode HTTP status code.
     * @param array<string, string> $headers Optional headers.
     * @return void
     */
    public function stream(
        callable $callback,
        int $statusCode = 200,
        array $headers = [],
    ): void;

    /**
     * Send a JSON response.
     *
     * @param mixed $data Data to encode as JSON.
     * @param int $statusCode HTTP status code.
     * @return void
     */
    public function json($data = [], $statusCode = 200): void;

    /**
     * Send a response with the given content.
     *
     * @param string $content The response content.
     * @param int $statusCode HTTP status code.
     * @param array<string, string> $headers Optional headers.
     * @param string|null $contentType Optional content type.
     * @return void
     */
    public function send(
        string $content,
        int $statusCode = 200,
        array $headers = [],
        ?string $contentType = null,
    ): void;

    /**
     * Clear the response (headers and body).
     *
     * @return void
     */
    public function end(): void;
}
