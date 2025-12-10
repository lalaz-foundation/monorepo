# API Reference

Complete API documentation for the Lalaz Scheduler package.

## Schedule

Main class for registering and managing scheduled tasks.

### Constructor

```php
public function __construct(
    ?MutexInterface $mutex = null,
    ?string $timezone = null,
    bool $distributedMode = false
)
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `$mutex` | `MutexInterface\|null` | Mutex for distributed locking (default: NullMutex) |
| `$timezone` | `string\|null` | Default timezone for all events |
| `$distributedMode` | `bool` | Enable distributed mode |

### Methods

#### call()

Schedule a closure/callable.

```php
public function call(callable $callback): ScheduledClosure
```

#### command()

Schedule a console command.

```php
public function command(string $command, array $parameters = []): ScheduledCommand
```

#### job()

Schedule a queue job.

```php
public function job(object $job, ?string $queue = null): ScheduledJob
```

#### events()

Get all scheduled events.

```php
public function events(): array
```

#### dueEvents()

Get all events that are due to run now.

```php
public function dueEvents(): array
```

#### hasEvents()

Check if the schedule has any events.

```php
public function hasEvents(): bool
```

#### count()

Get the number of scheduled events.

```php
public function count(): int
```

#### isDistributed()

Check if scheduler is in distributed mode.

```php
public function isDistributed(): bool
```

#### setTimezone() / timezone()

Set the default timezone for all events.

```php
public function setTimezone(string|DateTimeZone $timezone): self
public function timezone(string|DateTimeZone $timezone): self
```

#### getTimezone()

Get the default timezone.

```php
public function getTimezone(): ?DateTimeZone
```

---

## ScheduledEvent (Abstract)

Base class for all scheduled events.

### Frequency Methods (ManagesFrequencies trait)

| Method | Expression | Description |
|--------|------------|-------------|
| `everyMinute()` | `* * * * *` | Every minute |
| `everyTwoMinutes()` | `*/2 * * * *` | Every 2 minutes |
| `everyThreeMinutes()` | `*/3 * * * *` | Every 3 minutes |
| `everyFourMinutes()` | `*/4 * * * *` | Every 4 minutes |
| `everyFiveMinutes()` | `*/5 * * * *` | Every 5 minutes |
| `everyTenMinutes()` | `*/10 * * * *` | Every 10 minutes |
| `everyFifteenMinutes()` | `*/15 * * * *` | Every 15 minutes |
| `everyThirtyMinutes()` | `*/30 * * * *` | Every 30 minutes |
| `hourly()` | `0 * * * *` | Every hour at minute 0 |
| `hourlyAt(int $minute)` | `{minute} * * * *` | Every hour at specific minute |
| `everyTwoHours()` | `0 */2 * * *` | Every 2 hours |
| `everyThreeHours()` | `0 */3 * * *` | Every 3 hours |
| `everyFourHours()` | `0 */4 * * *` | Every 4 hours |
| `everySixHours()` | `0 */6 * * *` | Every 6 hours |
| `daily()` | `0 0 * * *` | Daily at midnight |
| `dailyAt(string $time)` | `{min} {hour} * * *` | Daily at specific time |
| `twiceDaily(int $first, int $second)` | `0 {first},{second} * * *` | Twice daily |
| `weekly()` | `0 0 * * 0` | Weekly on Sunday |
| `weeklyOn(int $day, string $time)` | `{min} {hour} * * {day}` | Weekly on specific day |
| `monthly()` | `0 0 1 * *` | Monthly on 1st |
| `monthlyOn(int $day, string $time)` | `{min} {hour} {day} * *` | Monthly on specific day |
| `twiceMonthly(int $first, int $second)` | `{min} {hour} {first},{second} * *` | Twice monthly |
| `quarterly()` | `0 0 1 1,4,7,10 *` | Quarterly |
| `yearly()` | `0 0 1 1 *` | Yearly on January 1st |
| `yearlyOn(int $month, int $day)` | `{min} {hour} {day} {month} *` | Yearly on specific date |

### Day Constraint Methods

| Method | Expression Modifier | Description |
|--------|---------------------|-------------|
| `weekdays()` | `* * * * 1-5` | Monday through Friday |
| `weekends()` | `* * * * 0,6` | Saturday and Sunday |
| `sundays()` | `* * * * 0` | Sundays only |
| `mondays()` | `* * * * 1` | Mondays only |
| `tuesdays()` | `* * * * 2` | Tuesdays only |
| `wednesdays()` | `* * * * 3` | Wednesdays only |
| `thursdays()` | `* * * * 4` | Thursdays only |
| `fridays()` | `* * * * 5` | Fridays only |
| `saturdays()` | `* * * * 6` | Saturdays only |
| `days(int\|array $days)` | `* * * * {days}` | Specific days |

### Custom Cron

```php
public function cron(string $expression): self
```

### Filter Methods (ManagesFilters trait)

#### when()

Add a condition that must be true for the event to run.

```php
public function when(bool|callable $condition): self
```

#### skip()

Add a condition that if true, will skip the event.

```php
public function skip(bool|callable $condition): self
```

#### environments()

Limit execution to specific environments.

```php
public function environments(array $environments): self
```

#### production()

Run only in production environment.

```php
public function production(): self
```

#### exceptProduction()

Run everywhere except production.

```php
public function exceptProduction(): self
```

### Output Methods (ManagesOutput trait)

#### before()

Register a callback to run before the event.

```php
public function before(callable $callback): self
```

#### after() / then()

Register a callback to run after the event.

```php
public function after(callable $callback): self
public function then(callable $callback): self
```

#### onSuccess()

Register a callback to run on successful completion.

```php
public function onSuccess(callable $callback): self
```

#### onFailure()

Register a callback to run on failure.

```php
public function onFailure(callable $callback): self
```

#### sendOutputTo()

Write output to a file (overwrites).

```php
public function sendOutputTo(string $path): self
```

#### appendOutputTo()

Append output to a file.

```php
public function appendOutputTo(string $path): self
```

#### handleOutputUsing()

Set a custom output handler.

```php
public function handleOutputUsing(callable $handler): self
```

### Constraint Methods

#### withoutOverlapping()

Prevent overlapping executions.

```php
public function withoutOverlapping(int $expiresAt = 1440): self
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `$expiresAt` | `int` | `1440` | Lock expiration in minutes |

