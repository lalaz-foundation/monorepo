# Drivers Overview

Queue drivers determine how jobs are stored and processed.

---

## Available Drivers

| Driver | Use Case | Persistence | Production Ready |
|--------|----------|-------------|------------------|
| `memory` | Development, testing | No | No |
| `mysql` | Production with MySQL | Yes | Yes |
| `pgsql` | Production with PostgreSQL | Yes | Yes |
| `sqlite` | Small apps, testing | Yes | Limited |

---

## Configuring Drivers

Set the driver in `config/queue.php`:

```php
<?php

return [
    'enabled' => true,
    'driver' => 'mysql', // memory | mysql | pgsql | sqlite
];
```

---

## InMemory Driver

The **InMemory** driver stores jobs in a PHP array. Jobs are lost when the process ends.

```php
return [
    'driver' => 'memory',
];
```

**Best for:**
- Development
- Unit testing
- Quick prototyping

**Limitations:**
- Jobs are not persisted
- No job history
- Single process only

See [InMemory Driver](inmemory.md) for details.

---

## Database Drivers

Database drivers persist jobs to a database table. They support:

- Job persistence across restarts
- Multiple workers
- Failed job tracking
- Job history and metrics

### MySQL Driver

```php
return [
    'driver' => 'mysql',
];
```

### PostgreSQL Driver

```php
return [
    'driver' => 'pgsql',
];
```

### SQLite Driver

```php
return [
    'driver' => 'sqlite',
];
```

See [Database Driver](database.md) for details.

---

## Driver Selection Guide

```
┌─────────────────────────────────────────────────────────────────┐
│                    Which Driver to Use?                          │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  Development?                                                    │
│      │                                                           │
│      ├── Yes ──▶ Use 'memory' driver                            │
│      │                                                           │
│      └── No ──▶ Production?                                     │
│                    │                                             │
│                    ├── MySQL ──▶ Use 'mysql' driver             │
│                    │                                             │
│                    ├── PostgreSQL ──▶ Use 'pgsql' driver        │
│                    │                                             │
│                    └── SQLite ──▶ Use 'sqlite' driver           │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Custom Drivers

You can create custom drivers by implementing `QueueDriverInterface`:

```php
use Lalaz\Queue\Contracts\QueueDriverInterface;

class RedisQueueDriver implements QueueDriverInterface
{
    public function add(
        string $jobClass,
        array $payload = [],
        string $queue = 'default',
        int $priority = 5,
        ?int $delay = null,
        array $options = [],
    ): bool {
        // Add job to Redis
    }
    
    public function process(?string $queue = null): void
    {
        // Process next job from Redis
    }
    
    // Implement other interface methods...
}
```

Register your custom driver:

```php
// In a service provider
$this->singleton(QueueDriverInterface::class, function () {
    return new RedisQueueDriver($redis);
});
```

---

## Driver Interface

All drivers implement `QueueDriverInterface`, which extends:

- `JobDispatcherInterface` - Add jobs to queue
- `JobProcessorInterface` - Process jobs
- `QueueStatsInterface` - Get queue statistics
- `FailedJobsInterface` - Handle failed jobs
- `QueueMaintenanceInterface` - Cleanup and maintenance

---

## Next Steps

- [InMemory Driver](inmemory.md) - Development driver details
- [Database Driver](database.md) - Production driver details
- [Creating Jobs](../jobs/creating-jobs.md) - Define custom jobs
