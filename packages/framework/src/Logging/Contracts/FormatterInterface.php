<?php

declare(strict_types=1);

namespace Lalaz\Logging\Contracts;

/**
 * Contract for log message formatters.
 *
 * Formatters transform log entries into strings suitable for output.
 * Different formatters can produce text, JSON, or other formats.
 *
 * Example implementation:
 * ```php
 * class JsonFormatter implements FormatterInterface
 * {
 *     public function format(string $level, string $message, array $context = []): string
 *     {
 *         return json_encode([
 *             'timestamp' => date('c'),
 *             'level' => $level,
 *             'message' => $message,
 *             'context' => $context,
 *         ]) . PHP_EOL;
 *     }
 * }
 * ```
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
interface FormatterInterface
{
    /**
     * Format a log entry into a string.
     *
     * @param string $level The log level (debug, info, warning, error, etc.)
     * @param string $message The log message (already interpolated)
     * @param array<string, mixed> $context Additional context data
     * @return string The formatted log line
     */
    public function format(
        string $level,
        string $message,
        array $context = [],
    ): string;
}
