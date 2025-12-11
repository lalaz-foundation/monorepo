<?php

declare(strict_types=1);

namespace Lalaz\Reactive\Concerns;

use Lalaz\Validation\Validator;

/**
 * ValidatesInput trait
 *
 * Provides validation capabilities to ReactiveComponent
 *
 * @package lalaz/reactive
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
trait ValidatesInput
{
    /**
     * Validation errors
     */
    protected array $errors = [];

    /**
     * Validate component properties
     *
     * @param array $rules Validation rules
     * @param array $messages Custom error messages
     * @return bool Validation passed
     * @throws \Exception If validation fails
     */
    protected function validate(array $rules, array $messages = []): bool
    {
        $data = $this->getPublicProperties();

        $validator = new Validator($data, $rules, $messages);

        if ($validator->fails()) {
            $this->errors = $validator->errors();
            throw new \Exception('Validation failed: ' . json_encode($this->errors));
        }

        $this->errors = [];
        return true;
    }

    /**
     * Get validation errors
     *
     * @return array Validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Check if there are validation errors
     *
     * @return bool Has errors
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Get error for a specific field
     *
     * @param string $field Field name
     * @return string|null Error message
     */
    public function getError(string $field): ?string
    {
        return $this->errors[$field] ?? null;
    }
}
