# Failed Jobs

How to handle jobs that fail after exhausting all retry attempts.

---

## Overview

When a job fails after all retry attempts, it's moved to the **dead letter queue** (failed_jobs table). This allows you to:

- Inspect failed jobs
- Retry them manually
- Analyze failure patterns
- Clean up old failures

---

## Viewing Failed Jobs

### Get All Failed Jobs

```php
use Lalaz\Queue\QueueManager;

$manager = resolve(QueueManager::class);

$failedJobs = $manager->getFailedJobs(limit: 50, offset: 0);

foreach ($failedJobs as $job) {
    echo "Job {$job['id']}: {$job['task']}\n";
    echo "Failed at: {$job['failed_at']}\n";
    echo "Error: {$job['exception']}\n";
}
```

### Get Single Failed Job

```php
$job = $manager->getFailedJob($id);

if ($job) {
    echo "Task: {$job['task']}\n";
    echo "Queue: {$job['queue']}\n";
    echo "Payload: " . json_encode($job['payload']) . "\n";
    echo "Exception: {$job['exception']}\n";
    echo "Stack Trace: {$job['stack_trace']}\n";
    echo "Attempts: {$job['total_attempts']}\n";
    echo "Retry History: " . json_encode($job['retry_history']) . "\n";
}
```

---

## Retrying Failed Jobs

### Retry Single Job

```php
$success = $manager->retryFailedJob($id);

if ($success) {
    echo "Job re-queued successfully\n";
} else {
    echo "Failed to retry job\n";
}
```

### Retry All Failed Jobs

```php
$count = $manager->retryAllFailedJobs();
echo "Retried {$count} jobs\n";

// Retry only specific queue
$count = $manager->retryAllFailedJobs('emails');
```

---

## Purging Failed Jobs

### Purge All Failed Jobs

```php
$deleted = $manager->purgeFailedJobs();
echo "Deleted {$deleted} failed jobs\n";
```

### Purge by Queue

```php
$deleted = $manager->purgeFailedJobs('emails');
```

### Purge Old Jobs

```php
// Delete jobs older than 7 days
$deleted = $manager->purgeOldJobs(olderThanDays: 7);
```

---

## Failed Job Structure

| Field | Description |
|-------|-------------|
| `id` | Unique identifier |
| `queue` | Original queue name |
| `task` | Job class name |
| `payload` | Job payload (JSON) |
| `exception` | Error message |
| `stack_trace` | Full stack trace |
| `failed_at` | Failure timestamp |
| `total_attempts` | Number of attempts |
| `retry_history` | Previous error logs |
| `original_job_id` | Original job ID |
| `priority` | Job priority |
| `tags` | Job tags |

---

## CLI Commands

```bash
# View failed jobs
php lalaz queue:failed

# Retry a specific job
php lalaz queue:retry 123

# Retry all failed jobs
php lalaz queue:retry --all

# Flush all failed jobs
php lalaz queue:flush-failed

# Flush failed jobs from specific queue
php lalaz queue:flush-failed --queue=emails
```

---

## Failure Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                       Failure Flow                               │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  Job Execution ──▶ Exception Thrown                             │
│                         │                                        │
│                         ▼                                        │
│                  attempts < max_attempts?                        │
│                         │                                        │
│              ┌──────────┴──────────┐                            │
│              │                     │                            │
│              ▼                     ▼                            │
│            Yes                    No                            │
│              │                     │                            │
│              ▼                     ▼                            │
│         Schedule              Move to DLQ                       │
│          Retry               (failed_jobs)                      │
│              │                     │                            │
│              ▼                     ▼                            │
│        Calculate              Log failure                       │
│         Delay                      │                            │
│              │                     ▼                            │
│              ▼               Available for                      │
│         Re-queue             manual retry                       │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Monitoring Failures

### Get Queue Statistics

```php
$stats = $manager->getStats();

echo "Total Jobs: {$stats['total']}\n";
echo "Failed Jobs: {$stats['failed']}\n";
echo "In Dead Letter Queue: {$stats['failed_total']}\n";
```

### View Retry History

```php
$job = $manager->getFailedJob($id);

foreach ($job['retry_history'] as $attempt) {
    echo "Attempt at {$attempt['created_at']}: {$attempt['message']}\n";
}
```

---

## Best Practices

### 1. Regular Monitoring

Check for failed jobs regularly:

```php
$failedJobs = $manager->getFailedJobs(limit: 100);

if (count($failedJobs) > 0) {
    // Send alert to admin
    AlertService::notify("You have " . count($failedJobs) . " failed jobs");
}
```

### 2. Automatic Cleanup

Schedule regular cleanup:

```php
// In a scheduled task
$manager->purgeOldJobs(olderThanDays: 30);
```

### 3. Analyze Patterns

Look for common failure causes:

```php
$failedJobs = $manager->getFailedJobs(limit: 1000);

$errors = [];
foreach ($failedJobs as $job) {
    $error = $job['exception'];
    $errors[$error] = ($errors[$error] ?? 0) + 1;
}

arsort($errors);
print_r($errors); // Most common errors first
```

### 4. Fix Root Cause

Before retrying jobs, fix the underlying issue:

```php
// 1. Identify the problem
$job = $manager->getFailedJob($id);
echo $job['exception'];
echo $job['stack_trace'];

// 2. Fix the code/configuration

// 3. Retry the jobs
$manager->retryAllFailedJobs();
```

---

## Next Steps

- [Retry Strategies](retry-strategies.md) - Configure retry behavior
- [Database Driver](drivers/database.md) - Storage details
- [Testing](testing.md) - Test job failures
