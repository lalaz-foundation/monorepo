# Quick Start

Get up and running with the Queue package in 5 minutes.

---

## Prerequisites

- Lalaz Framework installed
- Queue package installed (`composer require lalaz/queue`)
- Configuration file created

---

## Step 1: Enable the Queue

Update your `config/queue.php`:

```php
<?php

return [
    'enabled' => true,
    'driver' => 'memory', // Use 'mysql' for production
];
```

---

## Step 2: Create a Job

Create a job class in `app/Jobs/`:

```php
<?php

namespace App\Jobs;

use Lalaz\Queue\Job;

class SendWelcomeEmailJob extends Job
{
    protected string $queue = 'emails';
    protected int $maxAttempts = 3;
    
    public function handle(array $payload): void
    {
        $email = $payload['email'];
        $name = $payload['name'];
        
        // Send welcome email
        mail($email, 'Welcome!', "Hello {$name}, welcome to our app!");
    }
}
```

---

## Step 3: Dispatch the Job

Dispatch from a controller or service:

```php
<?php

namespace App\Controllers;

use App\Jobs\SendWelcomeEmailJob;

class UserController
{
    public function register(): void
    {
        // Create user...
        
        // Dispatch welcome email job
        SendWelcomeEmailJob::dispatch([
            'email' => 'user@example.com',
            'name' => 'John Doe',
        ]);
        
        // Response sent immediately, email sent in background
    }
}
```

---

## Step 4: Process Jobs

### Using Jobs Facade

```php
use Lalaz\Queue\Jobs;

// Process all queues
Jobs::run();

// Process specific queue
Jobs::run('emails');

// Process batch
Jobs::batch(10, 'emails');
```

### Using CLI

```bash
# Process all jobs
php lalaz queue:work

# Process specific queue
php lalaz queue:work --queue=emails

# Process in batches
php lalaz queue:work --batch=10
```

---

## Step 5: Monitor Jobs

Check queue statistics:

```php
use Lalaz\Queue\QueueManager;

$manager = resolve(QueueManager::class);
$stats = $manager->getStats();

echo "Pending: " . $stats['pending'] . "\n";
echo "Processing: " . $stats['processing'] . "\n";
echo "Completed: " . $stats['completed'] . "\n";
echo "Failed: " . $stats['failed'] . "\n";
```

---

## Common Patterns

### Dispatch with Priority

```php
// High priority job (lower number = higher priority)
SendWelcomeEmailJob::withPriority(1)->dispatch([
    'email' => 'vip@example.com',
]);
```

### Dispatch with Delay

```php
// Send email in 5 minutes
SendWelcomeEmailJob::later(300)->dispatch([
    'email' => 'user@example.com',
]);
```

### Dispatch to Specific Queue

```php
SendWelcomeEmailJob::onQueue('high-priority')->dispatch([
    'email' => 'user@example.com',
]);
```

### Dispatch Synchronously

```php
// Execute immediately without queue
SendWelcomeEmailJob::dispatchSync([
    'email' => 'user@example.com',
]);
```

---

## What's Next?

- [Core Concepts](concepts.md) - Understand jobs, queues, and drivers
- [Creating Jobs](jobs/creating-jobs.md) - Advanced job configuration
- [Drivers](drivers/index.md) - Configure production drivers
- [Failed Jobs](failed-jobs.md) - Handle job failures
