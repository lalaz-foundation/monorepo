# Lalaz Queue

A robust queue and background job processing system for PHP 8.3+ with support for multiple drivers, automatic retries, job priorities, and failed job handling.

## Features

- **Multiple Drivers**: InMemory for development, Database (MySQL/PostgreSQL/SQLite) for production
- **Job Priorities**: Process important jobs first (0-10 scale)
- **Delayed Jobs**: Schedule jobs to run at a specific time
- **Automatic Retries**: Configurable retry strategies with exponential backoff
- **Failed Job Handling**: Dead letter queue with retry capabilities
- **Batch Processing**: Process multiple jobs efficiently with timeout control
- **Job Metrics**: Track execution time and memory usage
- **Fluent API**: Chain methods for clean job configuration
- **CLI Commands**: Built-in commands for queue management
- **Service Provider**: Easy integration with Lalaz framework
- **Type-Safe**: Full PHP 8.3+ type declarations

## Installation

```bash
composer require lalaz/queue
```

## Quick Start

### Creating a Job

```php
use Lalaz\Queue\Job;

class SendEmailJob extends Job
{
    protected string $queue = 'emails';
    protected int $priority = 5;
    protected int $maxAttempts = 3;
    protected int $timeout = 300;
    
    public function handle(array $payload): void
    {
        $email = $payload['email'];
        $subject = $payload['subject'];
        
        // Send the email...
        mail($email, $subject, $payload['body']);
    }
}
```

### Dispatching Jobs

```php
// Simple dispatch
SendEmailJob::dispatch([
    'email' => 'user@example.com',
    'subject' => 'Welcome!',
    'body' => 'Hello from Lalaz!',
]);

// Dispatch synchronously (no queue)
SendEmailJob::dispatchSync(['email' => 'user@example.com']);

// With fluent options
SendEmailJob::onQueue('high-priority')
    ->priority(9)
    ->delay(60)
    ->maxAttempts(5)
    ->dispatch(['email' => 'user@example.com']);
```

## Job Configuration

### Job Properties

```php
class ProcessOrderJob extends Job
{
    // Queue name (default: 'default')
    protected string $queue = 'orders';
    
    // Priority 0-10, lower = higher priority (default: 5)
    protected int $priority = 3;
    
    // Maximum retry attempts (default: 3)
    protected int $maxAttempts = 5;
    
    // Job timeout in seconds (default: 300)
    protected int $timeout = 600;
    
    // Backoff strategy: 'exponential', 'linear', 'fixed'
    protected string $backoffStrategy = 'exponential';
    
    // Base retry delay in seconds (default: 60)
    protected int $retryDelay = 30;
    
    // Tags for filtering/monitoring
    protected array $tags = ['orders', 'critical'];
    
    public function handle(array $payload): void
    {
        // Process the order...
    }
}
```

### Fluent Dispatch API

```php
// Using PendingDispatch
SendEmailJob::onQueue('emails')
    ->priority(8)
    ->delay(120)           // Delay 2 minutes
    ->maxAttempts(5)
    ->timeout(600)
    ->backoff('linear')
    ->retryAfter(30)
    ->tags(['email', 'notification'])
    ->dispatch($payload);

// Or create pending dispatch directly
use Lalaz\Queue\PendingDispatch;

$dispatch = new PendingDispatch(SendEmailJob::class, 'emails');
$dispatch->priority(8)->delay(60)->dispatch($payload);
```

## Queue Manager

### Basic Usage

```php
use Lalaz\Queue\QueueManager;

// Add a job to the queue
$manager->addJob(
    jobClass: SendEmailJob::class,
    payload: ['email' => 'user@example.com'],
    queue: 'emails',
    priority: 5,
    delay: null,
    options: ['max_attempts' => 3]
);

// Process jobs
$manager->process('emails');

// Process batch with timeout
$results = $manager->processBatch(
    batchSize: 10,
    queue: 'default',
    maxExecutionTime: 55
);
```

### Queue Statistics

```php
$stats = $manager->getStats('emails');
// [
//     'pending' => 42,
//     'processing' => 2,
//     'completed' => 1000,
//     'failed' => 5
// ]
```

## Drivers

### InMemory Driver (Development)

```php
use Lalaz\Queue\Drivers\InMemoryQueueDriver;

$driver = new InMemoryQueueDriver();
$manager = new QueueManager($driver);
```

### Database Drivers (Production)

```php
use Lalaz\Queue\Drivers\MySQLQueueDriver;
use Lalaz\Queue\Drivers\PostgresQueueDriver;
use Lalaz\Queue\Drivers\SQLiteQueueDriver;

// MySQL
$driver = new MySQLQueueDriver($connection, $jobResolver);

// PostgreSQL
$driver = new PostgresQueueDriver($connection, $jobResolver);

// SQLite
$driver = new SQLiteQueueDriver($connection, $jobResolver);
```

### Configuration

```php
// config/queue.php
return [
    'enabled' => true,
    'driver' => 'mysql', // 'memory', 'mysql', 'pgsql', 'sqlite'
    
    'tables' => [
        'jobs' => 'jobs',
        'failed_jobs' => 'failed_jobs',
        'job_logs' => 'job_logs',
    ],
    
    'job_timeout' => 300,
    'max_attempts' => 3,
    'retry_delay' => 60,
];
```

