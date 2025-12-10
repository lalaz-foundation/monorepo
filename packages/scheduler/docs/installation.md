# Installation Guide

This guide covers the installation and configuration of the Lalaz Scheduler package.

## Requirements

- PHP 8.1 or higher
- Composer
- PSR-16 compatible cache (optional, for distributed mode)

## Installation

### Via Composer

```bash
composer require lalaz/scheduler
```

### Manual Installation

1. Download the package from the repository
2. Add the package to your `composer.json`:

```json
{
    "require": {
        "lalaz/scheduler": "^1.0"
    }
}
```

3. Run `composer install`

## Basic Setup

### 1. Import the Classes

```php
use Lalaz\Scheduler\Schedule;
use Lalaz\Scheduler\ScheduledClosure;
use Lalaz\Scheduler\ScheduledCommand;
use Lalaz\Scheduler\ScheduledJob;
use Lalaz\Scheduler\CronExpression;
```

### 2. Create a Schedule Instance

```php
// Basic usage
$schedule = new Schedule();

// With timezone
$schedule = new Schedule(null, 'UTC');

// With custom mutex
use Lalaz\Scheduler\Mutex\NullMutex;
$schedule = new Schedule(new NullMutex());
```

### 3. Register Tasks

```php
// Schedule a closure
$schedule->call(function () {
    return 'Hello, World!';
})->everyMinute()
  ->description('Say hello');

// Schedule a command
$schedule->command('cache:clear')
    ->hourly()
    ->description('Clear cache');

// Schedule a job
$schedule->job(new MyJob())
    ->daily()
    ->description('Run my job');
```

### 4. Run the Scheduler

```php
// Get due events
$dueEvents = $schedule->dueEvents();

// Execute each event
foreach ($dueEvents as $event) {
    $event->run();
}
```

## Framework Integration

### Service Provider Registration

If using the Lalaz framework, register the service provider:

```php
// In your bootstrap or service registration
use Lalaz\Scheduler\SchedulerServiceProvider;

$provider = new SchedulerServiceProvider($container);
$provider->register();
```

### Schedule Definition

Create a schedule definition file (e.g., `app/Console/Schedule.php`):

```php
<?php

namespace App\Console;

use Lalaz\Scheduler\Schedule;

class ScheduleDefinition
{
    public function define(Schedule $schedule): void
    {
        $schedule->call(function () {
            // Task logic
        })->everyMinute()
          ->description('My task');
        
        $schedule->command('app:maintenance')
            ->dailyAt('03:00')
            ->description('Run maintenance');
    }
}
```

## Distributed Mode Setup

For multi-server deployments, configure distributed mode:

### 1. Set Up a Cache Backend

```php
use Lalaz\Cache\Drivers\RedisCache;

$cache = new RedisCache([
    'host' => 'localhost',
    'port' => 6379,
]);
```

### 2. Create a Cache Mutex

```php
use Lalaz\Scheduler\Mutex\CacheMutex;

$mutex = new CacheMutex($cache);
```

### 3. Configure the Schedule

```php
// Enable distributed mode (third parameter = true)
$schedule = new Schedule($mutex, null, true);

// Mark tasks to run on one server
$schedule->call($task)
    ->everyMinute()
    ->onOneServer();
```

## Cron Job Setup

Set up a cron job to run the scheduler every minute:

```bash
* * * * * cd /path/to/project && php lalaz schedule:run >> /dev/null 2>&1
```

### Alternative: Using the Console Command

```bash
# Run the scheduler
php lalaz schedule:run

# List all scheduled tasks
php lalaz schedule:list

# Test a specific task
php lalaz schedule:test "Task Description"
```

## Environment Configuration

### Using Environment Variables

```php
// Set environment in .env
APP_ENV=production

// Use in schedule
$schedule->call($task)
    ->production()
    ->description('Production only task');
```

### Schedule Mode

```php
// Single server (default)
$schedule = new Schedule();

// Distributed
$schedule = new Schedule($mutex, null, true);
```

## Timezone Configuration

### Global Timezone

```php
$schedule = new Schedule(null, 'America/New_York');
```

### Per-Event Timezone

```php
$schedule->call($task)
    ->timezone('Europe/London')
    ->dailyAt('09:00'); // 9 AM London time
```

## Logging Configuration

### Output to File

```php
$schedule->call($task)
    ->hourly()
    ->sendOutputTo('/var/log/scheduler.log');
```

### Custom Logging

```php
$schedule->call($task)
    ->hourly()
    ->handleOutputUsing(function ($output, $exitCode) {
        $logger->info('Task output', [
            'output' => $output,
            'exitCode' => $exitCode,
        ]);
    });
```

## Verification

### Verify Installation

```php
// Check if scheduler is working
$schedule = new Schedule();

$schedule->call(function () {
    return 'Scheduler is working!';
})->everyMinute()
  ->description('Health check');

$dueEvents = $schedule->dueEvents();
echo count($dueEvents) . " events are due\n";

foreach ($dueEvents as $event) {
    $result = $event->run();
    echo "Result: $result\n";
}
```

### Run Tests

```bash
./vendor/bin/phpunit --testsuite=Unit
```

## Troubleshooting

### Common Issues

#### Tasks Not Running

1. Check if the cron expression is correct
2. Verify environment filters
3. Check if `isDue()` returns true

```php
$event = $schedule->call($task)->hourly();
echo "Is due: " . ($event->isDue() ? 'yes' : 'no') . "\n";
echo "Expression: " . $event->getExpression() . "\n";
```

#### Overlap Prevention Not Working

Make sure you're using a proper mutex in distributed mode:

```php
// Wrong - NullMutex doesn't prevent overlapping in distributed setup
$schedule = new Schedule();

// Correct
$mutex = new CacheMutex($cache);
$schedule = new Schedule($mutex, null, true);
```

#### Timezone Issues

Verify the timezone is set correctly:

```php
$schedule = new Schedule(null, 'UTC');

$event = $schedule->call($task)->dailyAt('12:00');
echo "Next run: " . $event->nextRunDate()->format('Y-m-d H:i:s') . "\n";
```

## Next Steps

- Learn the [Core Concepts](concepts.md)
- Explore the [API Reference](api-reference.md)
- Set up [Testing](testing.md)
