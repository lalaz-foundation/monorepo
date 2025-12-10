# Testing Guide

This guide covers testing practices and utilities for the Lalaz Scheduler package.

## Test Structure

The scheduler package includes comprehensive tests organized into:

```
tests/
├── bootstrap.php           # Test bootstrap
├── Common/                 # Shared test utilities
│   ├── SchedulerUnitTestCase.php
│   └── SchedulerIntegrationTestCase.php
├── Unit/                   # Unit tests
│   ├── ScheduleTest.php
│   ├── ScheduledEventTest.php
│   ├── CronExpressionTest.php
│   ├── Concerns/
│   │   ├── ManagesFrequenciesTest.php
│   │   ├── ManagesFiltersTest.php
│   │   └── ManagesOutputTest.php
│   └── Mutex/
│       └── MutexTest.php
└── Integration/            # Integration tests
    ├── ScheduleFlowIntegrationTest.php
    ├── CronExpressionIntegrationTest.php
    ├── ScheduledEventsIntegrationTest.php
    └── MutexIntegrationTest.php
```

## Running Tests

### All Tests

```bash
./vendor/bin/phpunit
```

### Unit Tests Only

```bash
./vendor/bin/phpunit --testsuite=Unit
```

### Integration Tests Only

```bash
./vendor/bin/phpunit --testsuite=Integration
```

### Specific Test File

```bash
./vendor/bin/phpunit tests/Unit/ScheduleTest.php
```

### With Coverage

```bash
./vendor/bin/phpunit --coverage-html coverage
```

## Base Test Classes

### SchedulerUnitTestCase

Base class for unit tests with factory methods:

```php
use Lalaz\Scheduler\Tests\Common\SchedulerUnitTestCase;

class MySchedulerTest extends SchedulerUnitTestCase
{
    public function test_my_schedule(): void
    {
        // Factory methods available
        $schedule = $this->createSchedule();
        $event = $this->createClosureEvent();
        $command = $this->createCommandEvent('my:command');
        $job = $this->createJobEvent(new MyJob());
        
        // Assertion helpers
        $this->assertEventIsDue($event);
        $this->assertEventNotDue($event);
        $this->assertCronExpression('* * * * *', $event);
    }
}
```

### SchedulerIntegrationTestCase

Extended base class for integration tests:

```php
use Lalaz\Scheduler\Tests\Common\SchedulerIntegrationTestCase;

class MyIntegrationTest extends SchedulerIntegrationTestCase
{
    public function test_workflow(): void
    {
        // Create complex schedules
        $result = $this->createMultiEventSchedule();
        $schedule = $result['schedule'];
        
        // Execute and verify
        $results = $this->executeDueEvents($schedule);
        
        // Assertions
        $this->assertAllEventsValid($schedule);
        $this->assertEventCount(4, $schedule);
    }
}
```

## Factory Methods

### Creating Schedules

```php
// Basic schedule
$schedule = $this->createSchedule();

// With timezone
$schedule = $this->createSchedule(timezone: 'UTC');

// Distributed mode
$schedule = $this->createSchedule(mode: 'distributed');

// With custom mutex
$mutex = $this->createNullMutex();
$schedule = $this->createSchedule(mutex: $mutex);
```

### Creating Events

```php
// Closure event
$event = $this->createClosureEvent();
$event = $this->createClosureEvent(fn() => 'custom');

// Command event
$event = $this->createCommandEvent('my:command');
$event = $this->createCommandEvent('my:command', ['--flag']);

// Job event
$event = $this->createJobEvent();
$event = $this->createJobEvent(new MyJob(), 'queue-name');
```

### Creating DateTimes

```php
// Current time
$date = $this->createDateTime();

// Specific time
$date = $this->createDateTime('2024-01-15 12:00:00');

// With timezone
$date = $this->createDateTime('2024-01-15 12:00:00', 'UTC');
```

## Assertion Methods

### Event Assertions

```php
// Check if event is due
$this->assertEventIsDue($event);
$this->assertEventNotDue($event);

// Check cron expression
$this->assertCronExpression('0 0 * * *', $event);

// Check description
$this->assertEventDescription('My task', $event);

// Check constraints
$this->assertPreventsOverlapping($event);
$this->assertRunsOnOneServer($event);
$this->assertRunsInBackground($event);
```

