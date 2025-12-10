# Glossary

Definitions of terms used in the Lalaz Scheduler package.

## A

### After Callback
A callback function that executes after a scheduled event completes, regardless of success or failure.

### Append Output
A method to add task output to an existing file without overwriting previous content.

## B

### Background Execution
Running a scheduled task in a background process, allowing the scheduler to continue without waiting for the task to complete.

### Before Callback
A callback function that executes immediately before a scheduled event runs.

## C

### Cache Mutex
A mutex implementation that uses a cache backend (like Redis) to coordinate locks across multiple servers in distributed mode.

### Constraint
A limitation applied to a scheduled event, such as preventing overlapping or requiring specific environments.

### Cron Expression
A string format using five fields (minute, hour, day of month, month, day of week) to specify when a task should run. Example: `0 0 * * *` runs at midnight daily.

### Cron Field
One of the five components of a cron expression:
- **Minute**: 0-59
- **Hour**: 0-23
- **Day of Month**: 1-31
- **Month**: 1-12
- **Day of Week**: 0-6 (Sunday=0)

## D

### Distributed Mode
A scheduler configuration where multiple servers run the same schedule, requiring coordination to prevent duplicate task execution.

### Due Event
A scheduled event whose cron expression matches the current time and all filters pass.

## E

### Environment Filter
A filter that restricts task execution to specific environments (e.g., production, staging, development).

### Event
See [Scheduled Event](#scheduled-event).

### Expression
See [Cron Expression](#cron-expression).

## F

### Failure Callback
A callback function that executes when a scheduled event throws an exception.

### Filter
A condition that must be met for a scheduled event to run. Events can have multiple filters applied.

### Frequency
How often a scheduled event runs, defined by its cron expression or frequency method calls.

## H

### Hook
See [Lifecycle Hook](#lifecycle-hook).

## L

### Lifecycle Hook
A callback point in the event execution lifecycle: before, after, onSuccess, or onFailure.

### List Values
Cron expression feature allowing multiple values separated by commas. Example: `0 8,12,18 * * *` runs at 8am, noon, and 6pm.

### Lock
A mechanism to prevent concurrent execution of the same task. See [Mutex](#mutex).

### Lock Expiration
The time after which a lock automatically releases if not explicitly released.

## M

### Mutex
A synchronization mechanism to prevent concurrent access. Used in distributed mode to ensure tasks run on only one server.

### MutexInterface
The contract defining mutex operations: acquire, release, and exists.

## N

### NullMutex
A no-op mutex implementation that always allows execution. Used in single-server mode.

## O

### One Server
A constraint ensuring a task runs on only one server in distributed mode.

### Output Handler
A callback that receives the output and exit code of a scheduled event.

### Overlapping
When multiple instances of the same scheduled event run simultaneously.

### Overlap Prevention
A constraint that prevents a new instance from starting if a previous instance is still running.

## R

### Range Values
Cron expression feature specifying a range with a hyphen. Example: `0 9-17 * * *` runs every hour from 9am to 5pm.

### Run
Execute a scheduled event and its associated callbacks.

## S

### Schedule
The main container class for registering and managing scheduled events.

### Scheduled Closure
An event type that executes a PHP callable (function, closure, or method).

### Scheduled Command
An event type that executes a console command.

### Scheduled Event
A task registered with the scheduler that runs at specified times. Base class for ScheduledClosure, ScheduledCommand, and ScheduledJob.

### Scheduled Job
An event type that dispatches a queue job.

### Scheduler Mode
The operational mode: single (default) or distributed.

### Single Server Mode
The default scheduler mode where all servers run all tasks independently.

### Skip Filter
A filter that prevents task execution when its condition is true.

### Step Values
Cron expression feature using a slash to specify intervals. Example: `*/5 * * * *` runs every 5 minutes.

### Success Callback
A callback function that executes when a scheduled event completes without throwing an exception.

### Summary
A brief description of a scheduled event, typically including the task name or callback details.

## T

### Task
The executable code within a scheduled event (closure, command, or job).

### Then Callback
Alias for [After Callback](#after-callback).

### Timezone
The timezone used to evaluate when an event is due. Can be set at schedule or event level.

## W

### When Filter
A filter that allows task execution only when its condition is true.

### Wildcard
The asterisk character (`*`) in a cron expression, matching any value in that field.

## Cron Expression Examples

| Expression | Description |
|------------|-------------|
| `* * * * *` | Every minute |
| `*/5 * * * *` | Every 5 minutes |
| `0 * * * *` | Every hour at minute 0 |
| `0 0 * * *` | Daily at midnight |
| `0 12 * * *` | Daily at noon |
| `0 0 * * 0` | Weekly on Sunday |
| `0 0 * * 1-5` | Weekdays at midnight |
| `0 0 1 * *` | Monthly on the 1st |
| `0 0 1 1 *` | Yearly on January 1st |
| `0 9-17 * * 1-5` | Hourly 9am-5pm on weekdays |
| `0 8,12,18 * * *` | At 8am, noon, and 6pm |

## Frequency Method Reference

| Method | Cron Expression |
|--------|-----------------|
| `everyMinute()` | `* * * * *` |
| `everyFiveMinutes()` | `*/5 * * * *` |
| `everyTenMinutes()` | `*/10 * * * *` |
| `everyFifteenMinutes()` | `*/15 * * * *` |
| `everyThirtyMinutes()` | `*/30 * * * *` |
| `hourly()` | `0 * * * *` |
| `hourlyAt(15)` | `15 * * * *` |
| `daily()` | `0 0 * * *` |
| `dailyAt('13:00')` | `0 13 * * *` |
| `twiceDaily(1, 13)` | `0 1,13 * * *` |
| `weekly()` | `0 0 * * 0` |
| `weeklyOn(1, '08:00')` | `0 8 * * 1` |
| `monthly()` | `0 0 1 * *` |
| `monthlyOn(15)` | `0 0 15 * *` |
| `quarterly()` | `0 0 1 1,4,7,10 *` |
| `yearly()` | `0 0 1 1 *` |

## Day Constraint Reference

| Method | Cron Modifier |
|--------|---------------|
| `weekdays()` | `* * * * 1-5` |
| `weekends()` | `* * * * 0,6` |
| `sundays()` | `* * * * 0` |
| `mondays()` | `* * * * 1` |
| `tuesdays()` | `* * * * 2` |
| `wednesdays()` | `* * * * 3` |
| `thursdays()` | `* * * * 4` |
| `fridays()` | `* * * * 5` |
| `saturdays()` | `* * * * 6` |
