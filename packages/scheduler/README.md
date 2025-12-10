# Lalaz Scheduler Package

A powerful and flexible task scheduling package for PHP applications. Define scheduled tasks using an elegant, fluent API with support for cron expressions, time-based constraints, environment filters, and distributed mode execution.

## Features

- **Fluent API** - Intuitive method chaining for defining schedules
- **Multiple Task Types** - Schedule closures, console commands, or queue jobs
- **Flexible Frequencies** - From every minute to yearly schedules
- **Cron Expressions** - Full 5-field cron expression support
- **Environment Filters** - Run tasks only in specific environments
- **Overlap Prevention** - Prevent concurrent task execution
- **Distributed Mode** - Coordinate task execution across multiple servers
- **Lifecycle Hooks** - Before, after, success, and failure callbacks
- **Output Handling** - Direct output to files or custom handlers
- **Timezone Support** - Configure schedules in any timezone

## Installation

```bash
composer require lalaz/scheduler
```

## Quick Start

### Basic Usage

```php
use Lalaz\Scheduler\Schedule;

$schedule = new Schedule();

// Schedule a closure to run every 5 minutes
$schedule->call(function () {
    return 'Task completed';
})->everyFiveMinutes()
  ->description('Periodic cleanup');

// Schedule a console command daily at midnight
$schedule->command('cache:clear')
    ->daily()
    ->description('Clear application cache');

// Schedule a queue job hourly
$schedule->job(new ProcessReportsJob())
    ->hourly()
    ->description('Process pending reports');

// Run all due events
foreach ($schedule->dueEvents() as $event) {
    $event->run();
}
```

### Frequency Methods

```php
$schedule->call($task)->everyMinute();           // * * * * *
$schedule->call($task)->everyFiveMinutes();      // */5 * * * *
$schedule->call($task)->everyTenMinutes();       // */10 * * * *
$schedule->call($task)->everyFifteenMinutes();   // */15 * * * *
$schedule->call($task)->everyThirtyMinutes();    // */30 * * * *
$schedule->call($task)->hourly();                // 0 * * * *
$schedule->call($task)->hourlyAt(15);            // 15 * * * *
$schedule->call($task)->daily();                 // 0 0 * * *
$schedule->call($task)->dailyAt('13:00');        // 0 13 * * *
$schedule->call($task)->twiceDaily(1, 13);       // 0 1,13 * * *
$schedule->call($task)->weekly();                // 0 0 * * 0
$schedule->call($task)->weeklyOn(1, '08:00');    // 0 8 * * 1
$schedule->call($task)->monthly();               // 0 0 1 * *
$schedule->call($task)->monthlyOn(15, '09:00');  // 0 9 15 * *
$schedule->call($task)->quarterly();             // 0 0 1 1,4,7,10 *
$schedule->call($task)->yearly();                // 0 0 1 1 *
```

### Day Constraints

```php
$schedule->call($task)->daily()->weekdays();     // Monday-Friday
$schedule->call($task)->daily()->weekends();     // Saturday-Sunday
$schedule->call($task)->daily()->mondays();      // Only Mondays
$schedule->call($task)->daily()->fridays();      // Only Fridays
$schedule->call($task)->daily()->days([1, 3, 5]); // Mon, Wed, Fri
```

### Custom Cron Expression

```php
$schedule->call($task)->cron('15 10 * * 1-5');   // 10:15am on weekdays
```

### Environment Filters

```php
// Run only in production
$schedule->call($task)
    ->everyMinute()
    ->production();

// Run everywhere except production
$schedule->call($task)
    ->everyMinute()
    ->exceptProduction();

// Run only in specific environments
$schedule->call($task)
    ->everyMinute()
    ->environments(['staging', 'production']);
```

### Conditional Execution

```php
// Run only when condition is true
$schedule->call($task)
    ->everyMinute()
    ->when(function () {
        return someCondition();
    });

// Skip when condition is true
$schedule->call($task)
    ->everyMinute()
    ->skip(function () {
        return shouldSkip();
    });
```

### Overlap Prevention

```php
// Prevent overlapping executions
$schedule->call($task)
    ->everyMinute()
    ->withoutOverlapping();

// With custom expiration time (in minutes)
$schedule->call($task)
    ->everyMinute()
    ->withoutOverlapping(1440); // 24 hours
```

### Distributed Mode

```php
use Lalaz\Scheduler\Mutex\CacheMutex;

// Use cache-based mutex for distributed mode
$mutex = new CacheMutex($cacheInstance);
$schedule = new Schedule($mutex, null, true);

// Run on one server only
$schedule->call($task)
    ->everyMinute()
    ->onOneServer();
```

