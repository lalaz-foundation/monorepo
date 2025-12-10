<?php

declare(strict_types=1);

namespace Lalaz\Queue;

class RetryStrategy
{
    private const MAX_DELAY = 3600;


    public static function calculateDelay(
        string $strategy,
        int $baseDelay,
        int $attempts,
        bool $withJitter = true
    ): int {
        $delay = match($strategy) {
            'exponential' => self::exponentialBackoff($baseDelay, $attempts),
            'linear' => self::linearBackoff($baseDelay, $attempts),
            'fixed' => $baseDelay,
            default => self::exponentialBackoff($baseDelay, $attempts),
        };

        if ($withJitter) {
            $delay = self::addJitter($delay);
        }

        return $delay;
    }


    private static function exponentialBackoff(int $baseDelay, int $attempts): int
    {
        $delay = $baseDelay * pow(2, $attempts - 1);
        return min((int)$delay, self::MAX_DELAY);
    }


    private static function linearBackoff(int $baseDelay, int $attempts): int
    {
        $delay = $baseDelay * $attempts;
        return min($delay, self::MAX_DELAY);
    }


    public static function addJitter(int $delay, float $jitterPercent = 0.1): int
    {
        $jitter = (int)($delay * $jitterPercent);
        return $delay + random_int(-$jitter, $jitter);
    }


    public static function getDelayForAttempt(
        string $strategy,
        int $baseDelay,
        int $attempt
    ): int {
        return self::calculateDelay($strategy, $baseDelay, $attempt, false);
    }


    public static function getRetrySchedule(
        string $strategy,
        int $baseDelay,
        int $maxAttempts
    ): array {
        $schedule = [];

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $schedule[$attempt] = self::getDelayForAttempt($strategy, $baseDelay, $attempt);
        }

        return $schedule;
    }


    public static function formatDelay(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds}s";
        }

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        $parts = [];
        if ($hours > 0) {
            $parts[] = "{$hours}h";
        }
        if ($minutes > 0) {
            $parts[] = "{$minutes}m";
        }
        if ($secs > 0 && $hours == 0) {
            $parts[] = "{$secs}s";
        }

        return implode(' ', $parts);
    }
}
