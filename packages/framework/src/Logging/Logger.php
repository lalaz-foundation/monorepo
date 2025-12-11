<?php

declare(strict_types=1);

namespace Lalaz\Logging;

use Lalaz\Logging\Contracts\FormatterInterface;
use Lalaz\Logging\Contracts\WriterInterface;
use Lalaz\Logging\Formatter\TextFormatter;
use Psr\Log\LoggerInterface;
use Stringable;

/**
 * PSR-3 compatible logger implementation.
 *
 * Provides logging functionality with configurable formatters and writers.
 * Supports multiple writers for outputting logs to different destinations
 * and level-based filtering.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
final class Logger implements LoggerInterface
{
    /**
     * Registered log writers.
     *
     * @var array<int, WriterInterface>
     */
    private array $writers = [];

    /**
     * Log message formatter.
     *
     * @var FormatterInterface
     */
    private FormatterInterface $formatter;

    /**
     * Minimum log level to process.
     *
     * @var string
     */
    private string $minimumLevel;

    /**
     * Create a new Logger instance.
     *
     * @param FormatterInterface|null $formatter Log formatter (defaults to TextFormatter).
     * @param string $minimumLevel Minimum level to log (defaults to DEBUG).
     */
    public function __construct(
        ?FormatterInterface $formatter = null,
        string $minimumLevel = LogLevel::DEBUG,
    ) {
        $this->formatter = $formatter ?? new TextFormatter();
        $this->minimumLevel = strtoupper($minimumLevel);
    }

    /**
     * Add a writer to the logger.
     *
     * @param WriterInterface $writer The writer to add.
     * @return void
     */
    public function pushWriter(WriterInterface $writer): void
    {
        $this->writers[] = $writer;
    }

    /**
     * Set the formatter for this logger.
     *
     * @param FormatterInterface $formatter
     * @return void
     */
    public function setFormatter(FormatterInterface $formatter): void
    {
        $this->formatter = $formatter;
    }

    /**
     * Get the current formatter.
     *
     * @return FormatterInterface
     */
    public function getFormatter(): FormatterInterface
    {
        return $this->formatter;
    }

    /**
     * Set the minimum log level.
     *
     * @param string $level
     * @return void
     */
    public function setMinimumLevel(string $level): void
    {
        $this->minimumLevel = strtoupper($level);
    }

    /**
     * Log an emergency message.
     *
     * @param Stringable|string $message The log message.
     * @param array<string, mixed> $context Context data.
     * @return void
     */
    public function emergency(Stringable|string $message, array $context = []): void
    {
        $this->log('EMERGENCY', $message, $context);
    }

    /**
     * Log an alert message.
     *
     * @param Stringable|string $message The log message.
     * @param array<string, mixed> $context Context data.
     * @return void
     */
    public function alert(Stringable|string $message, array $context = []): void
    {
        $this->log('ALERT', $message, $context);
    }

    /**
     * Log a critical message.
     *
     * @param Stringable|string $message The log message.
     * @param array<string, mixed> $context Context data.
     * @return void
     */
    public function critical(Stringable|string $message, array $context = []): void
    {
        $this->log('CRITICAL', $message, $context);
    }

    /**
     * Log an error message.
     *
     * @param Stringable|string $message The log message.
     * @param array<string, mixed> $context Context data.
     * @return void
     */
    public function error(Stringable|string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    /**
     * Log a warning message.
     *
     * @param Stringable|string $message The log message.
     * @param array<string, mixed> $context Context data.
     * @return void
     */
    public function warning(Stringable|string $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }

    /**
     * Log a notice message.
     *
     * @param Stringable|string $message The log message.
     * @param array<string, mixed> $context Context data.
     * @return void
     */
    public function notice(Stringable|string $message, array $context = []): void
    {
        $this->log('NOTICE', $message, $context);
    }

    /**
     * Log an info message.
     *
     * @param Stringable|string $message The log message.
     * @param array<string, mixed> $context Context data.
     * @return void
     */
    public function info(Stringable|string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    /**
     * Log a debug message.
     *
     * @param Stringable|string $message The log message.
     * @param array<string, mixed> $context Context data.
     * @return void
     */
    public function debug(Stringable|string $message, array $context = []): void
    {
        $this->log('DEBUG', $message, $context);
    }

    /**
     * Log a message at the specified level.
     *
     * @param mixed $level The log level.
     * @param Stringable|string $message The log message.
     * @param array<string, mixed> $context Context data.
     * @return void
     */
    public function log($level, Stringable|string $message, array $context = []): void
    {
        $level = strtoupper((string) $level);

        if (!LogLevel::shouldLog($level, $this->minimumLevel)) {
            return;
        }

        $line = $this->formatter->format(
            $level,
            $this->interpolate((string) $message, $context),
            $context,
        );

        foreach ($this->writers as $writer) {
            $writer->write($line);
        }
    }

    /**
     * Interpolate context values into message placeholders.
     *
     * Replaces {key} placeholders in the message with values from context.
     *
     * @param string $message The message with placeholders.
     * @param array<string, mixed> $context The context values.
     * @return string The interpolated message.
     */
    private function interpolate(string $message, array $context): string
    {
        $replace = [];

        foreach ($context as $key => $value) {
            if (
                $value === null ||
                is_scalar($value) ||
                ($value instanceof Stringable)
            ) {
                $replace['{' . $key . '}'] = (string) $value;
                continue;
            }

            if (is_array($value)) {
                $replace['{' . $key . '}'] = json_encode($value);
            }
        }

        return strtr($message, $replace);
    }
}
