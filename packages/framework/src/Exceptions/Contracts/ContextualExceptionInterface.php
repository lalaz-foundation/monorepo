<?php

declare(strict_types=1);

namespace Lalaz\Exceptions\Contracts;

/**
 * Contract for exceptions that expose structured context for logs/API responses.
 *
 * Implement this interface to ensure exceptions can provide contextual
 * information for debugging and can be serialized to arrays for API responses.
 *
 * Example implementation:
 * ```php
 * class MyException extends Exception implements ContextualExceptionInterface
 * {
 *     private array $context = [];
 *
 *     public function getContext(): array
 *     {
 *         return $this->context;
 *     }
 *
 *     public function toArray(): array
 *     {
 *         return [
 *             'error' => true,
 *             'message' => $this->getMessage(),
 *             'context' => $this->context,
 *         ];
 *     }
 * }
 * ```
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
interface ContextualExceptionInterface
{
    /**
     * Get arbitrary structured context describing the error.
     *
     * Returns an associative array with key-value pairs providing
     * additional details about the exception circumstances.
     *
     * @return array<string, mixed> The exception context
     */
    public function getContext(): array;

    /**
     * Serialize the exception into an array payload suitable for APIs/logs.
     *
     * Returns a structure that can be JSON-encoded for API responses
     * or used for structured logging.
     *
     * @return array<string, mixed> The serialized exception data
     */
    public function toArray(): array;
}
