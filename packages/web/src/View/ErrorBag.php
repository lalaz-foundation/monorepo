<?php

declare(strict_types=1);

namespace Lalaz\Web\View;

/**
 * Container for validation errors.
 *
 * Provides a structured way to store and retrieve validation errors
 * from form submissions. Works with flash session data to persist
 * errors across redirects.
 *
 * @package Lalaz\Web\View
 */
class ErrorBag
{
    /**
     * Validation errors indexed by field name.
     *
     * @var array<string, array<int, string>>
     */
    private array $errors = [];

    /**
     * Create a new ErrorBag instance.
     *
     * @param array<string, array<int, string>> $errors Initial errors
     */
    public function __construct(array $errors = [])
    {
        $this->errors = $errors;
    }

    /**
     * Add an error message for a field.
     *
     * @param string $field Field name
     * @param string $message Error message
     * @return self
     */
    public function add(string $field, string $message): self
    {
        $this->errors[$field] ??= [];
        $this->errors[$field][] = $message;
        return $this;
    }

    /**
     * Check if errors exist.
     *
     * @param string|null $field Check specific field or any errors if null
     * @return bool
     */
    public function has(?string $field = null): bool
    {
        if ($field === null) {
            return !empty($this->errors);
        }

        return isset($this->errors[$field]) && !empty($this->errors[$field]);
    }

    /**
     * Get all error messages for a field.
     *
     * @param string $field Field name
     * @return array<int, string>
     */
    public function get(string $field): array
    {
        return $this->errors[$field] ?? [];
    }

    /**
     * Get the first error message for a field.
     *
     * @param string|null $field Field name or null for first error of any field
     * @return string|null
     */
    public function first(?string $field = null): ?string
    {
        if ($field !== null) {
            return $this->errors[$field][0] ?? null;
        }

        // Return first error from first field
        foreach ($this->errors as $messages) {
            if (!empty($messages)) {
                return $messages[0];
            }
        }

        return null;
    }

    /**
     * Get all error messages as a flat array.
     *
     * @return array<int, string>
     */
    public function all(): array
    {
        $all = [];

        foreach ($this->errors as $messages) {
            foreach ($messages as $message) {
                $all[] = $message;
            }
        }

        return $all;
    }

    /**
     * Get errors as array indexed by field.
     *
     * @return array<string, array<int, string>>
     */
    public function toArray(): array
    {
        return $this->errors;
    }

    /**
     * Check if the error bag is empty.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->errors);
    }

    /**
     * Check if the error bag is not empty.
     *
     * @return bool
     */
    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * Get the total count of error messages.
     *
     * @return int
     */
    public function count(): int
    {
        $count = 0;

        foreach ($this->errors as $messages) {
            $count += count($messages);
        }

        return $count;
    }

    /**
     * Get all field names that have errors.
     *
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys($this->errors);
    }

    /**
     * Merge another ErrorBag into this one.
     *
     * @param ErrorBag $other
     * @return self
     */
    public function merge(ErrorBag $other): self
    {
        foreach ($other->toArray() as $field => $messages) {
            foreach ($messages as $message) {
                $this->add($field, $message);
            }
        }

        return $this;
    }

    /**
     * Create an ErrorBag from an array of errors.
     *
     * Handles both flat arrays and nested arrays:
     * - ['field' => 'message']
     * - ['field' => ['message1', 'message2']]
     *
     * @param array<string, string|array<int, string>> $errors
     * @return self
     */
    public static function fromArray(array $errors): self
    {
        $bag = new self();

        foreach ($errors as $field => $messages) {
            foreach ((array) $messages as $message) {
                $bag->add($field, $message);
            }
        }

        return $bag;
    }

    /**
     * Create an empty ErrorBag.
     *
     * @return self
     */
    public static function empty(): self
    {
        return new self();
    }
}
