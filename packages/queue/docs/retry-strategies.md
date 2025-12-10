# Retry Strategies

Configure how jobs are retried after failures.

---

## Overview

When a job fails, the queue system calculates a delay before retrying. The delay strategy can be configured per job.

---

## Available Strategies

### Exponential Backoff (Default)

Delay doubles with each attempt:

```
Attempt 1: 60s
Attempt 2: 120s
Attempt 3: 240s
Attempt 4: 480s
```

```php
class MyJob extends Job
{
    protected string $backoffStrategy = 'exponential';
    protected int $retryDelay = 60; // Base delay
}
```

### Linear Backoff

Delay increases linearly:

```
Attempt 1: 60s
Attempt 2: 120s
Attempt 3: 180s
Attempt 4: 240s
```

```php
class MyJob extends Job
{
    protected string $backoffStrategy = 'linear';
    protected int $retryDelay = 60;
}
```

### Fixed Delay

Same delay for all attempts:

```
Attempt 1: 60s
Attempt 2: 60s
Attempt 3: 60s
Attempt 4: 60s
```

```php
class MyJob extends Job
{
    protected string $backoffStrategy = 'fixed';
    protected int $retryDelay = 60;
}
```

---

## Configuration

### Job Properties

```php
class ProcessPaymentJob extends Job
{
    /**
     * Maximum number of retry attempts.
     */
    protected int $maxAttempts = 5;
    
    /**
     * Backoff strategy: exponential, linear, fixed.
     */
    protected string $backoffStrategy = 'exponential';
    
    /**
     * Base delay in seconds.
     */
    protected int $retryDelay = 30;
    
    public function handle(array $payload): void
    {
        // Process payment...
    }
}
```

### At Dispatch Time

```php
ProcessPaymentJob::onQueue('payments')
    ->maxAttempts(3)
    ->backoff('exponential')
    ->retryAfter(60)
    ->dispatch($payload);
```

---

## RetryStrategy Class

Use the `RetryStrategy` class directly:

```php
use Lalaz\Queue\RetryStrategy;

// Calculate delay for attempt
$delay = RetryStrategy::calculateDelay(
    strategy: 'exponential',
    baseDelay: 60,
    attempts: 3,
    withJitter: true
);

// Get delay without jitter
$delay = RetryStrategy::getDelayForAttempt(
    strategy: 'exponential',
    baseDelay: 60,
    attempt: 3
);

// Get complete retry schedule
$schedule = RetryStrategy::getRetrySchedule(
    strategy: 'exponential',
    baseDelay: 60,
    maxAttempts: 5
);
// [1 => 60, 2 => 120, 3 => 240, 4 => 480, 5 => 960]

// Format delay for display
echo RetryStrategy::formatDelay(3661);
// "1h 1m 1s"
```

---

## Jitter

Jitter adds random variation to prevent thundering herd problems:

```php
// With jitter (default)
$delay = RetryStrategy::calculateDelay('exponential', 60, 2, true);
// Returns ~120 ± 12 seconds

// Without jitter
$delay = RetryStrategy::calculateDelay('exponential', 60, 2, false);
// Returns exactly 120 seconds
```

Jitter is ±10% by default:

```php
$delay = RetryStrategy::addJitter(100, 0.1);
// Returns 90-110 seconds
```

---

## Maximum Delay

Delays are capped at 1 hour (3600 seconds):

```php
// Even with many attempts, delay won't exceed 1 hour
$schedule = RetryStrategy::getRetrySchedule('exponential', 60, 10);
// Attempt 7+ will be capped at 3600s
```

---

## Strategy Comparison

| Attempt | Exponential (60s) | Linear (60s) | Fixed (60s) |
|---------|-------------------|--------------|-------------|
| 1 | 60s | 60s | 60s |
| 2 | 120s | 120s | 60s |
| 3 | 240s | 180s | 60s |
| 4 | 480s | 240s | 60s |
| 5 | 960s | 300s | 60s |

---

## Use Case Guidelines

### Exponential (Default)

Best for:
- External API calls
- Rate-limited services
- Network issues

```php
class CallExternalApiJob extends Job
{
    protected string $backoffStrategy = 'exponential';
    protected int $retryDelay = 30;
    protected int $maxAttempts = 5;
}
```

### Linear

Best for:
- Database operations
- File processing
- Predictable recovery

```php
class ProcessFileJob extends Job
{
    protected string $backoffStrategy = 'linear';
    protected int $retryDelay = 60;
    protected int $maxAttempts = 3;
}
```

### Fixed

Best for:
- Quick retries
- Time-sensitive operations
- Consistent behavior

```php
class SendNotificationJob extends Job
{
    protected string $backoffStrategy = 'fixed';
    protected int $retryDelay = 10;
    protected int $maxAttempts = 3;
}
```

---

## Retry Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                       Retry Flow                                 │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  Job Fails                                                       │
│      │                                                           │
│      ▼                                                           │
│  Check attempts < max_attempts                                   │
│      │                                                           │
│      ├── No ──▶ Move to failed_jobs                             │
│      │                                                           │
│      └── Yes ──▶ Calculate delay                                │
│                      │                                           │
│                      ▼                                           │
│                 ┌─────────────────┐                             │
│                 │ Backoff Formula │                             │
│                 └─────────────────┘                             │
│                      │                                           │
│                      ▼                                           │
│                 Add jitter (±10%)                                │
│                      │                                           │
│                      ▼                                           │
│                 Cap at max (3600s)                               │
│                      │                                           │
│                      ▼                                           │
│                 Schedule retry                                   │
│                 (status = delayed)                               │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Next Steps

- [Failed Jobs](failed-jobs.md) - Handle permanent failures
- [Creating Jobs](jobs/creating-jobs.md) - Configure job properties
- [API Reference](api-reference.md) - Full API documentation
