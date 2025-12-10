# API Reference

Complete API documentation for the Queue package.

---

## Job

Base class for all jobs.

```php
namespace Lalaz\Queue;

abstract class Job implements JobInterface
```

### Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `$queue` | string | `'default'` | Target queue name |
| `$priority` | int | `5` | Priority (0-10) |
| `$maxAttempts` | int | `3` | Maximum retry attempts |
| `$timeout` | int | `300` | Execution timeout |
| `$backoffStrategy` | string | `'exponential'` | Retry strategy |
| `$retryDelay` | int | `60` | Base retry delay |
| `$tags` | array | `[]` | Job tags |

### Static Methods

#### dispatch()

```php
public static function dispatch(array $payload = []): bool
```

Dispatches the job to the queue.

| Parameter | Type | Description |
|-----------|------|-------------|
| `$payload` | array | Data passed to handle() |

**Returns:** `bool` - True if dispatched successfully

#### dispatchSync()

```php
public static function dispatchSync(array $payload = []): bool
```

Executes the job immediately (synchronously).

#### onQueue()

```php
public static function onQueue(string $queue): PendingDispatch
```

Creates a PendingDispatch for the specified queue.

#### withPriority()

```php
public static function withPriority(int $priority): PendingDispatch
```

Creates a PendingDispatch with custom priority.

#### later()

```php
public static function later(int $seconds): PendingDispatch
```

Creates a PendingDispatch with delay.

#### setTestDispatcher()

```php
public static function setTestDispatcher(?JobDispatcherInterface $dispatcher): void
```

Sets a test dispatcher for testing purposes.

#### setDispatcherResolver()

```php
public static function setDispatcherResolver(?callable $resolver): void
```

Sets a custom dispatcher resolver callable.

### Abstract Methods

#### handle()

```php
abstract public function handle(array $payload): void
```

Executes the job logic.

---

## QueueManager

Coordinates job enqueueing and processing.

```php
namespace Lalaz\Queue;

class QueueManager implements QueueManagerInterface
```

### Constructor

```php
public function __construct(
    QueueDriverInterface $driver,
    ?JobExecutorInterface $executor = null
)
```

### Static Methods

#### isEnabled()

```php
public static function isEnabled(): bool
```

Returns whether queue processing is enabled.

### Methods

#### addJob()

```php
public function addJob(
    string $jobClass,
    array $payload = [],
    string $queue = 'default',
    int $priority = 5,
    ?int $delay = null,
    array $options = [],
): bool
```

Adds a job to the queue.

| Parameter | Type | Description |
|-----------|------|-------------|
| `$jobClass` | string | Job class name |
| `$payload` | array | Job payload |
| `$queue` | string | Queue name |
| `$priority` | int | Priority (0-10) |
| `$delay` | int\|null | Delay in seconds |
| `$options` | array | Additional options |

#### add()

```php
public function add(
    string $jobClass,
    array $payload = [],
    string $queue = 'default',
    int $priority = 5,
    ?int $delay = null,
    array $options = [],
): bool
```

Alias for addJob().

#### processJobs()

```php
public function processJobs(?string $queue = null): void
```

Processes the next job in the queue.

#### processBatch()

```php
public function processBatch(
    int $batchSize = 10,
    ?string $queue = null,
    int $maxExecutionTime = 55,
): array
```

Processes multiple jobs in a batch.

**Returns:** Array with `processed`, `successful`, `failed`, `execution_time`

#### getStats()

```php
public function getStats(?string $queue = null): array
```

Returns queue statistics.

#### getFailedJobs()

```php
public function getFailedJobs(int $limit = 50, int $offset = 0): array
```

Returns failed jobs.

#### getFailedJob()

```php
public function getFailedJob(int $id): ?array
```

Returns a specific failed job.

#### retryFailedJob()

```php
public function retryFailedJob(int $id): bool
```

Retries a failed job.

#### retryAllFailedJobs()

```php
public function retryAllFailedJobs(?string $queue = null): int
```

Retries all failed jobs.

#### purgeOldJobs()

```php
public function purgeOldJobs(int $olderThanDays = 7): int
```

Removes old completed/failed jobs.

#### purgeFailedJobs()

```php
public function purgeFailedJobs(?string $queue = null): int
```

Removes all failed jobs.

#### getDriver()

```php
public function getDriver(): QueueDriverInterface
```

Returns the queue driver.

---

## PendingDispatch

Fluent builder for job dispatch.

```php
namespace Lalaz\Queue;

class PendingDispatch
```