### Schedule Assertions

```php
// Check event count
$this->assertEventCount(3, $schedule);

// Check for due events
$this->assertHasDueEvents($schedule);

// Check all events valid
$this->assertAllEventsValid($schedule);
```

### Callback Assertions

```php
// Check callback order
$this->assertCallbackOrder(
    ['before', 'task', 'after', 'success'],
    $tracker['order']
);

// Check output file
$this->assertOutputFileContains('/path/to/file', 'expected content');
```

## Testing Patterns

### Testing Frequencies

```php
public function test_frequency_methods(): void
{
    $event = $this->createClosureEvent();
    
    $event->everyMinute();
    $this->assertCronExpression('* * * * *', $event);
    
    $event->hourly();
    $this->assertCronExpression('0 * * * *', $event);
    
    $event->daily();
    $this->assertCronExpression('0 0 * * *', $event);
}
```

### Testing Filters

```php
public function test_environment_filter(): void
{
    $this->setEnvironment('production');
    
    $event = $this->createClosureEvent()
        ->everyMinute()
        ->production();
    
    $this->assertEventIsDue($event);
    
    $this->setEnvironment('testing');
    $this->assertEventNotDue($event);
}

public function test_when_filter(): void
{
    $event = $this->createClosureEvent()
        ->everyMinute()
        ->when(false);
    
    $this->assertEventNotDue($event);
}
```

### Testing Lifecycle Hooks

```php
public function test_lifecycle_hooks(): void
{
    $tracked = $this->createTrackedEvent();
    $event = $tracked['event'];
    
    $event->everyMinute()->run();
    
    $this->assertTrue($tracked['tracker']['before']);
    $this->assertTrue($tracked['tracker']['task']);
    $this->assertTrue($tracked['tracker']['after']);
    $this->assertTrue($tracked['tracker']['success']);
    $this->assertFalse($tracked['tracker']['failure']);
}

public function test_failure_hook(): void
{
    $tracked = $this->createTrackedEvent(function () {
        throw new \RuntimeException('Error');
    });
    
    try {
        $tracked['event']->everyMinute()->run();
    } catch (\RuntimeException) {}
    
    $this->assertTrue($tracked['tracker']['failure']);
    $this->assertSame('Error', $tracked['tracker']['error']);
}
```

### Testing Cron Expressions

```php
public function test_cron_is_due(): void
{
    $monday = new DateTimeImmutable('2024-01-15 00:00:00');
    
    $this->assertTrue(
        CronExpression::isDue('0 0 * * 1', $monday)
    );
    
    $this->assertFalse(
        CronExpression::isDue('0 0 * * 0', $monday)
    );
}

public function test_cron_validation(): void
{
    $this->assertTrue(CronExpression::isValid('* * * * *'));
    $this->assertFalse(CronExpression::isValid('invalid'));
}
```

### Testing Mutex Behavior

```php
public function test_mutex_prevents_overlapping(): void
{
    $cache = $this->createMock(CacheInterface::class);
    $cache->method('has')->willReturn(true); // Lock exists
    
    $mutex = new CacheMutex($cache);
    
    $this->assertFalse($mutex->acquire('task', 3600));
}

public function test_null_mutex_always_acquires(): void
{
    $mutex = new NullMutex();
    
    $this->assertTrue($mutex->acquire('task', 3600));
    $this->assertTrue($mutex->acquire('task', 3600)); // Again
}
```

## Integration Testing

### Multi-Event Schedule

```php
public function test_multi_event_schedule(): void
{
    $result = $this->createMultiEventSchedule();
    $schedule = $result['schedule'];
    
    $this->assertEventCount(4, $schedule);
    $this->assertAllEventsValid($schedule);
    
    $dueEvents = $schedule->dueEvents();
    $this->assertNotEmpty($dueEvents);
}
```

### Execution Flow

```php
public function test_execution_flow(): void
{
    $schedule = $this->createSchedule();
    $executed = [];
    
    $schedule->call(function () use (&$executed) {
        $executed[] = 'task1';
    })->everyMinute();
    
    $schedule->call(function () use (&$executed) {
        $executed[] = 'task2';
    })->everyMinute();
    
    foreach ($schedule->dueEvents() as $event) {
        $event->run();
    }
    
    $this->assertCount(2, $executed);
}
```

