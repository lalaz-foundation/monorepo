<?php

declare(strict_types=1);

namespace Lalaz\Logging;

use Lalaz\Logging\Contracts\FormatterInterface;
use Lalaz\Logging\Formatter\TextFormatter;
use Lalaz\Logging\Writer\ConsoleWriter;
use Lalaz\Logging\Writer\FileWriter;
use Psr\Log\LoggerInterface;

/**
 * Multi-channel log manager.
 *
 * Manages multiple logging channels (app, security, audit, etc.)
 * with independent configuration for each channel. Supports various
 * drivers including single file, daily rotating, stack, and console.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
final class LogManager implements LoggerInterface
{
    /**
     * Named logger instances.
     *
     * @var array<string, Logger>
     */
    private array $channels = [];

    /**
     * Channel configuration array.
     *
     * @var array<string, mixed>
     */
    private array $config;

    /**
     * The default channel name.
     *
     * @var string
     */
    private string $defaultChannel;

    /**
     * Application base path for resolving relative paths.
     *
     * @var string|null
     */
    private ?string $basePath;

    /**
     * Create a new LogManager instance.
     *
     * @param array<string, mixed> $config Configuration array with channels and default.
     * @param string|null $basePath Application base path for resolving relative log paths.
     */
    public function __construct(array $config = [], ?string $basePath = null)
    {
        $this->config = $config;
        $this->defaultChannel = $config['default'] ?? 'app';
        $this->basePath = $basePath;
    }

    /**
     * Set the application base path.
     *
     * @param string $basePath The application root directory.
     * @return self
     */
    public function setBasePath(string $basePath): self
    {
        $this->basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
        return $this;
    }

    /**
     * Resolve a log path, converting relative paths to absolute.
     *
     * @param string $path The path from configuration.
     * @return string The resolved absolute path.
     */
    private function resolvePath(string $path): string
    {
        // Already absolute
        if (str_starts_with($path, '/') || preg_match('/^[a-zA-Z]:/', $path)) {
            return $path;
        }

        // Resolve relative to basePath
        if ($this->basePath !== null) {
            return $this->basePath . DIRECTORY_SEPARATOR . $path;
        }

        // Fallback to current directory
        return $path;
    }

    /**
     * Get a logger instance for a specific channel.
     *
     * @param string|null $channel Channel name (null for default).
     * @return Logger The logger for the requested channel.
     */
    public function channel(?string $channel = null): Logger
    {
        $channel = $channel ?? $this->defaultChannel;

        if (!isset($this->channels[$channel])) {
            $this->channels[$channel] = $this->createChannel($channel);
        }

        return $this->channels[$channel];
    }

    /**
     * Create a logger for the specified channel.
     *
     * @param string $channel Channel name.
     * @return Logger The created logger.
     */
    private function createChannel(string $channel): Logger
    {
        $channelConfig = $this->config['channels'][$channel] ?? [];
        $driver = $channelConfig['driver'] ?? 'single';

        return match ($driver) {
            'single' => $this->createSingleDriver($channelConfig),
            'daily' => $this->createDailyDriver($channelConfig),
            'stack' => $this->createStackDriver($channelConfig),
            'console' => $this->createConsoleDriver($channelConfig),
            default => $this->createSingleDriver($channelConfig),
        };
    }

    /**
     * Create a single file logger.
     *
     * @param array<string, mixed> $config Channel configuration.
     * @return Logger The configured logger.
     */
    private function createSingleDriver(array $config): Logger
    {
        $level = $config['level'] ?? LogLevel::DEBUG;
        $path = $this->resolvePath($config['path'] ?? 'storage/logs/app.log');
        $formatter = $this->createFormatter($config);

        $logger = new Logger($formatter, $level);
        $logger->pushWriter(new FileWriter($path));

        return $logger;
    }

    /**
     * Create a daily rotating file logger.
     *
     * @param array<string, mixed> $config Channel configuration.
     * @return Logger The configured logger.
     */
    private function createDailyDriver(array $config): Logger
    {
        $level = $config['level'] ?? LogLevel::DEBUG;
        $path = $this->resolvePath($config['path'] ?? 'storage/logs/app.log');
        $days = $config['days'] ?? 14;
        $formatter = $this->createFormatter($config);

        // Insert date into filename
        $pathInfo = pathinfo($path);
        $dailyPath = sprintf(
            '%s/%s-%s.%s',
            $pathInfo['dirname'],
            $pathInfo['filename'],
            date('Y-m-d'),
            $pathInfo['extension'] ?? 'log',
        );

        $logger = new Logger($formatter, $level);
        $logger->pushWriter(new FileWriter($dailyPath, 10485760, $days));

        return $logger;
    }

    /**
     * Create a stack of multiple channels.
     *
     * @param array<string, mixed> $config Channel configuration.
     * @return Logger The configured logger.
     */
    private function createStackDriver(array $config): Logger
    {
        $level = $config['level'] ?? LogLevel::DEBUG;
        $formatter = $this->createFormatter($config);
        $logger = new Logger($formatter, $level);

        $channels = $config['channels'] ?? ['single'];

        foreach ($channels as $channelName) {
            $channelLogger = $this->channel($channelName);
            // Get writers from the channel logger and add them
            // For simplicity, we'll recreate the writers
            $channelConfig = $this->config['channels'][$channelName] ?? [];
            $this->addWritersToLogger($logger, $channelConfig);
        }

        return $logger;
    }

    /**
     * Create a console logger.
     *
     * @param array<string, mixed> $config Channel configuration.
     * @return Logger The configured logger.
     */
    private function createConsoleDriver(array $config): Logger
    {
        $level = $config['level'] ?? LogLevel::DEBUG;
        $formatter = $this->createFormatter($config);

        $logger = new Logger($formatter, $level);
        $logger->pushWriter(new ConsoleWriter());

        return $logger;
    }

    /**
     * Create a formatter based on config.
     *
     * @param array<string, mixed> $config Channel configuration.
     * @return FormatterInterface The configured formatter.
     */
    private function createFormatter(array $config): FormatterInterface
    {
        $format = $config['formatter'] ?? 'text';

        return match ($format) {
            'json' => new Formatter\JsonFormatter(),
            default => new TextFormatter(),
        };
    }

    /**
     * Add writers to an existing logger based on configuration.
     *
     * @param Logger $logger The logger to add writers to.
     * @param array<string, mixed> $config Channel configuration.
     * @return void
     */
    private function addWritersToLogger(Logger $logger, array $config): void
    {
        $driver = $config['driver'] ?? 'single';

        match ($driver) {
            'single', 'daily' => $logger->pushWriter(
                new FileWriter($this->resolvePath($config['path'] ?? 'storage/logs/app.log')),
            ),
            'console' => $logger->pushWriter(new ConsoleWriter()),
            default => null,
        };
    }

    /**
     * Get the default channel name.
     *
     * @return string The default channel name.
     */
    public function getDefaultChannel(): string
    {
        return $this->defaultChannel;
    }

    /**
     * Set the default channel.
     *
     * @param string $channel The channel name to set as default.
     * @return void
     */
    public function setDefaultChannel(string $channel): void
    {
        $this->defaultChannel = $channel;
    }

    /**
     * Get all configured channel names.
     *
     * @return array<string> List of channel names.
     */
    public function getChannels(): array
    {
        return array_keys($this->config['channels'] ?? []);
    }

    // =========================================================================
    // PSR-3 LoggerInterface implementation - delegates to default channel
    // =========================================================================

    /**
     * {@inheritdoc}
     */
    public function emergency(\Stringable|string $message, array $context = []): void
    {
        $this->channel()->emergency($message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function alert(\Stringable|string $message, array $context = []): void
    {
        $this->channel()->alert($message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function critical(\Stringable|string $message, array $context = []): void
    {
        $this->channel()->critical($message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function error(\Stringable|string $message, array $context = []): void
    {
        $this->channel()->error($message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function warning(\Stringable|string $message, array $context = []): void
    {
        $this->channel()->warning($message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function notice(\Stringable|string $message, array $context = []): void
    {
        $this->channel()->notice($message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function info(\Stringable|string $message, array $context = []): void
    {
        $this->channel()->info($message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function debug(\Stringable|string $message, array $context = []): void
    {
        $this->channel()->debug($message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->channel()->log($level, $message, $context);
    }

    /**
     * Create a LogManager from configuration array.
     *
     * @param array<string, mixed> $config Configuration array.
     * @return self The created LogManager.
     */
    public static function create(array $config = []): self
    {
        return new self($config);
    }
}