### Constructor

```php
public function __construct(string $jobClass, ?string $queue = null)
```

### Methods

#### onQueue()

```php
public function onQueue(string $queue): self
```

#### priority()

```php
public function priority(int $priority): self
```

#### delay()

```php
public function delay(int $seconds): self
```

#### maxAttempts()

```php
public function maxAttempts(int $attempts): self
```

#### timeout()

```php
public function timeout(int $seconds): self
```

#### backoff()

```php
public function backoff(string $strategy): self
```

#### retryAfter()

```php
public function retryAfter(int $seconds): self
```

#### tags()

```php
public function tags(array $tags): self
```

#### withOptions()

```php
public function withOptions(array $options): self
```

#### dispatch()

```php
public function dispatch(array $payload = []): bool
```

---

## RetryStrategy

Calculates retry delays.

```php
namespace Lalaz\Queue;

class RetryStrategy
```

### Static Methods

#### calculateDelay()

```php
public static function calculateDelay(
    string $strategy,
    int $baseDelay,
    int $attempts,
    bool $withJitter = true
): int
```

#### getDelayForAttempt()

```php
public static function getDelayForAttempt(
    string $strategy,
    int $baseDelay,
    int $attempt
): int
```

#### getRetrySchedule()

```php
public static function getRetrySchedule(
    string $strategy,
    int $baseDelay,
    int $maxAttempts
): array
```

#### addJitter()

```php
public static function addJitter(int $delay, float $jitterPercent = 0.1): int
```

#### formatDelay()

```php
public static function formatDelay(int $seconds): string
```

---

## Jobs (Facade)

Static access to job processing.

```php
namespace Lalaz\Queue;

class Jobs
```

### Static Methods

#### run()

```php
public static function run(?string $queue = null): void
```

Runs job processing.

#### batch()

```php
public static function batch(
    int $batchSize = 10,
    ?string $queue = null,
    int $maxExecutionTime = 55,
): array
```

Processes a batch of jobs.

---

## JobExecutor

Executes jobs.

```php
namespace Lalaz\Queue;

class JobExecutor implements JobExecutorInterface
```

### Constructor

```php
public function __construct(
    ?JobResolverInterface $resolver = null,
    ?QueueLoggerInterface $logger = null
)
```

### Methods

#### execute()

```php
public function execute(array $job): void
```

Executes a job from queue data.

#### executeSync()

```php
public function executeSync(string $jobClass, array $payload): bool
```

Executes a job synchronously.

---

## QueueLogger

Logs job execution.

```php
namespace Lalaz\Queue;

class QueueLogger implements QueueLoggerInterface
```

### Methods

#### log()

```php
public function log(
    string $level,
    string $message,
    array $context = [],
    ?int $jobId = null,
    ?string $queue = null,
    ?string $task = null,
): void
```

#### debug(), info(), warning(), error()

```php
public function debug(string $message, array $context = [], ...): void
public function info(string $message, array $context = [], ...): void
public function warning(string $message, array $context = [], ...): void
public function error(string $message, array $context = [], ...): void
```

#### logJobMetrics()

```php
public function logJobMetrics(
    int $jobId,
    string $queue,
    string $task,
    float $executionTime,
    int $memoryUsage,
): void
```

#### getJobLogs()

```php
public function getJobLogs(int $jobId, int $limit = 50): array
```

#### getLogsByLevel()

```php
public function getLogsByLevel(string $level, int $limit = 100): array
```

#### cleanup()

```php
public function cleanup(int $olderThanDays = 30): int
```

---

## Interfaces

### JobInterface

```php
interface JobInterface
{
    public function handle(array $payload): void;
}
```

### QueueDriverInterface

Extends: `JobDispatcherInterface`, `JobProcessorInterface`, `QueueStatsInterface`, `FailedJobsInterface`, `QueueMaintenanceInterface`

### JobDispatcherInterface

```php
interface JobDispatcherInterface
{
    public function add(
        string $jobClass,
        array $payload = [],
        string $queue = 'default',
        int $priority = 5,
        ?int $delay = null,
        array $options = [],
    ): bool;
}
```

### JobProcessorInterface

```php
interface JobProcessorInterface
{
    public function process(?string $queue = null): void;
    public function processBatch(
        int $batchSize = 10,
        ?string $queue = null,
        int $maxExecutionTime = 55,
    ): array;
}
```

---

## Next Steps

- [Glossary](glossary.md) - Terminology reference
- [Examples](examples/index.md) - Usage examples
