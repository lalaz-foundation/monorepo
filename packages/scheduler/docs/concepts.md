# Core Concepts

This guide explains the fundamental concepts and architecture of the Lalaz Scheduler package.

## Schedule

The `Schedule` class is the main entry point for defining scheduled tasks. It acts as a container for all scheduled events and provides methods to register different types of tasks.

```php
use Lalaz\Scheduler\Schedule;

$schedule = new Schedule();

// Register tasks using the fluent API
$schedule->call($closure)->everyMinute();
$schedule->command($command)->hourly();
$schedule->job($job)->daily();
```

### Schedule Modes

The scheduler operates in two modes:

#### Single Server Mode (Default)

In single server mode, all tasks run on every server that executes the scheduler. This is the default and simplest configuration.

```php
$schedule = new Schedule(); // Uses NullMutex
```

#### Distributed Mode

In distributed mode, the scheduler uses a mutex to coordinate task execution across multiple servers. Tasks marked with `onOneServer()` will only run on a single server.

```php
use Lalaz\Scheduler\Mutex\CacheMutex;

$mutex = new CacheMutex($cache);
$schedule = new Schedule($mutex, null, true); // Distributed mode
```

## Scheduled Events

Scheduled events represent individual tasks that should be executed at specific times. All events extend the abstract `ScheduledEvent` class.

### Event Types

#### ScheduledClosure

Executes a PHP callable (closure, function, or method):

```php
// Closure
$schedule->call(function () {
    return 'Hello, World!';
})->everyMinute();

// Method reference
$schedule->call([$object, 'method'])->hourly();

// Static method
$schedule->call([ClassName::class, 'staticMethod'])->daily();
```

#### ScheduledCommand

Executes a console command:

```php
// Simple command
$schedule->command('cache:clear')->hourly();

// With arguments
$schedule->command('email:send --queue=high --limit=100')->everyMinute();

// With array arguments
$schedule->command('process:data', ['--batch=50', '--verbose'])->daily();
```

#### ScheduledJob

Dispatches a queue job:

```php
// Default queue
$schedule->job(new ProcessDataJob())->hourly();

// Specific queue
$schedule->job(new SendEmailJob(), 'emails')->everyMinute();
```

## Cron Expressions

The scheduler uses 5-field cron expressions to determine when tasks should run:

```
┌───────────── minute (0-59)
│ ┌───────────── hour (0-23)
│ │ ┌───────────── day of month (1-31)
│ │ │ ┌───────────── month (1-12)
│ │ │ │ ┌───────────── day of week (0-6, Sunday=0)
│ │ │ │ │
* * * * *
```

### Expression Features

- **Wildcards (`*`)**: Matches any value
- **Specific values**: `30 14 * * *` (2:30 PM)
- **Ranges**: `0 9-17 * * *` (9 AM to 5 PM)
- **Lists**: `0 8,12,18 * * *` (8 AM, noon, 6 PM)
- **Steps**: `*/5 * * * *` (every 5 minutes)
- **Combined**: `*/15 9-17 * * 1-5` (every 15 min during business hours)

### CronExpression Utility

The `CronExpression` class provides static methods for working with cron expressions:

```php
use Lalaz\Scheduler\CronExpression;

// Validation
CronExpression::isValid('*/5 * * * *');  // true
CronExpression::isValid('invalid');       // false

// Check if due now
CronExpression::isDue('* * * * *');       // true

// Check if due at specific time
$date = new DateTimeImmutable('2024-01-15 12:00:00');
CronExpression::isDue('0 12 * * *', $date); // true

// Get next run date
$next = CronExpression::nextRunDate('0 0 * * *'); // Next midnight

// Human-readable description
CronExpression::describe('* * * * *');      // "Every minute"
CronExpression::describe('0 0 * * *');      // "Daily at midnight"
```

## Traits

The scheduler uses traits to compose event behavior:

### ManagesFrequencies

Provides methods for setting task frequency:

```php
$event->everyMinute();
$event->hourly();
$event->daily();
$event->weekly();
$event->monthly();
$event->quarterly();
$event->yearly();
$event->cron('0 0 * * *');
```

### ManagesFilters

Provides methods for conditional execution:

