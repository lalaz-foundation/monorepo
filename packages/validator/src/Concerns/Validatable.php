<?php

declare(strict_types=1);

namespace Lalaz\Validator\Concerns;

use Lalaz\Validator\Rule;
use Lalaz\Validator\ValidationException;
use Lalaz\Validator\Validator;

/**
 * Trait Validatable
 *
 * Add validation capabilities to any class or model.
 *
 * Usage:
 * ```php
 * class User {
 *     use Validatable;
 *
 *     public string $name;
 *     public string $email;
 *     public int $age;
 *
 *     protected function rules(): array {
 *         return [
 *             'name' => 'required|min:2|max:100',
 *             'email' => 'required|email',
 *             'age' => 'required|int|min:18',
 *         ];
 *     }
 * }
 *
 * $user = new User();
 * $user->fill(['name' => 'John', 'email' => 'john@example.com', 'age' => 25]);
 *
 * if ($user->isValid()) {
 *     // proceed
 * }
 *
 * // Or validate and throw exception
 * $user->validate(); // throws ValidationException on failure
 * ```
 *
 * @package Lalaz\Validator\Concerns
 */
trait Validatable
{
    /**
     * Validation errors from last validation.
     *
     * @var array<string, array<int, string>>
     */
    protected array $validationErrors = [];

    /**
     * The validator instance.
     */
    protected ?Validator $validatorInstance = null;

    /**
     * Define validation rules for this model.
     * Override this method in your class.
     *
     * @return array<string, string|array|Rule>
     */
    protected function rules(): array
    {
        return [];
    }

    /**
     * Define custom error messages.
     * Override this method in your class.
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [];
    }

    /**
     * Get data to validate.
     * Override this method if you need custom data extraction.
     *
     * @return array<string, mixed>
     */
    protected function validationData(): array
    {
        // Try to get public properties as data
        $data = [];
        $reflection = new \ReflectionClass($this);

        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();
            if ($property->isInitialized($this)) {
                $data[$name] = $this->{$name};
            } else {
                $data[$name] = null;
            }
        }

        // If class has toArray method, prefer that
        if (method_exists($this, 'toArray')) {
            return $this->toArray();
        }

        // If class has getAttributes method (like ORM models)
        if (method_exists($this, 'getAttributes')) {
            return $this->getAttributes();
        }

