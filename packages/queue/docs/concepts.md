# Core Concepts

Understanding the key concepts of the Queue package.

---

## Jobs

A **Job** is a unit of work that can be executed asynchronously. Jobs extend the base `Job` class and implement a `handle()` method.

```php
use Lalaz\Queue\Job;

class ProcessUploadJob extends Job
{
    public function handle(array $payload): void
    {
        $filePath = $payload['file_path'];
        // Process the uploaded file...
    }
}
```

### Job Properties

Jobs can configure their behavior through protected properties:

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `$queue` | string | `'default'` | Queue name for the job |
| `$priority` | int | `5` | Priority (0-10, lower = higher) |
| `$maxAttempts` | int | `3` | Maximum retry attempts |
| `$timeout` | int | `300` | Execution timeout in seconds |
| `$backoffStrategy` | string | `'exponential'` | Retry delay strategy |
| `$retryDelay` | int | `60` | Base delay between retries |
| `$tags` | array | `[]` | Tags for categorization |

---

## QueueManager

The **QueueManager** coordinates job enqueueing and processing. It acts as the main interface for working with queues.

```php
use Lalaz\Queue\QueueManager;

$manager = resolve(QueueManager::class);

// Add a job
$manager->addJob(SendEmailJob::class, ['email' => 'test@example.com']);

// Process jobs
$manager->processJobs();

// Get statistics
$stats = $manager->getStats();
```

### Synchronous Fallback

When `queue.enabled` is `false`, the QueueManager executes jobs synchronously:

```php
// config/queue.php
return [
    'enabled' => false, // Jobs run immediately
];
```

This is useful for development and testing.

---

## Drivers

A **Driver** is the backend storage and processing mechanism for the queue. The package includes:

### InMemory Driver

Stores jobs in memory. Best for development and testing.

```php
// config/queue.php
return [
    'driver' => 'memory',
];
```

### Database Drivers

Stores jobs in a database table. Best for production.

```php
// MySQL
return ['driver' => 'mysql'];

// PostgreSQL
return ['driver' => 'pgsql'];

// SQLite
return ['driver' => 'sqlite'];
```

---

## PendingDispatch

**PendingDispatch** provides a fluent API for configuring job dispatch:

```php
use Lalaz\Queue\PendingDispatch;

SendEmailJob::onQueue('emails')
    ->priority(1)
    ->delay(60)
    ->maxAttempts(5)
    ->timeout(120)
    ->backoff('exponential')
    ->tags(['email', 'notification'])
    ->dispatch(['email' => 'user@example.com']);
```

### Available Methods

| Method | Description |
|--------|-------------|
| `onQueue(string)` | Set target queue |
| `priority(int)` | Set job priority (0-10) |
| `delay(int)` | Delay execution in seconds |
| `maxAttempts(int)` | Set max retry attempts |
| `timeout(int)` | Set execution timeout |
| `backoff(string)` | Set backoff strategy |
| `retryAfter(int)` | Set base retry delay |
| `tags(array)` | Add tags to job |
| `withOptions(array)` | Set multiple options |
| `dispatch(array)` | Execute dispatch |

---

## JobExecutor

The **JobExecutor** is responsible for executing jobs. It:

1. Resolves the job class
2. Calls the `handle()` method
3. Logs execution metrics
4. Handles errors

```php
use Lalaz\Queue\JobExecutor;

$executor = new JobExecutor($resolver, $logger);
$executor->execute($jobData);
```

---

## RetryStrategy

The **RetryStrategy** calculates delay between retry attempts:

### Exponential Backoff (Default)

Delay doubles with each attempt:

```
Attempt 1: 60s
Attempt 2: 120s
Attempt 3: 240s
```

### Linear Backoff

Delay increases linearly:

```
Attempt 1: 60s
Attempt 2: 120s
Attempt 3: 180s
```

### Fixed Delay

Same delay for all attempts:

```
Attempt 1: 60s
Attempt 2: 60s
Attempt 3: 60s
```

### Jitter

Random variation added to prevent thundering herd:

```php
$delay = RetryStrategy::calculateDelay('exponential', 60, 2, true);
// Returns ~120 ± 12 seconds
```

---

## Queue Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                        Job Dispatch                              │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  Job::dispatch() ──▶ QueueManager ──▶ Driver::add()             │
│                                              │                   │
│                                              ▼                   │
│                                       ┌──────────┐              │
│                                       │  Storage │              │
│                                       └──────────┘              │
│                                                                  │
├─────────────────────────────────────────────────────────────────┤
│                        Job Processing                            │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  QueueManager::process() ──▶ Driver::process()                  │
│                                      │                           │
│                                      ▼                           │
│                              ┌──────────────┐                   │
│                              │ JobExecutor  │                   │
│                              └──────────────┘                   │
│                                      │                           │
│                          ┌───────────┴───────────┐              │
│                          ▼                       ▼              │
│                    ┌──────────┐           ┌──────────┐          │
│                    │ Success  │           │ Failure  │          │
│                    │ Complete │           │  Retry   │          │
│                    └──────────┘           └──────────┘          │
│                                                  │               │
│                                      ┌───────────┴───────────┐  │
│                                      ▼                       ▼  │
│                               ┌──────────┐           ┌──────────┐
│                               │  Retry   │           │  Failed  │
│                               │  Queue   │           │  Jobs    │
│                               └──────────┘           └──────────┘
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Next Steps

- [Drivers Overview](drivers/index.md) - Configure queue drivers
- [Creating Jobs](jobs/creating-jobs.md) - Define custom jobs
- [Failed Jobs](failed-jobs.md) - Handle job failures
- [API Reference](api-reference.md) - Full API documentation
