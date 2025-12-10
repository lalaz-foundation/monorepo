# Database Driver

The Database driver persists jobs to a database. Supports MySQL, PostgreSQL, and SQLite.

---

## Configuration

```php
// config/queue.php
return [
    'enabled' => true,
    'driver' => 'mysql', // mysql | pgsql | sqlite
    'connection' => null, // Use default connection
    'job_timeout' => 300,
    'tables' => [
        'jobs' => 'jobs',
        'failed' => 'failed_jobs',
        'logs' => 'job_logs',
    ],
];
```

---

## Setup

### Run Migration

```bash
php lalaz migrate
```

The migration creates three tables:
- `jobs` - Active job queue
- `failed_jobs` - Dead letter queue
- `job_logs` - Execution logs

---

## Features

### Add Jobs

```php
use Lalaz\Queue\QueueManager;

$manager = resolve(QueueManager::class);

$manager->addJob(
    jobClass: SendEmailJob::class,
    payload: ['email' => 'test@example.com'],
    queue: 'emails',
    priority: 5,
    delay: 60,
    options: [
        'max_attempts' => 3,
        'timeout' => 120,
        'backoff_strategy' => 'exponential',
        'retry_delay' => 60,
        'tags' => ['email', 'notification'],
    ]
);
```

### Process Jobs

```php
// Process one job
$manager->processJobs();

// Process specific queue
$manager->processJobs('emails');
```

### Batch Processing

```php
$stats = $manager->processBatch(
    batchSize: 10,
    queue: 'emails',
    maxExecutionTime: 55
);
```

### Statistics

```php
$stats = $manager->getStats();
// [
//     'pending' => 50,
//     'processing' => 2,
//     'delayed' => 10,
//     'completed' => 1000,
//     'failed' => 5,
//     'high_priority' => 3,
//     'avg_attempts' => 1.2,
//     'failed_total' => 15,
//     'total' => 1067,
// ]
```

---

## Failed Jobs

### View Failed Jobs

```php
$failedJobs = $manager->getFailedJobs(limit: 50, offset: 0);
```

### View Single Failed Job

```php
$job = $manager->getFailedJob($id);
// [
//     'id' => 1,
//     'queue' => 'emails',
//     'task' => 'App\Jobs\SendEmailJob',
//     'payload' => ['email' => 'test@example.com'],
//     'exception' => 'Connection timeout',
//     'stack_trace' => '...',
//     'failed_at' => '2024-01-15 10:30:00',
//     'total_attempts' => 3,
//     'retry_history' => [...],
// ]
```

### Retry Failed Job

```php
$success = $manager->retryFailedJob($id);
```

### Retry All Failed Jobs

```php
$count = $manager->retryAllFailedJobs();
$count = $manager->retryAllFailedJobs('emails'); // Specific queue
```

---

## Maintenance

### Release Delayed Jobs

Jobs become available when their delay expires:

```php
$driver->releaseDelayedJobs();
```

### Release Stuck Jobs

Jobs stuck in "processing" state are released for retry:

```php
$released = $driver->releaseStuckJobs();
```

### Purge Old Jobs

Remove completed and failed jobs older than N days:

```php
$deleted = $manager->purgeOldJobs(olderThanDays: 7);
```

### Purge Failed Jobs

Remove all failed jobs:

```php
$deleted = $manager->purgeFailedJobs();
$deleted = $manager->purgeFailedJobs('emails'); // Specific queue
```

---

## Job States

| State | Description |
|-------|-------------|
| `pending` | Ready to be processed |
| `delayed` | Waiting for availability time |
| `processing` | Currently being executed |
| `completed` | Successfully finished |
| `failed` | Moved to dead letter queue |

---

## Job Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                    Database Job Flow                             │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  add() ──▶ INSERT ──▶ pending/delayed                           │
│                            │                                     │
│                            ▼                                     │
│         releaseDelayedJobs() ──▶ pending                        │
│                            │                                     │
│                            ▼                                     │
│              claimNextJob() ──▶ processing                      │
│                            │                                     │
│                    ┌───────┴───────┐                            │
│                    ▼               ▼                            │
│              completed         failure                          │
│                                    │                             │
│                        ┌───────────┴───────────┐                │
│                        ▼                       ▼                │
│                   delayed                failed_jobs            │
│                  (retry)                (dead letter)           │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Logging

The database driver logs job execution to `job_logs`:

```php
// View logs for a job
$logger = resolve(QueueLogger::class);
$logs = $logger->getJobLogs($jobId);

// View logs by level
$errors = $logger->getLogsByLevel('error');

// Cleanup old logs
$deleted = $logger->cleanup(olderThanDays: 30);
```

---

## CLI Commands

```bash
# Process jobs
php lalaz queue:work --queue=emails --batch=10

# View failed jobs
php lalaz queue:failed

# Retry failed job
php lalaz queue:retry 123

# Flush failed jobs
php lalaz queue:flush-failed

# View statistics
php lalaz queue:stats

# Run maintenance
php lalaz queue:maintain
```

---

## Performance Tips

1. **Index columns** - The migration creates necessary indexes
2. **Batch processing** - Process multiple jobs per cycle
3. **Multiple workers** - Run several queue:work processes
4. **Cleanup regularly** - Purge old completed jobs
5. **Monitor metrics** - Track execution time and memory

---

## Next Steps

- [Failed Jobs](../failed-jobs.md) - Handle job failures
- [Retry Strategies](../retry-strategies.md) - Configure retries
- [Jobs Overview](../jobs/index.md) - Creating jobs
