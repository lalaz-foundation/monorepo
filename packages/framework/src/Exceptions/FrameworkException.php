<?php

declare(strict_types=1);

namespace Lalaz\Exceptions;

use Lalaz\Exceptions\Contracts\ContextualExceptionInterface;
use RuntimeException;
use Throwable;

/**
 * Base exception used across the framework to attach contextual metadata.
 *
 * All framework exceptions should extend this class to provide consistent
 * error handling with contextual information for debugging and logging.
 *
 * Example usage:
 * ```php
 * throw new FrameworkException(
 *     'Operation failed',
 *     ['operation' => 'save', 'entity' => 'User', 'id' => 123]
 * );
 *
 * // Or extend for specific exception types
 * class MyException extends FrameworkException
 * {
 *     public static function customError(string $reason): self
 *     {
 *         return new self("Custom error: {$reason}", ['reason' => $reason]);
 *     }
 * }
 * ```
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
class FrameworkException extends RuntimeException implements ContextualExceptionInterface
{
    /**
     * Additional context data for debugging.
     *
     * @var array<string, mixed>
     */
    protected array $context = [];

    /**
     * Create a new framework exception.
     *
     * @param string $message The exception message
     * @param array<string, mixed> $context Additional context data for debugging
     * @param Throwable|null $previous Previous exception for chaining
     */
    public function __construct(
        string $message = '',
        array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
        $this->context = $context;
    }

    /**
     * Get the contextual data associated with this exception.
     *
     * @return array<string, mixed> The context data
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Merge extra context into the exception.
     *
     * Allows adding additional context after the exception is created.
     * Context is merged with existing data, with new values taking precedence.
     *
     * @param array<string, mixed> $context Additional context to merge
     * @return self Returns self for method chaining
     */
    public function withContext(array $context): self
    {
        $this->context = array_merge($this->context, $context);
        return $this;
    }

    /**
     * Serialize the error into a log/API-friendly payload.
     *
     * Converts the exception to an array format suitable for
     * JSON responses or structured logging.
     *
     * @return array<string, mixed> The serialized exception data
     */
    public function toArray(): array
    {
        return [
            'error' => true,
            'message' => $this->getMessage(),
            'context' => $this->context,
        ];
    }
}
