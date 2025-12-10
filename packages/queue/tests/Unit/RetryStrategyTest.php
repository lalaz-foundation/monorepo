<?php declare(strict_types=1);

namespace Lalaz\Queue\Tests\Unit;

use Lalaz\Queue\RetryStrategy;
use Lalaz\Queue\Tests\Common\QueueUnitTestCase;

class RetryStrategyTest extends QueueUnitTestCase
{
    public function test_calculates_exponential_backoff_correctly(): void
    {
        // Base delay 60s, attempt 1 = 60
        $delay1 = RetryStrategy::calculateDelay('exponential', 60, 1, false);
        $this->assertSame(60, $delay1);

        // Base delay 60s, attempt 2 = 120
        $delay2 = RetryStrategy::calculateDelay('exponential', 60, 2, false);
        $this->assertSame(120, $delay2);

        // Base delay 60s, attempt 3 = 240
        $delay3 = RetryStrategy::calculateDelay('exponential', 60, 3, false);
        $this->assertSame(240, $delay3);

        // Base delay 60s, attempt 4 = 480
        $delay4 = RetryStrategy::calculateDelay('exponential', 60, 4, false);
        $this->assertSame(480, $delay4);
    }

    public function test_calculates_linear_backoff_correctly(): void
    {
        // Base delay 60s, attempt 1 = 60
        $delay1 = RetryStrategy::calculateDelay('linear', 60, 1, false);
        $this->assertSame(60, $delay1);

        // Base delay 60s, attempt 2 = 120
        $delay2 = RetryStrategy::calculateDelay('linear', 60, 2, false);
        $this->assertSame(120, $delay2);

        // Base delay 60s, attempt 3 = 180
        $delay3 = RetryStrategy::calculateDelay('linear', 60, 3, false);
        $this->assertSame(180, $delay3);
    }

    public function test_uses_fixed_delay_when_strategy_is_fixed(): void
    {
        $delay1 = RetryStrategy::calculateDelay('fixed', 60, 1, false);
        $delay2 = RetryStrategy::calculateDelay('fixed', 60, 5, false);
        $delay3 = RetryStrategy::calculateDelay('fixed', 60, 10, false);

        $this->assertSame(60, $delay1);
        $this->assertSame(60, $delay2);
        $this->assertSame(60, $delay3);
    }

    public function test_caps_delay_at_max_delay(): void
    {
        // Very high attempt number should cap at 3600
        $delay = RetryStrategy::calculateDelay('exponential', 60, 10, false);
        $this->assertLessThanOrEqual(3600, $delay);
    }

    public function test_adds_jitter_when_enabled(): void
    {
        $delays = [];
        for ($i = 0; $i < 10; $i++) {
            $delays[] = RetryStrategy::calculateDelay('exponential', 100, 3, true);
        }

        // With jitter, not all delays should be identical
        $uniqueDelays = array_unique($delays);
        $this->assertGreaterThan(1, count($uniqueDelays));
    }

    public function test_defaults_to_exponential_for_unknown_strategy(): void
    {
        $delay = RetryStrategy::calculateDelay('unknown', 60, 2, false);
        $this->assertSame(120, $delay); // Same as exponential
    }

    public function test_add_jitter_adds_random_jitter_within_percentage_range(): void
    {
        $baseDelay = 1000;
        $jitterPercent = 0.1; // 10%

        $results = [];
        for ($i = 0; $i < 100; $i++) {
            $results[] = RetryStrategy::addJitter($baseDelay, $jitterPercent);
        }

        $min = min($results);
        $max = max($results);

        // Should be within 900-1100 (10% of 1000)
        $this->assertGreaterThanOrEqual(900, $min);
        $this->assertLessThanOrEqual(1100, $max);
    }

    public function test_get_delay_for_attempt_returns_delay_without_jitter(): void
    {
        $delay = RetryStrategy::getDelayForAttempt('exponential', 60, 3);
        $this->assertSame(240, $delay);
    }

    public function test_get_retry_schedule_returns_complete_schedule_for_all_attempts(): void
    {
        $schedule = RetryStrategy::getRetrySchedule('exponential', 60, 5);

        $this->assertIsArray($schedule);
        $this->assertCount(5, $schedule);
        $this->assertSame(60, $schedule[1]);
        $this->assertSame(120, $schedule[2]);
        $this->assertSame(240, $schedule[3]);
        $this->assertSame(480, $schedule[4]);
        $this->assertSame(960, $schedule[5]);
    }

    public function test_get_retry_schedule_returns_linear_schedule(): void
    {
        $schedule = RetryStrategy::getRetrySchedule('linear', 30, 4);

        $this->assertSame(30, $schedule[1]);
        $this->assertSame(60, $schedule[2]);
        $this->assertSame(90, $schedule[3]);
        $this->assertSame(120, $schedule[4]);
    }

    public function test_format_delay_formats_seconds_correctly(): void
    {
        $this->assertSame('45s', RetryStrategy::formatDelay(45));
    }

    public function test_format_delay_formats_minutes_correctly(): void
    {
        $this->assertSame('2m', RetryStrategy::formatDelay(120));
    }

    public function test_format_delay_formats_hours_and_minutes_correctly(): void
    {
        $this->assertSame('1h 1m', RetryStrategy::formatDelay(3660));
    }

    public function test_format_delay_formats_complex_durations(): void
    {
        $this->assertSame('1m 30s', RetryStrategy::formatDelay(90));
    }
}
