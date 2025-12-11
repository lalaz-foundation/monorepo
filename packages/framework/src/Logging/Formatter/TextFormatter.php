<?php

declare(strict_types=1);

namespace Lalaz\Logging\Formatter;

use Lalaz\Logging\Contracts\FormatterInterface;

/**
 * Text formatter for human-readable log output.
 *
 * Produces log lines in the format:
 * [2024-01-15 10:30:45] INFO: Message {"context":"data"}
 *
 * Best suited for development and console output where
 * human readability is preferred over machine parsing.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
final class TextFormatter implements FormatterInterface
{
    /**
     * Format a log entry as a human-readable text line.
     *
     * Output format: [YYYY-MM-DD HH:MM:SS] LEVEL: message {"context":"data"}
     *
     * @param string $level The log level (debug, info, warning, error, etc.)
     * @param string $message The log message
     * @param array<string, mixed> $context Additional context data
     * @return string The formatted log line
     */
    public function format(
        string $level,
        string $message,
        array $context = [],
    ): string {
        $contextStr = $context === []
            ? ''
            : ' ' .
                json_encode(
                    $context,
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                );

        return sprintf(
            '[%s] %s: %s%s',
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $contextStr,
        );
    }
}
