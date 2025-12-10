<?php

declare(strict_types=1);

namespace Lalaz\Logging;

use Lalaz\Exceptions\LoggingException;
use Psr\Log\LoggerInterface;
use Stringable;

/**
 * Static facade for logging.
 *
 * Provides convenient static access to the logging system without
 * coupling to a specific Application class.
 *
 *  * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 *
 * @method static void emergency(string|Stringable $message, array $context = [])
 * @method static void alert(string|Stringable $message, array $context = [])
 * @method static void critical(string|Stringable $message, array $context = [])
 * @method static void error(string|Stringable $message, array $context = [])
 * @method static void warning(string|Stringable $message, array $context = [])
 * @method static void notice(string|Stringable $message, array $context = [])
 * @method static void info(string|Stringable $message, array $context = [])
 * @method static void debug(string|Stringable $message, array $context = [])
 */
final class Log
{
    /**
     * @var LoggerInterface|null The logger instance
     */
    private static ?LoggerInterface $logger = null;

    /**
     * @var LogManager|null The log manager instance
     */
    private static ?LogManager $manager = null;

    /**
     * Set the logger instance.
     *
     * @param LoggerInterface $logger
     * @return void
     */
    public static function setLogger(LoggerInterface $logger): void
    {
        self::$logger = $logger;
    }

    /**
     * Set the log manager instance.
     *
     * @param LogManager $manager
     * @return void
     */
    public static function setManager(LogManager $manager): void
    {
        self::$manager = $manager;
        self::$logger = $manager;
    }

    /**
     * Get the logger instance.
     *
     * @return LoggerInterface
     */
    public static function getLogger(): LoggerInterface
    {
        if (self::$logger === null) {
            // Create a default logger if none set
            self::$logger = self::createDefaultLogger();
        }

        return self::$logger;
    }

    /**
     * Get a specific channel logger.
     *
     * @param string $channel
     * @return Logger
     * @throws LoggingException If no LogManager is configured
     */
    public static function channel(string $channel): Logger
    {
        if (self::$manager === null) {
            throw LoggingException::notInitialized();
        }

        return self::$manager->channel($channel);
    }

    /**
     * Create a default logger (console output in debug mode).
     *
     * @return Logger
     */
    private static function createDefaultLogger(): Logger
    {
        $logger = new Logger();
        $logger->pushWriter(new Writer\ConsoleWriter());
        return $logger;
    }

    /**
     * Log an emergency message.
     *
     * @param string|Stringable $message
     * @param array $context
     * @return void
     */
    public static function emergency(string|Stringable $message, array $context = []): void
    {
        self::getLogger()->emergency($message, $context);
    }

    /**
     * Log an alert message.
     *
     * @param string|Stringable $message
     * @param array $context
     * @return void
     */
    public static function alert(string|Stringable $message, array $context = []): void
    {
        self::getLogger()->alert($message, $context);
    }

    /**
     * Log a critical message.
     *
     * @param string|Stringable $message
     * @param array $context
     * @return void
     */
    public static function critical(string|Stringable $message, array $context = []): void
    {
        self::getLogger()->critical($message, $context);
    }

    /**
     * Log an error message.
     *
     * @param string|Stringable $message
     * @param array $context
     * @return void
     */
    public static function error(string|Stringable $message, array $context = []): void
    {
        self::getLogger()->error($message, $context);
    }

    /**
     * Log a warning message.
     *
     * @param string|Stringable $message
     * @param array $context
     * @return void
     */
    public static function warning(string|Stringable $message, array $context = []): void
    {
        self::getLogger()->warning($message, $context);
    }

    /**
     * Log a notice message.
     *
     * @param string|Stringable $message
     * @param array $context
     * @return void
     */
    public static function notice(string|Stringable $message, array $context = []): void
    {
        self::getLogger()->notice($message, $context);
    }

    /**
     * Log an info message.
     *
     * @param string|Stringable $message
     * @param array $context
     * @return void
     */
    public static function info(string|Stringable $message, array $context = []): void
    {
        self::getLogger()->info($message, $context);
    }

    /**
     * Log a debug message.
     *
     * @param string|Stringable $message
     * @param array $context
     * @return void
     */
    public static function debug(string|Stringable $message, array $context = []): void
    {
        self::getLogger()->debug($message, $context);
    }

    /**
     * Log a message at the specified level.
     *
     * @param mixed $level
     * @param string|Stringable $message
     * @param array $context
     * @return void
     */
    public static function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        self::getLogger()->log($level, $message, $context);
    }

    /**
     * Reset the facade (useful for testing).
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$logger = null;
        self::$manager = null;
    }
}