## Failed Jobs

### Handling Failed Jobs

```php
// Get failed jobs
$failed = $manager->getFailedJobs(limit: 50, offset: 0);

// Get specific failed job
$job = $manager->getFailedJob($id);

// Retry a failed job
$manager->retryFailedJob($id);

// Retry all failed jobs in queue
$count = $manager->retryAllFailedJobs('emails');

// Purge failed jobs
$deleted = $manager->purgeFailedJobs('emails');
```

### Retry Strategies

```php
use Lalaz\Queue\RetryStrategy;

// Exponential backoff: 60s, 120s, 240s, 480s...
$strategy = RetryStrategy::exponential(baseDelay: 60, maxDelay: 3600);

// Linear backoff: 60s, 120s, 180s, 240s...
$strategy = RetryStrategy::linear(baseDelay: 60, increment: 60);

// Fixed delay: always 60s
$strategy = RetryStrategy::fixed(delay: 60);

// Calculate next retry delay
$delay = $strategy->nextDelay(attempt: 3);
```

## Job Executor

```php
use Lalaz\Queue\JobExecutor;
use Lalaz\Queue\JobResolver;

$resolver = new JobResolver();
$executor = new JobExecutor($resolver);

// Execute a job
$result = $executor->execute($jobData);

// Execute synchronously
$success = $executor->executeSync(SendEmailJob::class, $payload);
```

## Job Runner (CLI Worker)

```php
use Lalaz\Queue\JobRunner;

$runner = new JobRunner($manager);

// Run worker
$runner->run(
    queue: 'default',
    sleep: 3,           // Sleep between empty polls
    maxJobs: 100,       // Max jobs before restart
    maxTime: 3600,      // Max runtime in seconds
    stopOnEmpty: false  // Stop when queue is empty
);
```

## Service Provider

```php
use Lalaz\Queue\QueueServiceProvider;

$provider = new QueueServiceProvider($container);
$provider->register();

// Access from container
$queue = $container->get(QueueManager::class);
```

## CLI Commands

```bash
# Process queue jobs
php lalaz queue:work --queue=default --sleep=3

# Retry failed jobs
php lalaz queue:retry --id=123
php lalaz queue:retry --all --queue=emails

# List failed jobs
php lalaz queue:failed --limit=50

# Flush failed jobs
php lalaz queue:flush-failed --queue=emails

# Queue statistics
php lalaz queue:stats --queue=default

# Maintenance (purge old jobs)
php lalaz queue:maintain --days=7
```

## Database Migration

Run the included migration to create queue tables:

```php
use Lalaz\Queue\Migrations\CreateQueueTables;

$migration = new CreateQueueTables($connection);
$migration->up();
```

Creates tables:
- `jobs` - Pending and processing jobs
- `failed_jobs` - Failed jobs for retry
- `job_logs` - Job execution logs

## Testing

### Using Test Dispatcher

```php
use Lalaz\Queue\Job;
use Lalaz\Queue\Contracts\JobDispatcherInterface;

class FakeDispatcher implements JobDispatcherInterface
{
    public array $dispatched = [];
    
    public function add(
        string $jobClass,
        array $payload,
        string $queue,
        int $priority,
        ?int $delay,
        array $options
    ): bool {
        $this->dispatched[] = compact('jobClass', 'payload');
        return true;
    }
}

// In tests
$dispatcher = new FakeDispatcher();
Job::setTestDispatcher($dispatcher);

SendEmailJob::dispatch(['email' => 'test@example.com']);

$this->assertCount(1, $dispatcher->dispatched);
$this->assertEquals(SendEmailJob::class, $dispatcher->dispatched[0]['jobClass']);

// Clean up
Job::setTestDispatcher(null);
```

### Using InMemory Driver

```php
use Lalaz\Queue\Drivers\InMemoryQueueDriver;
use Lalaz\Queue\QueueManager;

$driver = new InMemoryQueueDriver();
$manager = new QueueManager($driver);

// Dispatch job
$manager->addJob(SendEmailJob::class, $payload);

// Verify job was queued
$stats = $manager->getStats();
$this->assertEquals(1, $stats['pending']);
```

## Running Tests

```bash
# Run all tests
composer test

# Run with coverage
composer test:coverage

# Run specific test file
./vendor/bin/phpunit tests/Unit/QueueManagerTest.php
```

## Requirements

- PHP 8.3 or higher
- PDO extension (for database drivers)
- lalaz/database (for database drivers)

## Documentation

- [Installation](docs/installation.md) - Setup guide
- [Quick Start](docs/quick-start.md) - Get started in 5 minutes
- [Core Concepts](docs/concepts.md) - Architecture overview
- [Drivers](docs/drivers/index.md) - Available drivers
- [Creating Jobs](docs/jobs/creating-jobs.md) - Job definition
- [Dispatching Jobs](docs/jobs/dispatching.md) - Dispatch options
- [Failed Jobs](docs/failed-jobs.md) - Error handling
- [Retry Strategies](docs/retry-strategies.md) - Backoff configuration
- [Testing](docs/testing.md) - Testing guide
- [API Reference](docs/api-reference.md) - Complete API

## License

MIT License. See [LICENSE](LICENSE) for details.
