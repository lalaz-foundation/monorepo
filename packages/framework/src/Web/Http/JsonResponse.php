<?php

declare(strict_types=1);

namespace Lalaz\Web\Http;

use JsonException;
use Lalaz\Web\Http\Contracts\RenderableInterface;
use Lalaz\Web\Http\Contracts\ResponseInterface;

/**
 * JsonResponse - A renderable JSON response object.
 *
 * This class represents a JSON response that can be returned from a controller
 * and automatically rendered to an HTTP response by the framework.
 *
 * Example usage:
 * ```php
 * class ApiController
 * {
 *     public function users()
 *     {
 *         return json(['users' => User::all()]);
 *     }
 *
 *     public function created()
 *     {
 *         return json(['id' => 123], 201);
 *     }
 * }
 * ```
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
final class JsonResponse implements RenderableInterface
{
    /**
     * @var array<string, string> Additional headers to send with the response.
     */
    private array $headers = [];

    /**
     * @var int JSON encoding options.
     */
    private int $encodingOptions = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    /**
     * Create a new JsonResponse instance.
     *
     * @param mixed $data The data to encode as JSON.
     * @param int $statusCode HTTP status code (default: 200).
     */
    public function __construct(
        private mixed $data = [],
        private int $statusCode = 200,
    ) {
    }

    /**
     * Create a new JsonResponse instance (static factory).
     *
     * @param mixed $data The data to encode as JSON.
     * @param int $statusCode HTTP status code.
     * @return self
     */
    public static function create(mixed $data = [], int $statusCode = 200): self
    {
        return new self($data, $statusCode);
    }

    /**
     * Create a success response.
     *
     * @param mixed $data The data to include.
     * @param string $message Optional success message.
     * @return self
     */
    public static function success(mixed $data = null, string $message = 'Success'): self
    {
        $payload = ['success' => true, 'message' => $message];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return new self($payload, 200);
    }

    /**
     * Create an error response.
     *
     * @param string $message Error message.
     * @param int $statusCode HTTP status code (default: 400).
     * @param array<string, mixed> $errors Optional validation errors.
     * @return self
     */
    public static function error(
        string $message,
        int $statusCode = 400,
        array $errors = [],
    ): self {
        $payload = ['success' => false, 'message' => $message];

        if (!empty($errors)) {
            $payload['errors'] = $errors;
        }

        return new self($payload, $statusCode);
    }

    /**
     * Set the HTTP status code.
     *
     * @param int $statusCode The HTTP status code.
     * @return self
     */
    public function status(int $statusCode): self
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    /**
     * Add a header to the response.
     *
     * @param string $name Header name.
     * @param string $value Header value.
     * @return self
     */
    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Add multiple headers to the response.
     *
     * @param array<string, string> $headers Headers to add.
     * @return self
     */
    public function withHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    /**
     * Get all headers.
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Add or merge data to the response.
     *
     * @param string|array<string, mixed> $key Key or array of key-value pairs.
     * @param mixed $value Value (if $key is a string).
     * @return self
     */
    public function with(string|array $key, mixed $value = null): self
    {
        if (!is_array($this->data)) {
            // If data is not an array, wrap it
            $this->data = ['data' => $this->data];
        }

        if (is_array($key)) {
            $this->data = array_merge($this->data, $key);
        } else {
            $this->data[$key] = $value;
        }

        return $this;
    }

    /**
     * Set JSON encoding options.
     *
     * @param int $options JSON encoding options (e.g., JSON_PRETTY_PRINT).
     * @return self
     */
    public function withOptions(int $options): self
    {
        $this->encodingOptions = $options;
        return $this;
    }

    /**
     * Enable pretty printing of JSON output.
     *
     * @return self
     */
    public function pretty(): self
    {
        $this->encodingOptions |= JSON_PRETTY_PRINT;
        return $this;
    }

    /**
     * Get the data.
     *
     * @return mixed
     */
    public function data(): mixed
    {
        return $this->data;
    }

    /**
     * Get the status code.
     *
     * @return int
     */
    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Encode the data to JSON string.
     *
     * @return string The JSON encoded string.
     * @throws JsonException If encoding fails.
     */
    public function toJson(): string
    {
        return json_encode(
            $this->data,
            $this->encodingOptions | JSON_THROW_ON_ERROR,
        );
    }

    /**
     * Render this JSON response to the given HTTP response.
     *
     * @param ResponseInterface $response The response to render to.
     * @return void
     */
    public function toResponse(ResponseInterface $response): void
    {
        $response->status($this->statusCode);

        foreach ($this->headers as $name => $value) {
            $response->header($name, $value);
        }

        $response->header('Content-Type', 'application/json; charset=utf-8');

        try {
            $response->setBody($this->toJson());
        } catch (JsonException $e) {
            $response->status(500);
            $response->setBody(json_encode([
                'error' => true,
                'message' => 'Failed to encode JSON response.',
            ]));
        }
    }

    /**
     * Convert to string (JSON encoded).
     *
     * @return string
     */
    public function __toString(): string
    {
        try {
            return $this->toJson();
        } catch (JsonException) {
            return '{"error":true,"message":"Failed to encode JSON response."}';
        }
    }
}
