<?php

declare(strict_types=1);

namespace Lalaz\Exceptions;

use Throwable;

/**
 * Exception thrown for configuration-related errors.
 *
 * Use this exception when:
 * - Configuration files cannot be loaded or parsed
 * - Required configuration values are missing
 * - Configuration values have invalid types or formats
 * - Environment variables are missing or invalid
 *
 * Example usage:
 * ```php
 * // Missing required key
 * throw ConfigurationException::missingKey('database.host', 'config/database.php');
 *
 * // Invalid value type
 * throw ConfigurationException::invalidValue('cache.ttl', 'not-a-number', 'integer');
 *
 * // File loading error
 * throw ConfigurationException::fileError('config/app.php', 'File not found');
 *
 * // Missing environment variable
 * throw ConfigurationException::missingEnvVariable('APP_KEY');
 * ```
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
class ConfigurationException extends FrameworkException
{
    /**
     * Create exception for missing configuration key.
     *
     * Use this when a required configuration value is not present.
     *
     * @param string $key The missing configuration key (dot notation)
     * @param string|null $file The configuration file path (optional)
     * @return self The exception instance
     */
    public static function missingKey(string $key, ?string $file = null): self
    {
        $context = ['key' => $key];

        if ($file !== null) {
            $context['file'] = $file;
        }

        return new self(
            "Missing required configuration key '{$key}'.",
            $context,
        );
    }

    /**
     * Create exception for invalid configuration value.
     *
     * Use this when a configuration value has an unexpected type or format.
     *
     * @param string $key The configuration key (dot notation)
     * @param mixed $value The invalid value that was found
     * @param string $expectedType Expected type or format description
     * @return self The exception instance
     */
    public static function invalidValue(string $key, mixed $value, string $expectedType): self
    {
        return new self(
            "Invalid configuration value for '{$key}'. Expected {$expectedType}.",
            [
                'key' => $key,
                'value' => $value,
                'expected_type' => $expectedType,
                'actual_type' => get_debug_type($value),
            ],
        );
    }

    /**
     * Create exception for file loading errors.
     *
     * Use this when a configuration file cannot be loaded or parsed.
     *
     * @param string $file The file path that could not be loaded
     * @param string|null $reason Description of why loading failed
     * @param Throwable|null $previous Previous exception for chaining
     * @return self The exception instance
     */
    public static function fileError(string $file, ?string $reason = null, ?Throwable $previous = null): self
    {
        $message = "Failed to load configuration file '{$file}'.";

        if ($reason !== null) {
            $message .= " {$reason}";
        }

        return new self(
            $message,
            ['file' => $file, 'reason' => $reason],
            $previous,
        );
    }

    /**
     * Create exception for missing environment variable.
     *
     * Use this when a required environment variable is not set.
     *
     * @param string $variable The name of the missing environment variable
     * @return self The exception instance
     */
    public static function missingEnvVariable(string $variable): self
    {
        return new self(
            "Missing required environment variable '{$variable}'.",
            ['variable' => $variable],
        );
    }
}