### Output Handling

```php
public function test_output_to_file(): void
{
    $file = $this->outputDir . '/output.log';
    
    $event = $this->createClosureEvent(fn() => 'Hello')
        ->everyMinute()
        ->sendOutputTo($file);
    
    $event->run();
    
    $this->assertOutputFileContains($file, 'Hello');
}
```

## Testing Your Scheduled Tasks

### Example: Testing a Cleanup Task

```php
class CleanupTaskTest extends SchedulerUnitTestCase
{
    public function test_cleanup_runs_daily(): void
    {
        $schedule = $this->createSchedule();
        
        $schedule->call(fn() => $this->cleanup())
            ->dailyAt('03:00')
            ->description('Cleanup task');
        
        $event = $schedule->events()[0];
        
        $this->assertCronExpression('0 3 * * *', $event);
    }
    
    public function test_cleanup_prevents_overlapping(): void
    {
        $schedule = $this->createSchedule();
        
        $event = $schedule->call(fn() => $this->cleanup())
            ->hourly()
            ->withoutOverlapping();
        
        $this->assertPreventsOverlapping($event);
    }
    
    public function test_cleanup_only_in_production(): void
    {
        $this->setEnvironment('production');
        
        $event = $this->createClosureEvent()
            ->everyMinute()
            ->production();
        
        $this->assertEventIsDue($event);
        
        $this->setEnvironment('development');
        $this->assertEventNotDue($event);
    }
}
```

### Example: Testing a Report Job

```php
class ReportJobTest extends SchedulerIntegrationTestCase
{
    public function test_report_job_weekly_schedule(): void
    {
        $schedule = $this->createSchedule();
        
        $event = $schedule->job(new GenerateReportJob())
            ->weeklyOn(5, '17:00') // Friday 5pm
            ->description('Weekly report');
        
        $this->assertCronExpression('0 17 * * 5', $event);
    }
    
    public function test_report_job_lifecycle(): void
    {
        $beforeCalled = false;
        $afterCalled = false;
        
        $event = $this->createClosureEvent(fn() => 'report')
            ->everyMinute()
            ->before(function () use (&$beforeCalled) {
                $beforeCalled = true;
            })
            ->after(function () use (&$afterCalled) {
                $afterCalled = true;
            });
        
        $event->run();
        
        $this->assertTrue($beforeCalled);
        $this->assertTrue($afterCalled);
    }
}
```

## Best Practices

### 1. Use Base Test Classes

```php
// Good
class MyTest extends SchedulerUnitTestCase
{
    public function test_something(): void
    {
        $event = $this->createClosureEvent();
    }
}

// Avoid
class MyTest extends TestCase
{
    public function test_something(): void
    {
        $event = new ScheduledClosure(fn() => null, new NullMutex(), false);
    }
}
```

### 2. Test Cron Expressions at Specific Times

```php
public function test_monthly_schedule(): void
{
    $firstOfMonth = new DateTimeImmutable('2024-01-01 00:00:00');
    
    $this->assertTrue(
        CronExpression::isDue('0 0 1 * *', $firstOfMonth)
    );
    
    $secondOfMonth = new DateTimeImmutable('2024-01-02 00:00:00');
    
    $this->assertFalse(
        CronExpression::isDue('0 0 1 * *', $secondOfMonth)
    );
}
```

### 3. Clean Up in tearDown

```php
protected function tearDown(): void
{
    // Clean up any files created
    if (file_exists($this->tempFile)) {
        unlink($this->tempFile);
    }
    
    parent::tearDown();
}
```

### 4. Use Descriptive Test Names

```php
// Good
public function test_daily_backup_runs_at_midnight_in_production(): void

// Avoid
public function test_backup(): void
```

## Coverage

The scheduler package aims for high test coverage. Run coverage reports:

```bash
./vendor/bin/phpunit --coverage-html coverage
```

View the report at `coverage/index.html`.

## Continuous Integration

Example GitHub Actions workflow:

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          coverage: xdebug
      
      - name: Install dependencies
        run: composer install
      
      - name: Run tests
        run: ./vendor/bin/phpunit --coverage-clover coverage.xml
```
