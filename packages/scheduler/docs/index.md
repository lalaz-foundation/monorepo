# Lalaz Scheduler Package Documentation

Welcome to the Lalaz Scheduler package documentation. This package provides a powerful and flexible task scheduling system for PHP applications.

## Table of Contents

1. [Quick Start Guide](quick-start.md) - Get up and running quickly
2. [Installation](installation.md) - Detailed installation instructions
3. [Core Concepts](concepts.md) - Understanding the scheduler architecture
4. [API Reference](api-reference.md) - Complete API documentation
5. [Testing Guide](testing.md) - Testing your scheduled tasks
6. [Glossary](glossary.md) - Terms and definitions

## Overview

The Scheduler package allows you to define scheduled tasks using an elegant, fluent API. It supports:

- **Closures** - Schedule any PHP callable
- **Commands** - Schedule console commands
- **Jobs** - Schedule queue jobs
- **Cron Expressions** - Full 5-field cron support
- **Environment Filters** - Run tasks in specific environments
- **Distributed Mode** - Coordinate across multiple servers
- **Lifecycle Hooks** - Before, after, success, failure callbacks

## Quick Example

```php
use Lalaz\Scheduler\Schedule;

$schedule = new Schedule();

// Schedule a cleanup task every 5 minutes
$schedule->call(function () {
    cleanupTemporaryFiles();
})->everyFiveMinutes()
  ->description('Cleanup temporary files');

// Schedule a backup daily at 3am
$schedule->command('backup:run')
    ->dailyAt('03:00')
    ->production()
    ->withoutOverlapping()
    ->description('Database backup');

// Schedule a report job weekly
$schedule->job(new GenerateWeeklyReport())
    ->weeklyOn(1, '09:00') // Monday at 9am
    ->description('Generate weekly report');

// Execute due tasks
foreach ($schedule->dueEvents() as $event) {
    $event->run();
}
```

## Key Features

### Fluent Frequency API

```php
$schedule->call($task)->everyMinute();
$schedule->call($task)->hourly();
$schedule->call($task)->daily();
$schedule->call($task)->weekly();
$schedule->call($task)->monthly();
$schedule->call($task)->quarterly();
$schedule->call($task)->yearly();
```

### Environment Awareness

```php
// Run only in production
$schedule->call($task)->production();

// Run everywhere except production
$schedule->call($task)->exceptProduction();

// Run in specific environments
$schedule->call($task)->environments(['staging', 'production']);
```

### Distributed Mode

```php
use Lalaz\Scheduler\Mutex\CacheMutex;

$mutex = new CacheMutex($cache);
$schedule = new Schedule($mutex, null, true);

// Ensure task runs on one server only
$schedule->call($task)->onOneServer();
```

### Lifecycle Hooks

```php
$schedule->call($task)
    ->before(fn() => log('Starting...'))
    ->after(fn() => log('Completed'))
    ->onSuccess(fn() => notify('Success'))
    ->onFailure(fn($e) => alert($e->getMessage()));
```

## Architecture

The package consists of these main components:

- **Schedule** - Main entry point for registering tasks
- **ScheduledEvent** - Abstract base class for all event types
- **ScheduledClosure** - Executes PHP callables
- **ScheduledCommand** - Executes console commands
- **ScheduledJob** - Dispatches queue jobs
- **CronExpression** - Parses and evaluates cron expressions
- **MutexInterface** - Contract for distributed locking

## Next Steps

- Read the [Quick Start Guide](quick-start.md) to get started
- Explore the [Core Concepts](concepts.md) for deeper understanding
- Check the [API Reference](api-reference.md) for complete documentation