        return $data;
    }

    /**
     * Validate the model data.
     *
     * @param array<string, mixed>|null $data Optional data to validate (uses validationData() if null)
     * @param array<string, string|array|Rule>|null $rules Optional rules (uses rules() if null)
     * @return bool True if valid, false otherwise
     */
    public function isValid(?array $data = null, ?array $rules = null): bool
    {
        $this->validationErrors = $this->runValidation($data, $rules);
        return empty($this->validationErrors);
    }

    /**
     * Validate and throw exception on failure.
     *
     * @param array<string, mixed>|null $data Optional data to validate
     * @param array<string, string|array|Rule>|null $rules Optional rules
     * @throws ValidationException When validation fails
     * @return static Returns $this for chaining
     */
    public function validate(?array $data = null, ?array $rules = null): static
    {
        $this->validationErrors = $this->runValidation($data, $rules);

        if (!empty($this->validationErrors)) {
            throw new ValidationException($this->validationErrors);
        }

        return $this;
    }

    /**
     * Validate specific fields only.
     *
     * @param array<int, string> $fields Fields to validate
     * @param array<string, mixed>|null $data Optional data
     * @return bool True if valid
     */
    public function isValidOnly(array $fields, ?array $data = null): bool
    {
        $allRules = $this->rules();
        $rules = array_intersect_key($allRules, array_flip($fields));

        return $this->isValid($data, $rules);
    }

    /**
     * Validate all fields except specified ones.
     *
     * @param array<int, string> $fields Fields to exclude
     * @param array<string, mixed>|null $data Optional data
     * @return bool True if valid
     */
    public function isValidExcept(array $fields, ?array $data = null): bool
    {
        $allRules = $this->rules();
        $rules = array_diff_key($allRules, array_flip($fields));

        return $this->isValid($data, $rules);
    }

    /**
     * Get validation errors from last validation.
     *
     * @return array<string, array<int, string>>
     */
    public function errors(): array
    {
        return $this->validationErrors;
    }

    /**
     * Get first error for a specific field.
     *
     * @param string $field
     * @return string|null
     */
    public function error(string $field): ?string
    {
        return $this->validationErrors[$field][0] ?? null;
    }

    /**
     * Check if a specific field has errors.
     *
     * @param string $field
     * @return bool
     */
    public function hasError(string $field): bool
    {
        return !empty($this->validationErrors[$field]);
    }

    /**
     * Get all error messages as a flat array.
     *
     * @return array<int, string>
     */
    public function errorMessages(): array
    {
        $messages = [];
        foreach ($this->validationErrors as $fieldErrors) {
            foreach ($fieldErrors as $error) {
                $messages[] = $error;
            }
        }
        return $messages;
    }

    /**
     * Get the first error message.
     *
     * @return string|null
     */
    public function firstError(): ?string
    {
        foreach ($this->validationErrors as $fieldErrors) {
            if (!empty($fieldErrors)) {
                return $fieldErrors[0];
            }
        }
        return null;
    }

    /**
     * Clear validation errors.
     *
     * @return static
     */
    public function clearErrors(): static
    {
        $this->validationErrors = [];
        return $this;
    }

    /**
     * Fill model with data and optionally validate.
     *
     * @param array<string, mixed> $data
     * @param bool $validate Whether to validate after filling
     * @return static
     * @throws ValidationException If $validate is true and validation fails
     */
    public function fill(array $data, bool $validate = false): static
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $this->castToPropertyType($key, $value);
            }
        }

        if ($validate) {
            $this->validate($data);
        }

        return $this;
    }

    /**
     * Cast a value to match the property's declared type.
     *
     * @param string $property The property name
     * @param mixed $value The value to cast
     * @return mixed The cast value
     */
    private function castToPropertyType(string $property, mixed $value): mixed
    {
        try {
            $reflection = new \ReflectionProperty($this, $property);
            $type = $reflection->getType();

            if (!$type instanceof \ReflectionNamedType) {
                return $value;
            }

            $typeName = $type->getName();

            // Handle null values
            if ($value === null) {
                return $type->allowsNull() ? null : $value;
            }

            // Cast based on type
            return match ($typeName) {
                'bool' => $this->castToBool($value),
                'int' => (int) $value,
                'float' => (float) $value,
                'string' => (string) $value,
                'array' => (array) $value,
                default => $value,
            };
        } catch (\ReflectionException) {
            return $value;
        }
    }

    /**
     * Cast a value to boolean.
     *
     * Handles common truthy values from HTML forms (checkbox "on", "1", "true").
     *
     * @param mixed $value
     * @return bool
     */
    private function castToBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $lower = strtolower($value);
            return in_array($lower, ['1', 'true', 'on', 'yes'], true);
        }

        return (bool) $value;
    }

    /**
     * Fill model with data and validate (throws on failure).
     *
     * @param array<string, mixed> $data
     * @return static
     * @throws ValidationException If validation fails
     */
    public function fillAndValidate(array $data): static
    {
        return $this->fill($data, true);
    }

    /**
     * Create a new instance with data and validate.
     *
     * @param array<string, mixed> $data
     * @return static
     * @throws ValidationException If validation fails
     */
    public static function createValidated(array $data): static
    {
        $instance = new static();
        return $instance->fillAndValidate($data);
    }

    /**
     * Run the validation.
     *
     * @param array<string, mixed>|null $data
     * @param array<string, string|array|Rule>|null $rules
     * @return array<string, array<int, string>>
     */
    protected function runValidation(?array $data, ?array $rules): array
    {
        $data = $data ?? $this->validationData();
        $rules = $rules ?? $this->rules();

        if (empty($rules)) {
            return [];
        }

        // Apply custom messages to rules
        $messages = $this->messages();
        if (!empty($messages)) {
            $rules = $this->applyCustomMessages($rules, $messages);
        }

        return $this->getValidator()->validateData($data, $rules);
    }

    /**
     * Apply custom messages to rules.
     *
     * @param array<string, string|array|Rule> $rules
     * @param array<string, string> $messages
     * @return array<string, string|array|Rule>
     */
    protected function applyCustomMessages(array $rules, array $messages): array
    {
        foreach ($messages as $key => $message) {
            // Format: field.rule => message (e.g., 'email.required' => 'Email is required')
            if (str_contains($key, '.')) {
                [$field, $rule] = explode('.', $key, 2);
                if (isset($rules[$field]) && is_string($rules[$field])) {
                    // Append message to the specific rule
                    $rules[$field] = str_replace(
                        $rule,
                        "{$rule}|message:{$message}",
                        $rules[$field]
                    );
                }
            }
        }
        return $rules;
    }

    /**
     * Get the validator instance.
     *
     * @return Validator
     */
    protected function getValidator(): Validator
    {
        if ($this->validatorInstance === null) {
            $this->validatorInstance = new Validator();
        }
        return $this->validatorInstance;
    }

    /**
     * Set a custom validator instance.
     *
     * @param Validator $validator
     * @return static
     */
    public function setValidator(Validator $validator): static
    {
        $this->validatorInstance = $validator;
        return $this;
    }
}
