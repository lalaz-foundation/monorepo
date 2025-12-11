<?php

declare(strict_types=1);

namespace Lalaz\Web\Http;

use Closure;
use Lalaz\Exceptions\HttpException;
use Lalaz\Support\Concerns\HasAttributes;
use Lalaz\Web\Http\Contracts\ResponseBodyEmitterInterface;
use Lalaz\Web\Http\Contracts\ResponseInterface;
use ReflectionFunction;

/**
 * HTTP Response class for building and sending HTTP responses.
 *
 * This class provides a fluent interface for constructing HTTP responses,
 * supporting various response types including JSON, file downloads, streaming,
 * and redirects. It handles headers, status codes, and body content.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
class Response implements ResponseInterface
{
    use HasAttributes;

    /**
     * HTTP status code for the response.
     *
     * @var int
     */
    private int $statusCode = 200;

    /**
     * Current host for safe redirect validation.
     *
     * @var string
     */
    private string $currentHost;

    /**
     * Response headers.
     *
     * @var array<string, string>
     */
    private array $headers = [];

    /**
     * Response body content.
     *
     * @var string
     */
    private string $body = '';

    /**
     * Callback for streaming responses.
     *
     * @var null|Closure(callable(string): void): void
     */
    private ?Closure $streamCallback = null;

    /**
     * Create a new Response instance.
     *
     * @param string $host The current host for redirect validation.
     */
    public function __construct(string $host)
    {
        $this->currentHost = $host;
    }

    /**
     * Set the HTTP status code.
     *
     * @param int $code The HTTP status code.
     * @return self
     */
    public function status(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    /**
     * Get the current HTTP status code.
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Add a header to the response.
     *
     * @param string $name Header name.
     * @param string $value Header value.
     * @return self
     */
    public function addHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Add a header to the response (alias for addHeader).
     *
     * @param string $name Header name.
     * @param string $value Header value.
     * @return self
     */
    public function header(string $name, string $value): self
    {
        return $this->addHeader($name, $value);
    }

    /**
     * Add multiple headers to the response.
     *
     * @param array<string, string> $headers Associative array of headers.
     * @return self
     */
    public function withHeaders(array $headers): self
    {
        foreach ($headers as $name => $value) {
            $this->addHeader((string) $name, (string) $value);
        }

        return $this;
    }

    /**
     * Get all response headers.
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Get the response body content.
     *
     * @return string
     */
    public function body(): string
    {
        return $this->body;
    }

    /**
     * Check if this response uses streaming.
     *
     * @return bool True if a stream callback is set.
     */
    public function isStreamed(): bool
    {
        return $this->streamCallback !== null;
    }

    /**
     * Send the response body using the provided emitter.
     *
     * Handles both streaming and regular body content.
     *
     * @param ResponseBodyEmitterInterface $emitter The emitter to write to.
     * @return void
     */
    public function sendBody(ResponseBodyEmitterInterface $emitter): void
    {
        if ($this->streamCallback !== null) {
            $callback = $this->streamCallback;
            $callback(static function (string $chunk) use ($emitter): void {
                if ($chunk === '') {
                    return;
                }

                $emitter->write($chunk);
            });
            return;
        }

        if ($this->body === '') {
            return;
        }

        $emitter->write($this->body);
    }

    /**
     * Set the response body content.
     *
     * @param string $content The body content.
     * @return self
     */
    public function setBody(string $content): self
    {
        $this->clearStreamHandler();
        $this->body = $content;
        return $this;
    }

    /**
     * Append content to the response body.
     *
     * @param string $content Content to append.
     * @return self
     */
    public function append(string $content): self
    {
        $this->clearStreamHandler();
        $this->body .= $content;
        return $this;
    }

    /**
     * Redirect to a URL.
     *
     * By default, only same-host redirects are allowed for security.
     * Set $allowExternal to true to allow external redirects.
     *
     * @param string $url The URL to redirect to.
     * @param bool $allowExternal Whether to allow external redirects.
     * @return void
     * @throws HttpException When redirect URL is unsafe and external not allowed.
     */
    public function redirect(string $url, bool $allowExternal = false): void
    {
        if (!$allowExternal && !$this->isSafeRedirectUrl($url)) {
            throw HttpException::badRequest(
                'Unsafe redirect URL. External redirects must be explicitly allowed.',
            );
        }

        $this->status(302)->header('Location', $url);
    }

    /**
     * Check if a redirect URL is safe (same host or relative).
     *
     * @param string $url The URL to validate.
     * @return bool True if the URL is safe for redirect.
     */
    private function isSafeRedirectUrl(string $url): bool
    {
        if (str_starts_with($url, '/')) {
            if (str_starts_with($url, '//')) {
                return false;
            }
            return true;
        }

        $parsed = parse_url($url);
        if ($parsed === false) {
            return false;
        }

        if (!isset($parsed['host'])) {
            return true;
        }

        $currentHost = explode(':', $this->currentHost)[0];
        $redirectHost = $parsed['host'];

        return strcasecmp($currentHost, $redirectHost) === 0;
    }

    /**
     * Send a 204 No Content response.
     *
     * @param array<string, string> $headers Optional headers to include.
     * @return void
     */
    public function noContent(array $headers = []): void
    {
        $this->statusCode = 204;
        $this->clearStreamHandler();
        $this->body = '';
        $this->withHeaders($headers);
    }

    /**
     * Send a 201 Created response.
     *
     * @param string $location The URL of the created resource.
     * @param mixed $data Optional data to return as JSON.
     * @return void
     */
    public function created(string $location, mixed $data = null): void
    {
        $this->header('Location', $location);

        if ($data === null) {
            $this->status(201)->setBody('');
            return;
        }

        $this->json((array) $data, 201);
    }

    /**
     * Send a file download response.
     *
     * Streams the file content for memory-efficient large file downloads.
     *
     * @param string $filePath Path to the file to download.
     * @param string|null $fileName Optional custom filename for download.
     * @param array<string, string> $headers Optional additional headers.
     * @return void
     * @throws HttpException When file is not found or not readable.
     */
    public function download(
        string $filePath,
        ?string $fileName = null,
        array $headers = [],
    ): void {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw HttpException::notFound('File not found', [
                'path' => $filePath,
            ]);
        }

        $fileName ??= basename($filePath);
        $fileName = str_replace(["\r", "\n"], '', $fileName);

        $this->header('Content-Type', 'application/octet-stream')
            ->header(
                'Content-Disposition',
                sprintf('attachment; filename="%s"', addslashes($fileName)),
            )
            ->withHeaders($headers);
        $normalized = array_change_key_case($this->headers, CASE_LOWER);
        if (!isset($normalized['content-length'])) {
            $size = @filesize($filePath);
            if (is_int($size) && $size >= 0) {
                $this->header('Content-Length', (string) $size);
            }
        }

        $this->useStreamHandler(static function (callable $write) use (
            $filePath,
        ): void {
            $handle = fopen($filePath, 'rb');

            if ($handle === false) {
                throw HttpException::internalServerError(
                    'Unable to open file for download.',
                    ['path' => $filePath],
                );
            }

            try {
                while (!feof($handle)) {
                    $chunk = fread($handle, 8192);

                    if ($chunk === false) {
                        break;
                    }

                    if ($chunk === '') {
                        continue;
                    }

                    $write($chunk);
                }
            } finally {
                fclose($handle);
            }
        });
    }

    /**
     * Send a streaming response.
     *
     * The callback receives a write function to send chunks progressively.
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
    ): void {
        $this->status($statusCode)->withHeaders($headers);
        $this->useStreamHandler($this->prepareStreamCallback($callback));
    }

    /**
     * Send a JSON response.
     *
     * @param mixed $data Data to encode as JSON.
     * @param int $statusCode HTTP status code.
     * @return void
     */
    public function json($data = [], $statusCode = 200): void
    {
        $this->statusCode = $statusCode;

        try {
            $json = json_encode(
                $data,
                JSON_THROW_ON_ERROR |
                    JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES,
            );
        } catch (\JsonException $e) {
            $this->status(500)
                ->header('Content-Type', 'application/json')
                ->setBody(
                    json_encode([
                        'error' => true,
                        'message' => 'Failed to encode JSON response.',
                    ]),
                );
            return;
        }

        $this->header('Content-Type', 'application/json')->setBody($json);
    }

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
    ): void {
        $this->status($statusCode);

        if (
            $contentType !== null &&
            !array_key_exists('Content-Type', $headers)
        ) {
            $headers['Content-Type'] = $contentType;
        }

        $this->withHeaders($headers);
        $this->setBody($content);
    }

    /**
     * Clear the response (headers and body).
     *
     * @return void
     */
    public function end(): void
    {
        $this->headers = [];
        $this->body = '';
        $this->clearStreamHandler();
    }

    /**
     * Set a stream handler for streaming responses.
     *
     * @param Closure $handler The stream handler callback.
     * @return void
     */
    private function useStreamHandler(Closure $handler): void
    {
        $this->streamCallback = $handler;
        $this->body = '';
    }

    /**
     * Clear the current stream handler.
     *
     * @return void
     */
    private function clearStreamHandler(): void
    {
        $this->streamCallback = null;
    }

    /**
     * Normalize the user-provided callback so it can stream via a chunk writer.
     *
     * @param callable $callback The user-provided stream callback.
     * @return Closure(callable(string): void): void
     */
    private function prepareStreamCallback(callable $callback): Closure
    {
        $closure =
            $callback instanceof Closure
                ? $callback
                : Closure::fromCallable($callback);
        $reflection = new ReflectionFunction($closure);
        $expectsWriter = $reflection->getNumberOfParameters() >= 1;

        return static function (
            callable $write,
        ) use ($closure, $expectsWriter): void {
            $result = null;
            $buffer = null;

            ob_start();
            try {
                if ($expectsWriter) {
                    $result = $closure(static function (string $chunk) use (
                        $write,
                    ): void {
                        if ($chunk === '') {
                            return;
                        }

                        $write($chunk);
                    });
                } else {
                    $result = $closure();
                }
            } finally {
                $captured = ob_get_clean();
                if ($captured !== false) {
                    $buffer = $captured;
                }
            }

            if ($buffer !== null && $buffer !== '') {
                $write($buffer);
            }

            if ($result === null) {
                return;
            }

            if (is_iterable($result)) {
                foreach ($result as $chunk) {
                    if ($chunk === null) {
                        continue;
                    }

                    $chunkString = (string) $chunk;

                    if ($chunkString === '') {
                        continue;
                    }

                    $write($chunkString);
                }
                return;
            }

            if (is_string($result) && $result !== '') {
                $write($result);
            }
        };
    }
}