#### onOneServer()

Run on only one server in distributed mode.

```php
public function onOneServer(): self
```

#### runInBackground()

Run the event in a background process.

```php
public function runInBackground(): self
```

### Inspection Methods

#### getExpression()

Get the cron expression.

```php
public function getExpression(): string
```

#### getDescription()

Get the event description.

```php
public function getDescription(): ?string
```

#### getSummary()

Get a summary of the event.

```php
public function getSummary(): string
```

#### isDue()

Check if the event is due to run.

```php
public function isDue(?DateTimeImmutable $date = null): bool
```

#### filtersPass()

Check if all filters pass.

```php
public function filtersPass(): bool
```

#### preventsOverlapping()

Check if overlapping prevention is enabled.

```php
public function preventsOverlapping(): bool
```

#### runsOnOneServer()

Check if one-server mode is enabled.

```php
public function runsOnOneServer(): bool
```

#### runsInBackground()

Check if background mode is enabled.

```php
public function runsInBackground(): bool
```

#### nextRunDate()

Get the next scheduled run date.

```php
public function nextRunDate(): ?DateTimeImmutable
```

#### getOptions()

Get enabled options as array.

```php
public function getOptions(): array
```

### Configuration Methods

#### description()

Set a description for the event.

```php
public function description(string $description): self
```

#### timezone()

Set the timezone for this event.

```php
public function timezone(string|DateTimeZone $timezone): self
```

### Execution

#### run()

Execute the event.

```php
public function run(): mixed
```

---

## ScheduledClosure

Executes PHP callables.

### Constructor

```php
public function __construct(
    callable $callback,
    MutexInterface $mutex,
    bool $distributedMode = false
)
```

---

## ScheduledCommand

Executes console commands.

### Constructor

```php
public function __construct(
    string $command,
    array $parameters = [],
    MutexInterface $mutex,
    bool $distributedMode = false
)
```

### Methods

#### getCommand()

Get the command name.

```php
public function getCommand(): string
```

#### getParameters()

Get command parameters.

```php
public function getParameters(): array
```

---

## ScheduledJob

Dispatches queue jobs.

### Constructor

```php
public function __construct(
    object $job,
    ?string $queue = null,
    MutexInterface $mutex,
    bool $distributedMode = false
)
```

### Methods

#### getJob()

Get the job instance.

```php
public function getJob(): object
```

#### getQueue()

Get the queue name.

```php
public function getQueue(): ?string
```

---

## CronExpression

Static utility class for working with cron expressions.

### isValid()

Check if a cron expression is valid.

```php
public static function isValid(string $expression): bool
```

### isDue()

Check if an expression is due to run.

```php
public static function isDue(
    string $expression,
    ?DateTimeImmutable $date = null
): bool
```

### nextRunDate()

Get the next run date for an expression.

```php
public static function nextRunDate(
    string $expression,
    ?DateTimeZone $timezone = null,
    ?DateTimeImmutable $from = null
): ?DateTimeImmutable
```

### describe()

Get a human-readable description.

```php
public static function describe(string $expression): string
```

---

## MutexInterface

Contract for mutex implementations.

### acquire()

Acquire a lock.

```php
public function acquire(string $name, int $expiresAt): bool
```

### release()

Release a lock.

```php
public function release(string $name): bool
```

### exists()

Check if a lock exists.

```php
public function exists(string $name): bool
```

---

## NullMutex

No-op mutex implementation for single-server mode.

```php
class NullMutex implements MutexInterface
{
    public function acquire(string $name, int $expiresAt): bool // Always returns true
    public function release(string $name): bool                  // Always returns true
    public function exists(string $name): bool                   // Always returns false
}
```

---

## CacheMutex

Cache-based mutex implementation for distributed mode.

### Constructor

```php
public function __construct(CacheInterface $cache)
```

### Methods

Implements `MutexInterface` using the provided cache backend for lock storage.

---

## SchedulerServiceProvider

Service provider for framework integration.

### Constructor

```php
public function __construct(ContainerInterface $container)
```

### Methods

#### register()

Register the scheduler services.

```php
public function register(): void
```

#### boot()

Boot the scheduler services.

```php
public function boot(): void
```

---

## Console Commands

### schedule:run

Run all due scheduled tasks.

```bash
php lalaz schedule:run
```

### schedule:list

List all scheduled tasks.

```bash
php lalaz schedule:list
```

### schedule:test

Test a specific scheduled task by description.

```bash
php lalaz schedule:test "Task Description"
```
