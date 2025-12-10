# Lalaz Queue

A queue and background job processing system for the Lalaz Framework.

---

## Table of Contents

- [Installation](installation.md)
- [Quick Start](quick-start.md)
- [Core Concepts](concepts.md)

### Drivers

- [Drivers Overview](drivers/index.md)
- [InMemory Driver](drivers/inmemory.md)
- [Database Driver](drivers/database.md)

### Jobs

- [Jobs Overview](jobs/index.md)
- [Creating Jobs](jobs/creating-jobs.md)
- [Dispatching Jobs](jobs/dispatching.md)

### Advanced

- [Failed Jobs](failed-jobs.md)
- [Retry Strategies](retry-strategies.md)

### Reference

- [Testing](testing.md)
- [API Reference](api-reference.md)
- [Glossary](glossary.md)

### Examples

- [Examples Overview](examples/index.md)
- [Background Processing](examples/background-processing.md)

---

## Overview

The Queue package provides a robust system for deferring time-consuming tasks such as sending emails, processing uploads, or generating reports. Jobs can be dispatched to run asynchronously in the background, improving application response times.

### Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        Application                               │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────┐    ┌─────────────────┐    ┌──────────────────┐   │
│  │   Job    │───▶│  QueueManager   │───▶│  QueueDriver     │   │
│  └──────────┘    └─────────────────┘    └──────────────────┘   │
│                           │                      │              │
│                           │                      ▼              │
│                           │              ┌──────────────────┐   │
│                           │              │  Storage         │   │
│                           │              │  (Memory/DB)     │   │
│                           │              └──────────────────┘   │
│                           ▼                                     │
│                  ┌─────────────────┐                            │
│                  │  JobExecutor    │                            │
│                  └─────────────────┘                            │
│                           │                                     │
│                           ▼                                     │
│                  ┌─────────────────┐                            │
│                  │  Job::handle()  │                            │
│                  └─────────────────┘                            │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### Key Features

- **Multiple Drivers** - InMemory for development, Database (MySQL/PostgreSQL/SQLite) for production
- **Job Priorities** - Process important jobs first
- **Delayed Jobs** - Schedule jobs to run at a specific time
- **Automatic Retries** - Configurable retry strategies with exponential backoff
- **Failed Job Handling** - Dead letter queue with retry capabilities
- **Batch Processing** - Process multiple jobs efficiently
- **Job Metrics** - Track execution time and memory usage

---

## Quick Example

```php
use Lalaz\Queue\Job;

// Define a job
class SendEmailJob extends Job
{
    public function handle(array $payload): void
    {
        $email = $payload['email'];
        $subject = $payload['subject'];
        
        // Send the email...
    }
}

// Dispatch the job
SendEmailJob::dispatch([
    'email' => 'user@example.com',
    'subject' => 'Welcome!',
]);

// Or with options
SendEmailJob::onQueue('emails')
    ->priority(8)
    ->delay(60)
    ->dispatch(['email' => 'user@example.com']);
```

---

## Requirements

- PHP 8.2 or higher
- Lalaz Framework
- PDO extension (for database drivers)

---

## Next Steps

- [Installation Guide](installation.md) - Set up the Queue package
- [Quick Start](quick-start.md) - Get running in 5 minutes
- [Core Concepts](concepts.md) - Understand how queues work
