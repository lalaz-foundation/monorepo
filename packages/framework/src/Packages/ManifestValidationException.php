<?php

declare(strict_types=1);

namespace Lalaz\Packages;

use Lalaz\Exceptions\FrameworkException;
use Throwable;

/**
 * Exception thrown when package manifest validation fails.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 */
final class ManifestValidationException extends FrameworkException
{
    /**
     * Create exception for missing required field.
     *
     * @param string $field The missing field name
     * @param string|null $package The package name (optional)
     * @return self
     */
    public static function missingField(string $field, ?string $package = null): self
    {
        $context = ['field' => $field];

        if ($package !== null) {
            $context['package'] = $package;
        }

        return new self(
            "Missing required field '{$field}' in package manifest.",
            $context,
        );
    }

    /**
     * Create exception for invalid field value.
     *
     * @param string $field The field name
     * @param mixed $value The invalid value
     * @param string $expected Expected type or format
     * @return self
     */
    public static function invalidField(string $field, mixed $value, string $expected): self
    {
        return new self(
            "Invalid value for field '{$field}'. Expected {$expected}.",
            [
                'field' => $field,
                'value' => $value,
                'expected' => $expected,
            ],
        );
    }

    /**
     * Create exception for file not found.
     *
     * @param string $file The manifest file path
     * @param Throwable|null $previous Previous exception
     * @return self
     */
    public static function fileNotFound(string $file, ?Throwable $previous = null): self
    {
        return new self(
            "Package manifest file not found: '{$file}'.",
            ['file' => $file],
            $previous,
        );
    }

    /**
     * Create exception for parse errors.
     *
     * @param string $file The manifest file path
     * @param string|null $reason The parse error reason
     * @param Throwable|null $previous Previous exception
     * @return self
     */
    public static function parseError(string $file, ?string $reason = null, ?Throwable $previous = null): self
    {
        $message = "Failed to parse package manifest '{$file}'.";

        if ($reason !== null) {
            $message .= " {$reason}";
        }

        return new self(
            $message,
            ['file' => $file, 'reason' => $reason],
            $previous,
        );
    }
}
