# Jobs Overview

Jobs are units of work that can be executed asynchronously.

---

## What is a Job?

A job encapsulates a task that should be processed in the background:

- Sending emails
- Processing file uploads
- Generating reports
- Syncing data with external APIs
- Any time-consuming operation

---

## Job Structure

Jobs extend the base `Job` class:

```php
<?php

namespace App\Jobs;

use Lalaz\Queue\Job;

class ProcessOrderJob extends Job
{
    protected string $queue = 'orders';
    protected int $priority = 5;
    protected int $maxAttempts = 3;
    
    public function handle(array $payload): void
    {
        $orderId = $payload['order_id'];
        
        // Process the order...
    }
}
```

---

## Job Lifecycle

```
┌─────────────────────────────────────────────────────────────────┐
│                       Job Lifecycle                              │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────┐                                                   │
│  │  Create  │  new ProcessOrderJob()                            │
│  └────┬─────┘                                                   │
│       │                                                          │
│       ▼                                                          │
│  ┌──────────┐                                                   │
│  │ Dispatch │  ProcessOrderJob::dispatch($payload)              │
│  └────┬─────┘                                                   │
│       │                                                          │
│       ▼                                                          │
│  ┌──────────┐                                                   │
│  │  Queue   │  Stored in driver (memory/database)               │
│  └────┬─────┘                                                   │
│       │                                                          │
│       ▼                                                          │
│  ┌──────────┐                                                   │
│  │ Process  │  Worker picks up job                              │
│  └────┬─────┘                                                   │
│       │                                                          │
│       ▼                                                          │
│  ┌──────────┐                                                   │
│  │ Execute  │  handle($payload) is called                       │
│  └────┬─────┘                                                   │
│       │                                                          │
│  ┌────┴────┐                                                    │
│  ▼         ▼                                                    │
│ Success  Failure ──▶ Retry or Failed Jobs                       │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Creating Jobs

See [Creating Jobs](creating-jobs.md) for detailed instructions.

---

## Dispatching Jobs

See [Dispatching Jobs](dispatching.md) for all dispatch options.

---

## Quick Reference

### Basic Dispatch

```php
SendEmailJob::dispatch(['email' => 'test@example.com']);
```

### With Options

```php
SendEmailJob::onQueue('emails')
    ->priority(1)
    ->delay(300)
    ->dispatch(['email' => 'test@example.com']);
```

### Synchronous Execution

```php
SendEmailJob::dispatchSync(['email' => 'test@example.com']);
```

---

## Job Properties

| Property | Default | Description |
|----------|---------|-------------|
| `$queue` | `'default'` | Target queue name |
| `$priority` | `5` | Priority (0-10) |
| `$maxAttempts` | `3` | Max retry attempts |
| `$timeout` | `300` | Execution timeout |
| `$backoffStrategy` | `'exponential'` | Retry delay strategy |
| `$retryDelay` | `60` | Base retry delay |
| `$tags` | `[]` | Job tags |

---

## Next Steps

- [Creating Jobs](creating-jobs.md) - Define custom jobs
- [Dispatching Jobs](dispatching.md) - All dispatch options
- [Failed Jobs](../failed-jobs.md) - Handle failures
