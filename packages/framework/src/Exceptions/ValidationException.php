<?php

declare(strict_types=1);

namespace Lalaz\Exceptions;

use Lalaz\Exceptions\Contracts\ContextualExceptionInterface;
use RuntimeException;
use Throwable;

/**
 * Exception thrown when validation rules fail for incoming data.
 *
 * Contains field-specific error messages organized by field name.
 * Each field can have multiple validation error messages.
 *
 * Example usage:
 * ```php
 * $errors = [
 *     'email' => ['The email field is required.', 'The email must be valid.'],
 *     'password' => ['The password must be at least 8 characters.'],
 * ];
 *
 * throw new ValidationException($errors, 'Validation failed', [
 *     'input' => $request->all(),
 * ]);
 *
 * // Later, in error handling
 * try {
 *     $validator->validate($data);
 * } catch (ValidationException $e) {
 *     return response()->json($e->toArray(), 422);
 * }
 * ```
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
class ValidationException extends RuntimeException implements ContextualExceptionInterface
{
    /**
     * Validation errors organized by field name.
     *
     * @var array<string, array<int, string>>
     */
    protected array $errors = [];

    /**
     * Additional context data for debugging.
     *
     * @var array<string, mixed>
     */
    protected array $context = [];

    /**
     * Create a new validation exception.
     *
     * @param array<string, array<int, string>> $errors Field-specific error messages
     * @param string $message General error message
     * @param array<string, mixed> $context Additional context data
     * @param Throwable|null $previous Previous exception for chaining
     */
    public function __construct(
        array $errors,
        string $message = 'Validation failed',
        array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
        $this->errors = $errors;
        $this->context = $context;
    }

    /**
     * Get the validation errors.
     *
     * Returns an associative array where keys are field names
     * and values are arrays of error messages for that field.
     *
     * @return array<string, array<int, string>> The validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
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
     * Serialize the exception to an array.
     *
     * Returns a structure suitable for JSON API responses with
     * all validation errors organized by field.
     *
     * @return array<string, mixed> The serialized exception data
     */
    public function toArray(): array
    {
        return [
            'error' => true,
            'message' => $this->getMessage(),
            'errors' => $this->errors,
            'context' => $this->context,
        ];
    }
}
