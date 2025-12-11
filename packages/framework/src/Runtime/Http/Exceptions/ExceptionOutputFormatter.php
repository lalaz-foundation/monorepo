<?php

declare(strict_types=1);

namespace Lalaz\Runtime\Http\Exceptions;

/**
 * Helper for sanitizing exception context and formatting error payloads.
 *
 * Provides utilities for:
 * - Filtering sensitive context data in production
 * - Formatting exception details for output
 * - Building human-readable error messages
 * - JSON formatting of debug data
 *
 * In debug mode, all context is exposed (with HTML escaping for safety).
 * In production, only whitelisted "safe" keys are included.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
class ExceptionOutputFormatter
{
    /**
     * Create a new exception output formatter.
     *
     * @param bool $debug Whether debug mode is enabled.
     * @param array<int, string> $safeContextKeys Context keys allowed in production.
     */
    public function __construct(
        private bool $debug,
        private array $safeContextKeys,
    ) {
    }

    /**
     * Check if debug mode is enabled.
     *
     * @return bool True if debug mode is active.
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * Filter exception context based on debug mode.
     *
     * In production, only keys in the safe list are included.
     * In debug mode, all keys are included with HTML escaping.
     *
     * @param array<string, mixed> $context The full exception context.
     * @return array<string, mixed> The filtered context.
     */
    public function filterContext(array $context): array
    {
        if (!$this->debug) {
            return array_filter(
                $context,
                fn ($key) => in_array($key, $this->safeContextKeys, true),
                ARRAY_FILTER_USE_KEY,
            );
        }

        return array_map(
            static fn ($value) => is_string($value)
                ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
                : $value,
            $context,
        );
    }

    /**
     * Build a plain text error message with optional context.
     *
     * In debug mode, appends formatted context to the message.
     * In production, returns just the message.
     *
     * @param string $message The base error message.
     * @param array<string, mixed> $context Additional context data.
     * @return string The formatted message.
     */
    public function buildPlainMessage(string $message, array $context): string
    {
        if (!$this->debug || $context === []) {
            return $message;
        }

        return $message . PHP_EOL . $this->formatArray($context);
    }

    /**
     * Format an array as pretty-printed JSON.
     *
     * @param array<string, mixed> $data The data to format.
     * @return string JSON-encoded string or empty string on failure.
     */
    public function formatArray(array $data): string
    {
        return json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ) ?: '';
    }
}
