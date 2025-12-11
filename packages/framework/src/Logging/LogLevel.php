<?php

declare(strict_types=1);

namespace Lalaz\Logging;

use Psr\Log\LogLevel as PsrLogLevel;

/**
 * Log level constants and utilities.
 *
 * Extends PSR-3 LogLevel with priority comparison for filtering.
 *
 *  * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
final class LogLevel extends PsrLogLevel
{
    /**
     * Priority values for each level (higher = more severe).
     */
    private const array PRIORITIES = [
        self::DEBUG => 100,
        self::INFO => 200,
        self::NOTICE => 250,
        self::WARNING => 300,
        self::ERROR => 400,
        self::CRITICAL => 500,
        self::ALERT => 550,
        self::EMERGENCY => 600,
    ];

    /**
     * Get the priority value for a log level.
     *
     * @param string $level The log level
     * @return int Priority value (higher = more severe)
     */
    public static function getPriority(string $level): int
    {
        $normalized = strtolower($level);
        return self::PRIORITIES[$normalized] ?? self::PRIORITIES[self::DEBUG];
    }

    /**
     * Determine if a message should be logged based on minimum level.
     *
     * @param string $level The message level
     * @param string $minimum The minimum level to log
     * @return bool
     */
    public static function shouldLog(string $level, string $minimum): bool
    {
        return self::getPriority($level) >= self::getPriority($minimum);
    }

    /**
     * Get all available log levels in order of severity (lowest to highest).
     *
     * @return array<string>
     */
    public static function all(): array
    {
        return [
            self::DEBUG,
            self::INFO,
            self::NOTICE,
            self::WARNING,
            self::ERROR,
            self::CRITICAL,
            self::ALERT,
            self::EMERGENCY,
        ];
    }

    /**
     * Check if a level string is valid.
     *
     * @param string $level The level to check
     * @return bool
     */
    public static function isValid(string $level): bool
    {
        return isset(self::PRIORITIES[strtolower($level)]);
    }

    /**
     * Normalize a level string to lowercase (PSR-3 standard).
     *
     * @param string $level The level to normalize
     * @return string
     */
    public static function normalize(string $level): string
    {
        return strtolower($level);
    }
}
