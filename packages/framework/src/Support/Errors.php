<?php

declare(strict_types=1);

namespace Lalaz\Support;

use Lalaz\Exceptions\FrameworkException;
use Throwable;

/**
 * Small helper to throw consistent framework errors with context.
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
final class Errors
{
    /**
     * Throw a configuration error.
     *
     * @param string $message Error message
     * @param array<string, mixed> $context Error context
     * @param Throwable|null $previous Previous exception
     * @return never
     * @throws FrameworkException
     */
    public static function throwConfigurationError(
        string $message,
        array $context = [],
        ?Throwable $previous = null,
    ): never {
        throw new FrameworkException($message, $context, $previous);
    }

    /**
     * Throw a runtime error.
     *
     * @param string $message Error message
     * @param array<string, mixed> $context Error context
     * @param Throwable|null $previous Previous exception
     * @return never
     * @throws FrameworkException
     */
    public static function throwRuntimeError(
        string $message,
        array $context = [],
        ?Throwable $previous = null,
    ): never {
        throw new FrameworkException($message, $context, $previous);
    }

    /**
     * Throw an invalid argument error.
     *
     * @param string $message Error message
     * @param array<string, mixed> $context Error context
     * @param Throwable|null $previous Previous exception
     * @return never
     * @throws FrameworkException
     */
    public static function throwInvalidArgument(
        string $message,
        array $context = [],
        ?Throwable $previous = null,
    ): never {
        throw new FrameworkException($message, $context, $previous);
    }
}
