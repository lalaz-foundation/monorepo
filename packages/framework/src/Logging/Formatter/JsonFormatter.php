<?php

declare(strict_types=1);

namespace Lalaz\Logging\Formatter;

use Lalaz\Logging\Contracts\FormatterInterface;

/**
 * JSON formatter for structured logging.
 *
 * Outputs logs as JSON objects, ideal for log aggregation systems
 * like ELK Stack, Datadog, CloudWatch, etc.
 *
 *  * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
final class JsonFormatter implements FormatterInterface
{
    private bool $prettyPrint;
    private bool $includeTimestamp;
    private ?string $channel;

    /**
     * @param bool $prettyPrint Format JSON with indentation (dev mode)
     * @param bool $includeTimestamp Include ISO 8601 timestamp
     * @param string|null $channel Optional channel name to include
     */
    public function __construct(
        bool $prettyPrint = false,
        bool $includeTimestamp = true,
        ?string $channel = null,
    ) {
        $this->prettyPrint = $prettyPrint;
        $this->includeTimestamp = $includeTimestamp;
        $this->channel = $channel;
    }

    public function format(
        string $level,
        string $message,
        array $context = [],
    ): string {
        $entry = [];

        if ($this->includeTimestamp) {
            $entry['timestamp'] = date('c');
        }

        $entry['level'] = strtolower($level);

        if ($this->channel !== null) {
            $entry['channel'] = $this->channel;
        }

        $entry['message'] = $message;

        // Handle exception in context specially
        if (isset($context['exception']) && $context['exception'] instanceof \Throwable) {
            $exception = $context['exception'];
            $context['exception'] = [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ];
        }

        if (!empty($context)) {
            $entry['context'] = $context;
        }

        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

        if ($this->prettyPrint) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $json = json_encode($entry, $flags);

        if ($json === false) {
            // Fallback if JSON encoding fails
            return json_encode([
                'timestamp' => date('c'),
                'level' => 'error',
                'message' => 'Failed to encode log message: ' . json_last_error_msg(),
                'original_message' => $message,
            ], $flags) ?: '{"error":"json_encode_failed"}';
        }

        return $json;
    }

    /**
     * Create a formatter with a specific channel.
     *
     * @param string $channel The channel name
     * @return self
     */
    public function withChannel(string $channel): self
    {
        return new self($this->prettyPrint, $this->includeTimestamp, $channel);
    }
}
