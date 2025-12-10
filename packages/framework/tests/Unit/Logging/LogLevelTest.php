<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Logging;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use Lalaz\Logging\LogLevel;
use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;

#[CoversClass(LogLevel::class)]
class LogLevelTest extends FrameworkUnitTestCase
{
    #[Test]
    public function it_returns_correct_priority_for_all_levels(): void
    {
        $this->assertEquals(100, LogLevel::getPriority('debug'));
        $this->assertEquals(200, LogLevel::getPriority('info'));
        $this->assertEquals(250, LogLevel::getPriority('notice'));
        $this->assertEquals(300, LogLevel::getPriority('warning'));
        $this->assertEquals(400, LogLevel::getPriority('error'));
        $this->assertEquals(500, LogLevel::getPriority('critical'));
        $this->assertEquals(550, LogLevel::getPriority('alert'));
        $this->assertEquals(600, LogLevel::getPriority('emergency'));
    }

    #[Test]
    public function it_handles_uppercase_levels(): void
    {
        $this->assertEquals(400, LogLevel::getPriority('ERROR'));
        $this->assertEquals(200, LogLevel::getPriority('INFO'));
    }

    #[Test]
    public function it_returns_debug_priority_for_unknown_levels(): void
    {
        $this->assertEquals(100, LogLevel::getPriority('unknown'));
        $this->assertEquals(100, LogLevel::getPriority('custom'));
    }

    #[Test]
    public function should_log_returns_true_when_level_meets_minimum(): void
    {
        $this->assertTrue(LogLevel::shouldLog('error', 'error'));
        $this->assertTrue(LogLevel::shouldLog('error', 'warning'));
        $this->assertTrue(LogLevel::shouldLog('error', 'debug'));
        $this->assertTrue(LogLevel::shouldLog('emergency', 'debug'));
    }

    #[Test]
    public function should_log_returns_false_when_level_below_minimum(): void
    {
        $this->assertFalse(LogLevel::shouldLog('debug', 'info'));
        $this->assertFalse(LogLevel::shouldLog('info', 'warning'));
        $this->assertFalse(LogLevel::shouldLog('warning', 'error'));
    }

    #[Test]
    public function all_returns_levels_in_severity_order(): void
    {
        $levels = LogLevel::all();

        $this->assertCount(8, $levels);
        $this->assertEquals('debug', $levels[0]);
        $this->assertEquals('emergency', $levels[7]);
    }

    #[Test]
    public function is_valid_returns_true_for_valid_levels(): void
    {
        foreach (LogLevel::all() as $level) {
            $this->assertTrue(LogLevel::isValid($level));
        }
    }

    #[Test]
    public function is_valid_returns_false_for_invalid_levels(): void
    {
        $this->assertFalse(LogLevel::isValid('invalid'));
        $this->assertFalse(LogLevel::isValid('custom'));
        $this->assertFalse(LogLevel::isValid(''));
    }

    #[Test]
    public function normalize_converts_to_lowercase(): void
    {
        $this->assertEquals('error', LogLevel::normalize('ERROR'));
        $this->assertEquals('debug', LogLevel::normalize('DEBUG'));
        $this->assertEquals('info', LogLevel::normalize('Info'));
    }
}
