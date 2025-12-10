<?php

declare(strict_types=1);

namespace Lalaz\Web\Http;

use Lalaz\Web\View\ErrorBag;

/**
 * Manages flash data for views (old input and validation errors).
 *
 * Uses session storage to persist data across redirects.
 * Data is automatically cleared after being read (flash behavior).
 *
 * @package Lalaz\Web\Http
 */
class ViewDataBag
{
    /**
     * Session key for old input data.
     */
    private const OLD_INPUT_KEY = '_old_input';

    /**
     * Session key for validation errors.
     */
    private const ERRORS_KEY = '_errors';

    /**
     * Cached old input (to avoid multiple session reads).
     *
     * @var array<string, mixed>|null
     */
    private static ?array $oldInputCache = null;

    /**
     * Cached errors (to avoid multiple session reads).
     *
     * @var ErrorBag|null
     */
    private static ?ErrorBag $errorsCache = null;

    /**
     * Whether old input has been read from session.
     */
    private static bool $oldInputRead = false;

    /**
     * Whether errors have been read from session.
     */
    private static bool $errorsRead = false;

    /**
     * Flash input data to the session for the next request.
     *
     * @param array<string, mixed> $input Input data to flash
     * @return void
     */
    public static function flashInput(array $input): void
    {
        SessionManager::setValue(self::OLD_INPUT_KEY, $input);
    }

    /**
     * Flash validation errors to the session for the next request.
     *
     * @param array<string, array<int, string>>|ErrorBag $errors Errors to flash
     * @return void
     */
    public static function flashErrors(array|ErrorBag $errors): void
    {
        $data = $errors instanceof ErrorBag ? $errors->toArray() : $errors;
        SessionManager::setValue(self::ERRORS_KEY, $data);
    }

    /**
     * Get old input value.
     *
     * On first call, reads from session and caches the data.
     * Session data is cleared after first read.
     *
     * @param string|null $key Specific key or null for all input
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    public static function getOldInput(?string $key = null, mixed $default = null): mixed
    {
        // Load and cache on first access
        if (!self::$oldInputRead) {
            self::$oldInputCache = SessionManager::getValue(self::OLD_INPUT_KEY, []);
            SessionManager::unset(self::OLD_INPUT_KEY);
            self::$oldInputRead = true;
        }

        $data = self::$oldInputCache ?? [];

        if ($key === null) {
            return $data;
        }

        return $data[$key] ?? $default;
    }

    /**
     * Get validation errors.
     *
     * On first call, reads from session and caches the data.
     * Session data is cleared after first read.
     *
     * @return ErrorBag
     */
    public static function getErrors(): ErrorBag
    {
        // Load and cache on first access
        if (!self::$errorsRead) {
            $errors = SessionManager::getValue(self::ERRORS_KEY, []);
            SessionManager::unset(self::ERRORS_KEY);
            self::$errorsCache = ErrorBag::fromArray($errors);
            self::$errorsRead = true;
        }

        return self::$errorsCache ?? ErrorBag::empty();
    }

    /**
     * Check if there are any validation errors.
     *
     * @param string|null $field Check specific field or any if null
     * @return bool
     */
    public static function hasErrors(?string $field = null): bool
    {
        return self::getErrors()->has($field);
    }

    /**
     * Get the first error message for a field.
     *
     * @param string|null $field Field name or null for first error
     * @return string|null
     */
    public static function getFirstError(?string $field = null): ?string
    {
        return self::getErrors()->first($field);
    }

    /**
     * Get all error messages as a flat array.
     *
     * @return array<int, string>
     */
    public static function getAllErrors(): array
    {
        return self::getErrors()->all();
    }

    /**
     * Reset the cached data (useful for testing).
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$oldInputCache = null;
        self::$errorsCache = null;
        self::$oldInputRead = false;
        self::$errorsRead = false;
    }

    /**
     * Check if there is any old input data.
     *
     * @param string|null $key Check specific key or any if null
     * @return bool
     */
    public static function hasOldInput(?string $key = null): bool
    {
        $data = self::getOldInput();

        if ($key === null) {
            return !empty($data);
        }

        return isset($data[$key]);
    }
}
