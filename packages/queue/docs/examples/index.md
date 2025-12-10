# Examples Overview

Practical examples of using the Queue package.

---

## Available Examples

### [Background Processing](background-processing.md)

Complete example of background job processing including:

- Email notification jobs
- Report generation
- File processing
- API synchronization
- Scheduled cleanup

---

## Quick Examples

### Basic Job

```php
use Lalaz\Queue\Job;

class SendEmailJob extends Job
{
    public function handle(array $payload): void
    {
        $email = $payload['email'];
        $subject = $payload['subject'];
        
        mail($email, $subject, $payload['body']);
    }
}

// Dispatch
SendEmailJob::dispatch([
    'email' => 'user@example.com',
    'subject' => 'Hello!',
    'body' => 'Welcome to our app.',
]);
```

### Priority Job

```php
class UrgentNotificationJob extends Job
{
    protected string $queue = 'notifications';
    protected int $priority = 0; // Highest priority
    
    public function handle(array $payload): void
    {
        // Send urgent notification...
    }
}

UrgentNotificationJob::dispatch(['message' => 'Server down!']);
```

### Delayed Job

```php
class SendReminderJob extends Job
{
    protected string $queue = 'reminders';
    
    public function handle(array $payload): void
    {
        // Send reminder email...
    }
}

// Send in 1 hour
SendReminderJob::later(3600)->dispatch([
    'user_id' => 123,
    'message' => 'Don\'t forget your appointment!',
]);
```

### Job with Retries

```php
class SyncWithApiJob extends Job
{
    protected int $maxAttempts = 5;
    protected string $backoffStrategy = 'exponential';
    protected int $retryDelay = 30;
    
    public function handle(array $payload): void
    {
        $response = Http::post('https://api.example.com/sync', $payload);
        
        if (!$response->successful()) {
            throw new \RuntimeException('API sync failed');
        }
    }
}
```

### Batch Processing

```php
use Lalaz\Queue\Jobs;

// Process 50 jobs from all queues
$stats = Jobs::batch(50);

echo "Processed: {$stats['processed']}\n";
echo "Successful: {$stats['successful']}\n";
echo "Failed: {$stats['failed']}\n";
```

### Queue Statistics

```php
use Lalaz\Queue\QueueManager;

$manager = resolve(QueueManager::class);
$stats = $manager->getStats('emails');

echo "Pending: {$stats['pending']}\n";
echo "Processing: {$stats['processing']}\n";
echo "Completed: {$stats['completed']}\n";
echo "Failed: {$stats['failed']}\n";
```

---

## Common Patterns

### From Controller

```php
class OrderController
{
    public function store(Request $request): Response
    {
        $order = Order::create($request->validated());
        
        // Queue order processing
        ProcessOrderJob::dispatch(['order_id' => $order->id]);
        
        // Queue notification
        SendOrderConfirmationJob::onQueue('emails')
            ->dispatch(['order_id' => $order->id]);
        
        return response()->json($order, 201);
    }
}
```

### From Service

```php
class UserService
{
    public function register(array $data): User
    {
        $user = User::create($data);
        
        // Welcome email (delayed by 5 minutes)
        SendWelcomeEmailJob::later(300)->dispatch([
            'user_id' => $user->id,
        ]);
        
        // Sync to mailing list
        SyncMailingListJob::dispatch([
            'email' => $user->email,
            'name' => $user->name,
        ]);
        
        return $user;
    }
}
```

### Worker Script

```php
#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

use Lalaz\Queue\Jobs;

echo "Starting queue worker...\n";

while (true) {
    $stats = Jobs::batch(10, null, 55);
    
    if ($stats['processed'] === 0) {
        // No jobs, wait before checking again
        sleep(5);
    }
    
    // Memory check
    if (memory_get_usage(true) > 128 * 1024 * 1024) {
        echo "Memory limit reached, restarting...\n";
        exit(0);
    }
}
```

---

## Next Steps

- [Background Processing](background-processing.md) - Complete example
- [Creating Jobs](../jobs/creating-jobs.md) - Job definition guide
- [Dispatching Jobs](../jobs/dispatching.md) - All dispatch options
