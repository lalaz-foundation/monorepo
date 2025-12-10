# InMemory Driver

The InMemory driver stores jobs in a PHP array. Ideal for development and testing.

---

## Configuration

```php
// config/queue.php
return [
    'enabled' => true,
    'driver' => 'memory',
];
```

---

## How It Works

Jobs are stored in an array and processed sequentially:

```php
use Lalaz\Queue\Drivers\InMemoryQueueDriver;

$driver = new InMemoryQueueDriver();

// Add a job
$driver->add(SendEmailJob::class, ['email' => 'test@example.com']);

// Process the job
$driver->process();
```

---

## Features

### Add Jobs

```php
$driver->add(
    jobClass: SendEmailJob::class,
    payload: ['email' => 'test@example.com'],
    queue: 'emails',
    priority: 5,
    delay: 60,
    options: ['max_attempts' => 3]
);
```

### Process Jobs

```php
// Process one job from any queue
$driver->process();

// Process one job from specific queue
$driver->process('emails');
```

### Batch Processing

```php
$stats = $driver->processBatch(
    batchSize: 10,
    queue: 'emails',
    maxExecutionTime: 55
);

// Returns:
// [
//     'processed' => 10,
//     'successful' => 8,
//     'failed' => 2,
//     'execution_time' => 12,
// ]
```

### Get Statistics

```php
$stats = $driver->getStats();
// [
//     'pending' => 5,
//     'processing' => 1,
//     'completed' => 10,
//     'failed' => 2,
//     'delayed' => 3,
// ]
```

### View All Jobs

```php
$jobs = $driver->all();
```

### Cleanup

```php
// Remove completed and failed jobs
$removed = $driver->cleanup();
```

---

## Limitations

| Feature | Supported |
|---------|-----------|
| Persistence | ❌ No |
| Multiple Workers | ❌ No |
| Job History | ❌ No |
| Failed Job Storage | ⚠️ Limited |
| Delayed Jobs | ✅ Yes |
| Priorities | ✅ Yes |

---

## Use Cases

### Development

```php
// config/queue.php
return [
    'enabled' => env('QUEUE_ENABLED', false),
    'driver' => env('QUEUE_DRIVER', 'memory'),
];
```

### Unit Testing

```php
use Lalaz\Queue\Drivers\InMemoryQueueDriver;

class JobTest extends TestCase
{
    public function test_job_is_dispatched(): void
    {
        $driver = new InMemoryQueueDriver();
        
        $driver->add(SendEmailJob::class, ['email' => 'test@example.com']);
        
        $jobs = $driver->all();
        $this->assertCount(1, $jobs);
        $this->assertEquals(SendEmailJob::class, $jobs[0]['task']);
    }
}
```

---

## Job Lifecycle

```
┌─────────────────────────────────────────────────────────────────┐
│                    InMemory Job Lifecycle                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  add() ──▶ Array Entry ──▶ pending                              │
│                                │                                 │
│                                ▼                                 │
│            process() ──▶ processing                             │
│                                │                                 │
│                    ┌───────────┴───────────┐                    │
│                    ▼                       ▼                    │
│              completed                  failed                   │
│                                                                  │
│  cleanup() removes completed and failed jobs                    │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Next Steps

- [Database Driver](database.md) - Production-ready driver
- [Creating Jobs](../jobs/creating-jobs.md) - Define custom jobs
