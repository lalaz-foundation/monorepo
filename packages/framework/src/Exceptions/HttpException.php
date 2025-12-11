<?php

declare(strict_types=1);

namespace Lalaz\Exceptions;

use Lalaz\Exceptions\Contracts\ContextualExceptionInterface;
use RuntimeException;
use Throwable;

/**
 * HTTP-aware exception with helpers for common status codes.
 *
 * This exception is designed for HTTP error responses and includes
 * status code, headers, and contextual data. Factory methods are
 * provided for common HTTP error statuses.
 *
 * Example usage:
 * ```php
 * // Using factory methods
 * throw HttpException::notFound('User not found', ['user_id' => $id]);
 * throw HttpException::unauthorized('Invalid credentials');
 * throw HttpException::tooManyRequests('Rate limit exceeded', retryAfter: 120);
 *
 * // Direct instantiation
 * throw new HttpException('Custom error', 422, [], ['field' => 'email']);
 * ```
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
class HttpException extends RuntimeException implements ContextualExceptionInterface
{
    /**
     * HTTP status code for the response.
     *
     * @var int
     */
    protected int $statusCode;

    /**
     * HTTP headers to include in the response.
     *
     * @var array<string, string>
     */
    protected array $headers = [];

    /**
     * Additional context data for debugging.
     *
     * @var array<string, mixed>
     */
    protected array $context = [];

    /**
     * Create a new HTTP exception.
     *
     * @param string $message The error message
     * @param int $statusCode HTTP status code (default: 500)
     * @param array<string, string> $headers HTTP headers to send
     * @param array<string, mixed> $context Additional context data
     * @param Throwable|null $previous Previous exception for chaining
     */
    public function __construct(
        string $message = '',
        int $statusCode = 500,
        array $headers = [],
        array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        $this->context = $context;
    }

    /**
     * Get the HTTP status code.
     *
     * @return int The HTTP status code
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get the HTTP headers.
     *
     * @return array<string, string> The HTTP headers
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get the contextual data.
     *
     * @return array<string, mixed> The context data
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Add additional context to the exception.
     *
     * @param array<string, mixed> $context Context to merge
     * @return self Returns self for method chaining
     */
    public function withContext(array $context): self
    {
        $this->context = array_merge($this->context, $context);
        return $this;
    }

    /**
     * Add additional headers to the exception.
     *
     * @param array<string, string> $headers Headers to merge
     * @return self Returns self for method chaining
     */
    public function withHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    /**
     * Serialize the exception to an array.
     *
     * @return array<string, mixed> The serialized exception data
     */
    public function toArray(): array
    {
        return [
            'error' => true,
            'message' => $this->getMessage(),
            'statusCode' => $this->statusCode,
            'context' => $this->context,
        ];
    }

    /**
     * Create a 400 Bad Request exception.
     *
     * @param string $message Error message
     * @param array<string, mixed> $context Additional context
     * @return self The exception instance
     */
    public static function badRequest(
        string $message = 'Bad Request',
        array $context = [],
    ): self {
        return new self($message, 400, [], $context);
    }

    /**
     * Create a 401 Unauthorized exception.
     *
     * @param string $message Error message
     * @param array<string, mixed> $context Additional context
     * @return self The exception instance
     */
    public static function unauthorized(
        string $message = 'Unauthorized',
        array $context = [],
    ): self {
        return new self($message, 401, [], $context);
    }

    /**
     * Create a 403 Forbidden exception.
     *
     * @param string $message Error message
     * @param array<string, mixed> $context Additional context
     * @return self The exception instance
     */
    public static function forbidden(
        string $message = 'Forbidden',
        array $context = [],
    ): self {
        return new self($message, 403, [], $context);
    }

    /**
     * Create a 404 Not Found exception.
     *
     * @param string $message Error message
     * @param array<string, mixed> $context Additional context
     * @return self The exception instance
     */
    public static function notFound(
        string $message = 'Not Found',
        array $context = [],
    ): self {
        return new self($message, 404, [], $context);
    }

    /**
     * Create a 405 Method Not Allowed exception.
     *
     * @param string $message Error message
     * @param array<string, mixed> $context Additional context
     * @return self The exception instance
     */
    public static function methodNotAllowed(
        string $message = 'Method Not Allowed',
        array $context = [],
    ): self {
        return new self($message, 405, [], $context);
    }

    /**
     * Create a 419 CSRF Token Mismatch exception.
     *
     * @param string $message Error message
     * @param array<string, mixed> $context Additional context
     * @return self The exception instance
     */
    public static function csrfMismatch(
        string $message = 'CSRF token mismatch',
        array $context = [],
    ): self {
        return new self($message, 419, [], $context);
    }

    /**
     * Create a 429 Too Many Requests exception.
     *
     * Includes a Retry-After header indicating when the client can retry.
     *
     * @param string $message Error message
     * @param array<string, mixed> $context Additional context
     * @param int $retryAfter Seconds until client can retry (default: 60)
     * @return self The exception instance
     */
    public static function tooManyRequests(
        string $message = 'Too Many Requests',
        array $context = [],
        int $retryAfter = 60,
    ): self {
        return new self(
            $message,
            429,
            ['Retry-After' => (string) $retryAfter],
            $context,
        );
    }

    /**
     * Create a 500 Internal Server Error exception.
     *
     * @param string $message Error message
     * @param array<string, mixed> $context Additional context
     * @return self The exception instance
     */
    public static function internalServerError(
        string $message = 'Internal Server Error',
        array $context = [],
    ): self {
        return new self($message, 500, [], $context);
    }

    /**
     * Create a 503 Service Unavailable exception.
     *
     * Includes a Retry-After header indicating when the service may be available.
     *
     * @param string $message Error message
     * @param array<string, mixed> $context Additional context
     * @param int $retryAfter Seconds until service may be available (default: 60)
     * @return self The exception instance
     */
    public static function serviceUnavailable(
        string $message = 'Service Unavailable',
        array $context = [],
        int $retryAfter = 60,
    ): self {
        return new self(
            $message,
            503,
            ['Retry-After' => (string) $retryAfter],
            $context,
        );
    }
}
