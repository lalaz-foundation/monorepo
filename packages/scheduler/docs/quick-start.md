# Quick Start Guide

Get up and running with the Lalaz Scheduler package in minutes.

## Installation

Install the package via Composer:

```bash
composer require lalaz/scheduler
```

## Basic Usage

### 1. Create a Schedule

```php
use Lalaz\Scheduler\Schedule;

$schedule = new Schedule();
```

### 2. Register Tasks

```php
// Schedule a closure
$schedule->call(function () {
    // Your task logic here
    return 'Task completed';
})->everyMinute()
  ->description('My first scheduled task');

// Schedule a console command
$schedule->command('cache:clear')
    ->hourly()
    ->description('Clear application cache');

// Schedule a queue job
$schedule->job(new ProcessOrdersJob())
    ->everyFiveMinutes()
    ->description('Process pending orders');
```

### 3. Run Due Tasks

```php
// Get all tasks that are due to run
$dueEvents = $schedule->dueEvents();

// Execute each due task
foreach ($dueEvents as $event) {
    echo "Running: " . $event->getDescription() . "\n";
    $event->run();
}
```

## Frequency Methods

### Common Frequencies

```php
$schedule->call($task)->everyMinute();         // Every minute
$schedule->call($task)->everyFiveMinutes();    // Every 5 minutes
$schedule->call($task)->everyTenMinutes();     // Every 10 minutes
$schedule->call($task)->everyFifteenMinutes(); // Every 15 minutes
$schedule->call($task)->everyThirtyMinutes();  // Every 30 minutes
$schedule->call($task)->hourly();              // At minute 0 of every hour
$schedule->call($task)->hourlyAt(30);          // At minute 30 of every hour
```

### Daily Schedules

```php
$schedule->call($task)->daily();              // At midnight
$schedule->call($task)->dailyAt('13:00');     // At 1:00 PM
$schedule->call($task)->twiceDaily(1, 13);    // At 1:00 AM and 1:00 PM
```

### Weekly Schedules

```php
$schedule->call($task)->weekly();              // Sunday at midnight
$schedule->call($task)->weeklyOn(1, '08:00');  // Monday at 8:00 AM
```

### Monthly & Yearly

```php
$schedule->call($task)->monthly();              // 1st of month at midnight
$schedule->call($task)->monthlyOn(15, '09:00'); // 15th at 9:00 AM
$schedule->call($task)->quarterly();            // Quarterly
$schedule->call($task)->yearly();               // January 1st at midnight
```

## Day Constraints

```php
$schedule->call($task)->daily()->weekdays();   // Monday through Friday
$schedule->call($task)->daily()->weekends();   // Saturday and Sunday
$schedule->call($task)->daily()->mondays();    // Only on Mondays
$schedule->call($task)->daily()->fridays();    // Only on Fridays
```

## Environment Filters

Control which environments your tasks run in:

```php
// Run only in production
$schedule->call($task)
    ->hourly()
    ->production();

// Run everywhere except production
$schedule->call($task)
    ->hourly()
    ->exceptProduction();

// Run in specific environments
$schedule->call($task)
    ->hourly()
    ->environments(['staging', 'production']);
```

## Conditional Execution

Add custom conditions for task execution:

```php
// Only run when condition is true
$schedule->call($task)
    ->hourly()
    ->when(function () {
        return date('H') < 12; // Morning only
    });

// Skip when condition is true
$schedule->call($task)
    ->hourly()
    ->skip(function () {
        return isMaintenanceMode();
    });
```

## Preventing Overlapping

Prevent a task from running if a previous instance is still executing:

```php
$schedule->call($task)
    ->everyMinute()
    ->withoutOverlapping();

// With custom lock expiration (in minutes)
$schedule->call($task)
    ->everyMinute()
    ->withoutOverlapping(60); // Lock expires after 60 minutes
```

## Lifecycle Hooks

Execute code before, after, on success, or on failure:

```php
$schedule->call($task)
    ->hourly()
    ->before(function () {
        // Runs before the task
        log('Task starting...');
    })
    ->after(function ($result) {
        // Runs after the task (success or failure)
        log('Task finished');
    })
    ->onSuccess(function ($result) {
        // Runs only on successful completion
        notify('Task completed successfully');
    })
    ->onFailure(function ($exception) {
        // Runs only when task throws an exception
        alert('Task failed: ' . $exception->getMessage());
    });
```

## Output Handling

Direct task output to files:

```php
// Write output to file (overwrites)
$schedule->call($task)
    ->hourly()
    ->sendOutputTo('/var/log/task.log');

// Append output to file
$schedule->call($task)
    ->hourly()
    ->appendOutputTo('/var/log/task.log');

// Custom output handler
$schedule->call($task)
    ->hourly()
    ->handleOutputUsing(function ($output, $exitCode) {
        // Process the output as needed
    });
```

## Complete Example

Here's a complete example of a typical scheduler setup:

```php
<?php

use Lalaz\Scheduler\Schedule;
use App\Jobs\ProcessOrdersJob;
use App\Jobs\SendDailyReportJob;

// Create the schedule
$schedule = new Schedule();

// Every minute: Process orders queue
$schedule->call(function () {
    processOrderQueue();
    return 'Processed orders';
})
    ->everyMinute()
    ->withoutOverlapping()
    ->description('Process order queue');

// Every 5 minutes: Clear expired sessions
$schedule->call(function () {
    clearExpiredSessions();
})
    ->everyFiveMinutes()
    ->description('Clear expired sessions');

// Hourly: Cache warming
$schedule->command('cache:warm')
    ->hourly()
    ->production()
    ->description('Warm application cache');

// Daily at 3am: Database backup
$schedule->command('backup:database')
    ->dailyAt('03:00')
    ->production()
    ->withoutOverlapping(120)
    ->before(fn() => log('Starting backup'))
    ->onSuccess(fn() => notify('Backup complete'))
    ->onFailure(fn($e) => alert('Backup failed'))
    ->description('Database backup');

// Daily at 6pm (weekdays): Send daily report
$schedule->job(new SendDailyReportJob())
    ->dailyAt('18:00')
    ->weekdays()
    ->description('Send daily report');

// Weekly on Monday: Generate analytics
$schedule->call(function () {
    return generateWeeklyAnalytics();
})
    ->weeklyOn(1, '09:00')
    ->description('Generate weekly analytics');

// Run the scheduler
foreach ($schedule->dueEvents() as $event) {
    try {
        echo "Running: " . $event->getDescription() . "\n";
        $result = $event->run();
        echo "Result: " . json_encode($result) . "\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
```

## Next Steps

- Learn about [Core Concepts](concepts.md)
- Explore the complete [API Reference](api-reference.md)
- Set up [Testing](testing.md) for your scheduled tasks