### Lifecycle Hooks

```php
$schedule->call($task)
    ->everyMinute()
    ->before(function () {
        // Runs before the task
    })
    ->after(function ($result) {
        // Runs after the task (success or failure)
    })
    ->onSuccess(function ($result) {
        // Runs only on success
    })
    ->onFailure(function ($exception) {
        // Runs only on failure
    });
```

### Output Handling

```php
// Write output to file
$schedule->call($task)
    ->everyMinute()
    ->sendOutputTo('/path/to/output.log');

// Append output to file
$schedule->call($task)
    ->everyMinute()
    ->appendOutputTo('/path/to/output.log');

// Custom output handler
$schedule->call($task)
    ->everyMinute()
    ->handleOutputUsing(function ($output, $exitCode) {
        // Handle the output
    });
```

### Background Execution

```php
$schedule->command('long:running:task')
    ->everyMinute()
    ->runInBackground();
```

## Cron Expression Utility

The package includes a standalone `CronExpression` utility class:

```php
use Lalaz\Scheduler\CronExpression;

// Check if expression is valid
CronExpression::isValid('*/5 * * * *'); // true
CronExpression::isValid('invalid'); // false

// Check if expression is due now
CronExpression::isDue('* * * * *'); // true (every minute)
CronExpression::isDue('0 12 * * *'); // true only at noon

// Check if due at specific time
$date = new DateTimeImmutable('2024-01-15 12:00:00');
CronExpression::isDue('0 12 * * *', $date); // true

// Get next run date
$next = CronExpression::nextRunDate('0 0 * * *');

// Get human-readable description
CronExpression::describe('* * * * *');      // "Every minute"
CronExpression::describe('0 0 * * *');      // "Daily at midnight"
CronExpression::describe('*/5 * * * *');    // "Every 5 minutes"
```

## Mutex Implementations

### NullMutex (Default)

No-op mutex for single server deployments:

```php
use Lalaz\Scheduler\Mutex\NullMutex;

$mutex = new NullMutex();
$schedule = new Schedule($mutex);
```

### CacheMutex

Cache-based mutex for distributed deployments:

```php
use Lalaz\Scheduler\Mutex\CacheMutex;

$mutex = new CacheMutex($cacheInstance);
$schedule = new Schedule($mutex, null, true); // Distributed mode
```

## Console Commands

The package provides console commands for managing the scheduler:

### Running the Scheduler

```bash
php lalaz schedule:run
```

### Listing Scheduled Tasks

```bash
php lalaz schedule:list
```

### Testing a Task

```bash
php lalaz schedule:test "Task Description"
```

## Event Types

### ScheduledClosure

Execute any callable:

```php
$schedule->call(function () {
    // Your task logic
    return 'Result';
})->everyMinute();

// With injected dependencies (if using container)
$schedule->call([ServiceClass::class, 'method'])
    ->hourly();
```

### ScheduledCommand

Execute console commands:

```php
// Simple command
$schedule->command('cache:clear')
    ->daily();

// With arguments
$schedule->command('email:send --queue=high --tries=3')
    ->everyMinute();

// With array arguments
$schedule->command('process:data', [
    '--batch-size=100',
    '--verbose',
])->hourly();
```

### ScheduledJob

Dispatch queue jobs:

```php
// Default queue
$schedule->job(new ProcessReportsJob())
    ->daily();

// Specific queue
$schedule->job(new SendNotificationsJob(), 'notifications')
    ->everyFiveMinutes();
```

## Configuration

### Service Provider

Register the scheduler in your service provider:

```php
use Lalaz\Scheduler\SchedulerServiceProvider;

$provider = new SchedulerServiceProvider($container);
$provider->register();
```

### Timezone Configuration

```php
// Set timezone on schedule
$schedule = new Schedule(null, 'America/New_York');

// Or set per-event
$schedule->call($task)
    ->everyMinute()
    ->timezone('Europe/London');
```

## Testing

Run the test suite:

```bash
# Run all tests
./vendor/bin/phpunit

# Run unit tests only
./vendor/bin/phpunit --testsuite=Unit

# Run integration tests only
./vendor/bin/phpunit --testsuite=Integration

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage
```

## Documentation

- [Quick Start Guide](docs/quick-start.md)
- [Core Concepts](docs/concepts.md)
- [API Reference](docs/api-reference.md)
- [Testing Guide](docs/testing.md)
- [Glossary](docs/glossary.md)

## Requirements

- PHP 8.1 or higher
- PSR-16 compatible cache (for distributed mode)

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).