```php
$event->when(fn() => condition());
$event->skip(fn() => shouldSkip());
$event->environments(['production']);
$event->production();
$event->exceptProduction();
```

### ManagesOutput

Provides methods for output handling:

```php
$event->before(fn() => beforeTask());
$event->after(fn($result) => afterTask($result));
$event->onSuccess(fn($result) => onSuccess($result));
$event->onFailure(fn($e) => onFailure($e));
$event->sendOutputTo('/path/to/file');
$event->appendOutputTo('/path/to/file');
$event->handleOutputUsing(fn($output, $code) => handle($output));
```

## Mutex System

The mutex system prevents concurrent execution of the same task.

### MutexInterface

The contract that all mutex implementations must follow:

```php
interface MutexInterface
{
    public function acquire(string $name, int $expiresAt): bool;
    public function release(string $name): bool;
    public function exists(string $name): bool;
}
```

### NullMutex

A no-op implementation for single-server deployments:

```php
$mutex = new NullMutex();
// acquire() always returns true
// release() always returns true
// exists() always returns false
```

### CacheMutex

A cache-based implementation for distributed deployments:

```php
$mutex = new CacheMutex($cacheInstance);
// Uses cache to store lock state
// Supports TTL-based lock expiration
```

## Event Lifecycle

Each scheduled event goes through a defined lifecycle:

```
1. Check if due (cron expression matches current time)
   ↓
2. Check if filters pass (when, skip, environments)
   ↓
3. Check for overlapping (if withoutOverlapping is set)
   ↓
4. Execute before callbacks
   ↓
5. Run the task
   ↓
6. Execute after callbacks
   ↓
7. Execute onSuccess or onFailure callback
```

### Lifecycle Example

```php
$schedule->call(function () {
    return processData();
})
    ->everyMinute()
    ->when(fn() => isReady())              // 2. Filter check
    ->withoutOverlapping()                  // 3. Overlap check
    ->before(fn() => log('Starting'))       // 4. Before callback
    ->after(fn() => log('Finished'))        // 6. After callback
    ->onSuccess(fn() => notify('Done'))     // 7a. Success callback
    ->onFailure(fn($e) => alert($e));       // 7b. Failure callback
```

## Constraints

Events can have multiple constraints that affect their execution:

### withoutOverlapping()

Prevents concurrent execution of the same task:

```php
$schedule->call($task)
    ->everyMinute()
    ->withoutOverlapping();     // Default 24 hour expiration

$schedule->call($task)
    ->everyMinute()
    ->withoutOverlapping(60);   // 60 minute expiration
```

### onOneServer()

In distributed mode, ensures task runs on only one server:

```php
$schedule->call($task)
    ->everyMinute()
    ->onOneServer();
```

### runInBackground()

Runs the task in a background process:

```php
$schedule->command('long:task')
    ->everyMinute()
    ->runInBackground();
```

## Timezone Support

Schedules can be configured with specific timezones:

### Schedule-Level Timezone

```php
$schedule = new Schedule(null, 'America/New_York');
// All events use New York timezone by default
```

### Event-Level Timezone

```php
$schedule->call($task)
    ->everyMinute()
    ->timezone('Europe/London');
// This event uses London timezone
```

## Best Practices

### 1. Always Set Descriptions

```php
$schedule->call($task)
    ->hourly()
    ->description('Process order queue'); // Makes logs readable
```

### 2. Use Overlap Prevention for Long Tasks

```php
$schedule->command('backup:database')
    ->hourly()
    ->withoutOverlapping(120); // 2 hour timeout for backups
```

### 3. Set Appropriate Lifecycle Hooks

```php
$schedule->call($task)
    ->daily()
    ->onFailure(function ($e) {
        alert('Task failed: ' . $e->getMessage());
    });
```

### 4. Use Environment Filters

```php
$schedule->call($task)
    ->daily()
    ->production(); // Don't run in development
```

### 5. Choose Appropriate Frequencies

```php
// Bad: Running expensive task too often
$schedule->command('backup:full')->everyMinute();

// Good: Running expensive task at appropriate interval
$schedule->command('backup:full')->dailyAt('03:00');
```

## Next Steps

- See the complete [API Reference](api-reference.md)
- Learn about [Testing](testing.md) scheduled tasks
- Check the [Glossary](glossary.md) for definitions
