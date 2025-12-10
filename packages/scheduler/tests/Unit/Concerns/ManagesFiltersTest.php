<?php declare(strict_types=1);

namespace Lalaz\Scheduler\Tests\Unit\Concerns;

use Lalaz\Scheduler\Tests\Common\SchedulerUnitTestCase;
use Lalaz\Scheduler\ScheduledClosure;
use Lalaz\Scheduler\Mutex\NullMutex;

/**
 * Tests for ManagesFilters trait.
 *
 * @package lalaz/scheduler
 */
class ManagesFiltersTest extends SchedulerUnitTestCase
{
    private function createEvent(): ScheduledClosure
    {
        return $this->createClosureEvent();
    }

    public function test_when_with_true_allows_execution(): void
    {
        $event = $this->createEvent()->everyMinute()->when(true);
        $this->assertTrue($event->isDue());
    }

    public function test_when_with_false_prevents_execution(): void
    {
        $event = $this->createEvent()->everyMinute()->when(false);
        $this->assertFalse($event->isDue());
    }

    public function test_when_with_callback(): void
    {
        $event = $this->createEvent()->everyMinute()->when(fn() => true);
        $this->assertTrue($event->isDue());

        $event = $this->createEvent()->everyMinute()->when(fn() => false);
        $this->assertFalse($event->isDue());
    }

    public function test_skip_with_true_prevents_execution(): void
    {
        $event = $this->createEvent()->everyMinute()->skip(true);
        $this->assertFalse($event->isDue());
    }

    public function test_skip_with_false_allows_execution(): void
    {
        $event = $this->createEvent()->everyMinute()->skip(false);
        $this->assertTrue($event->isDue());
    }

    public function test_skip_with_callback(): void
    {
        $event = $this->createEvent()->everyMinute()->skip(fn() => true);
        $this->assertFalse($event->isDue());

        $event = $this->createEvent()->everyMinute()->skip(fn() => false);
        $this->assertTrue($event->isDue());
    }

    public function test_environments_allows_matching(): void
    {
        $_ENV['APP_ENV'] = 'production';

        $event = $this->createEvent()->everyMinute()->environments(['production']);
        $this->assertTrue($event->isDue());
    }

    public function test_environments_blocks_non_matching(): void
    {
        $_ENV['APP_ENV'] = 'testing';

        $event = $this->createEvent()->everyMinute()->environments(['production']);
        $this->assertFalse($event->isDue());
    }

    public function test_environments_accepts_multiple(): void
    {
        $_ENV['APP_ENV'] = 'staging';

        $event = $this->createEvent()->everyMinute()->environments(['production', 'staging']);
        $this->assertTrue($event->isDue());
    }

    public function test_production_shortcut(): void
    {
        $_ENV['APP_ENV'] = 'production';

        $event = $this->createEvent()->everyMinute()->production();
        $this->assertTrue($event->isDue());

        $_ENV['APP_ENV'] = 'testing';

        $event = $this->createEvent()->everyMinute()->production();
        $this->assertFalse($event->isDue());
    }

    public function test_except_production(): void
    {
        $_ENV['APP_ENV'] = 'production';

        $event = $this->createEvent()->everyMinute()->exceptProduction();
        $this->assertFalse($event->isDue());

        $_ENV['APP_ENV'] = 'testing';

        $event = $this->createEvent()->everyMinute()->exceptProduction();
        $this->assertTrue($event->isDue());
    }

    public function test_multiple_filters_all_must_pass(): void
    {
        $event = $this->createEvent()
            ->everyMinute()
            ->when(true)
            ->when(true)
            ->when(false);

        $this->assertFalse($event->isDue());
    }

    public function test_any_skip_prevents_execution(): void
    {
        $event = $this->createEvent()
            ->everyMinute()
            ->skip(false)
            ->skip(false)
            ->skip(true);

        $this->assertFalse($event->isDue());
    }
}
