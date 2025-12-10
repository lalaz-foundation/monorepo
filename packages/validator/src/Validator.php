<?php

declare(strict_types=1);

namespace Lalaz\Validator;

/**
 * Main Validator class for data validation.
 *
 * Provides a complete API for validating arrays of data against defined rules.
 * Supports string rules, array rules, and fluent Rule builder objects.
 *
 * @package Lalaz\Validator
 */
class Validator
{
    /**
     * Validate data and return errors array.
     *
     * @param array<string, mixed> $data Data to validate
     * @param array<string, string|array<int, string|array<string,mixed>>|Rule> $rules Validation rules
     * @return array<string, array<int, string>> Errors array (empty when valid)
     */
    public function validateData(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $definition) {
            $value = $data[$field] ?? null;
            $parsed = $this->parseRules($field, $definition);

            foreach ($parsed as $rule) {
                $ruleName = is_array($rule)
                    ? $rule['name'] ?? ($rule[0] ?? null)
                    : $rule;
                $custom = is_array($rule) ? $rule['message'] ?? null : null;

                if ($ruleName === null) {
                    continue;
                }

                // required runs even when null/empty
                if ($ruleName === 'required') {
                    if ($value === null || $value === '') {
                        $this->pushError(
                            $errors,
                            $field,
                            $custom ?? 'required',
                        );
                    }
                    continue;
                }

                // skip remaining rules if value is null/empty
                if ($value === null || $value === '') {
                    continue;
                }

                if (
                    $ruleName === 'int' &&
                    filter_var($value, FILTER_VALIDATE_INT) === false
                ) {
                    $this->pushError($errors, $field, $custom ?? 'int');
                } elseif (
                    $ruleName === 'decimal' &&
                    filter_var($value, FILTER_VALIDATE_FLOAT) === false
                ) {
                    $this->pushError($errors, $field, $custom ?? 'decimal');
                } elseif ($ruleName === 'boolean' && !is_bool($value)) {
                    $this->pushError($errors, $field, $custom ?? 'boolean');
                } elseif (
                    $ruleName === 'email' &&
                    filter_var($value, FILTER_VALIDATE_EMAIL) === false
                ) {
                    $this->pushError($errors, $field, $custom ?? 'email');
                } elseif (
                    $ruleName === 'url' &&
                    filter_var($value, FILTER_VALIDATE_URL) === false
                ) {
                    $this->pushError($errors, $field, $custom ?? 'url');
                } elseif (
                    $ruleName === 'domain' &&
                    filter_var(
                        $value,
                        FILTER_VALIDATE_DOMAIN,
                        FILTER_FLAG_HOSTNAME,
                    ) === false
                ) {
                    $this->pushError($errors, $field, $custom ?? 'domain');
                } elseif (
                    $ruleName === 'ip' &&
                    filter_var($value, FILTER_VALIDATE_IP) === false
                ) {
                    $this->pushError($errors, $field, $custom ?? 'ip');
                } elseif (
                    $ruleName === 'min' &&
                    is_array($rule) &&
                    isset($rule['min'])
                ) {
                    if ($this->tooSmall($value, (float) $rule['min'])) {
                        $this->pushError(
                            $errors,
                            $field,
                            $custom ?? "min:{$rule['min']}",
                        );
                    }
                } elseif (
                    $ruleName === 'max' &&
                    is_array($rule) &&
                    isset($rule['max'])
                ) {
                    if ($this->tooLarge($value, (float) $rule['max'])) {
                        $this->pushError(
                            $errors,
                            $field,
                            $custom ?? "max:{$rule['max']}",
                        );
                    }
                } elseif (
                    $ruleName === 'match' &&
                    is_array($rule) &&
                    isset($rule['match'])
                ) {
                    $other = $data[$rule['match']] ?? null;
                    if ($value !== $other) {
                        $this->pushError(
                            $errors,
                            $field,
                            $custom ?? "match:{$rule['match']}",
                        );
                    }
                } elseif (
                    $ruleName === 'regex' &&
                    is_array($rule) &&
                    isset($rule['pattern'])
                ) {
                    if (!preg_match($rule['pattern'], (string) $value)) {
                        $this->pushError($errors, $field, $custom ?? 'regex');
                    }
                } elseif (
                    $ruleName === 'in' &&
                    is_array($rule) &&
                    isset($rule['values'])
                ) {
                    if (!in_array($value, (array) $rule['values'], true)) {
                        $this->pushError($errors, $field, $custom ?? 'in');
                    }
                } elseif (
                    $ruleName === 'not_in' &&
                    is_array($rule) &&
                    isset($rule['values'])
                ) {
                    if (in_array($value, (array) $rule['values'], true)) {
                        $this->pushError($errors, $field, $custom ?? 'not_in');
                    }
                } elseif ($ruleName === 'date') {
                    if (strtotime((string) $value) === false) {
                        $this->pushError($errors, $field, $custom ?? 'date');
                    }
                } elseif (
                    $ruleName === 'date_format' &&
                    is_array($rule) &&
                    isset($rule['format'])
                ) {
                    $dt = \DateTime::createFromFormat(
                        $rule['format'],
                        (string) $value,
                    );
                    if (
                        !$dt ||
                        $dt->format($rule['format']) !== (string) $value
                    ) {
                        $this->pushError(
                            $errors,
                            $field,
                            $custom ?? "date_format:{$rule['format']}",
                        );
                    }
                } elseif ($ruleName === 'json') {
                    json_decode((string) $value, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $this->pushError($errors, $field, $custom ?? 'json');
                    }
                } elseif (
                    $ruleName === 'custom' &&
                    is_array($rule) &&
                    isset($rule['callback'])
                ) {
                    $callback = $rule['callback'];
                    if (
                        is_callable($callback) &&
                        $callback($value, $data) === false
                    ) {
                        $this->pushError($errors, $field, $custom ?? 'custom');
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Validate data with ORM model context.
     *
     * This method is primarily used for ORM integration, validating model data
     * and throwing an exception if validation fails.
     *
     * @param object $model The model instance
     * @param array<string, mixed> $data Data to validate
     * @param array<string, string|array<int, string|array<string,mixed>>|Rule> $rules Validation rules
     * @param string $operation Operation type (create, update, etc.)
     * @throws ValidationException When validation fails
     */
    public function validate(
        object $model,
        array $data,
        array $rules,
        string $operation,
    ): void {
        $errors = $this->validateData($data, $rules);
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }

    /**
     * Alias for validate() - validates model data and throws exception on failure.
     *
     * @param object $model The model instance
     * @param array<string, mixed> $data Data to validate
     * @param array<string, string|array<int, string|array<string,mixed>>|Rule> $rules Validation rules
     * @param string $operation Operation type (create, update, etc.)
     * @throws ValidationException When validation fails
     */
    public function validateModel(
        object $model,
        array $data,
        array $rules,
        string $operation,
    ): void {
        $this->validate($model, $data, $rules, $operation);
    }

    /**
     * Parse rule definition into normalized array format.
     *
     * @param string $field Field name
     * @param string|array<int, string|array<string,mixed>>|Rule $ruleDef Rule definition
     * @return array<int, string|array<string, mixed>> Parsed rules
     */
    protected function parseRules(
        string $field,
        string|array|Rule $ruleDef,
    ): array {
        if ($ruleDef instanceof Rule) {
            $rules = $ruleDef->buildArray();
        } else {
            $rules = RuleParser::parse($ruleDef);
        }

        return RuleParser::normalizeConfirmed($field, $rules);
    }

    /**
     * Check if value is too small (numeric comparison or string length).
     *
     * @param mixed $value Value to check
     * @param float $min Minimum value/length
     * @return bool True if value is too small
     */
    protected function tooSmall(mixed $value, float $min): bool
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value < $min;
        }
        return mb_strlen((string) $value) < $min;
    }

    /**
     * Check if value is too large (numeric comparison or string length).
     *
     * @param mixed $value Value to check
     * @param float $max Maximum value/length
     * @return bool True if value is too large
     */
    protected function tooLarge(mixed $value, float $max): bool
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value > $max;
        }
        return mb_strlen((string) $value) > $max;
    }

    /**
     * Add error message to errors array.
     *
     * @param array<string, array<int, string>> $errors Errors array (by reference)
     * @param string $field Field name
     * @param string $message Error message
     */
    protected function pushError(
        array &$errors,
        string $field,
        string $message,
    ): void {
        $errors[$field] ??= [];
        $errors[$field][] = $message;
    }
}
