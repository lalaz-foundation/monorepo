# Dispatching Jobs

All the ways to dispatch jobs to the queue.

---

## Basic Dispatch

The simplest way to dispatch a job:

```php
SendEmailJob::dispatch(['email' => 'user@example.com']);
```

---

## Dispatch Methods

### dispatch()

Queues the job for asynchronous processing:

```php
SendEmailJob::dispatch([
    'email' => 'user@example.com',
    'subject' => 'Welcome!',
]);
```

### dispatchSync()

Executes the job immediately (bypasses queue):

```php
SendEmailJob::dispatchSync([
    'email' => 'user@example.com',
]);
```

---

## Fluent Dispatch API

Use the fluent API for more control:

### onQueue()

Specify the target queue:

```php
SendEmailJob::onQueue('emails')->dispatch([
    'email' => 'user@example.com',
]);
```

### withPriority()

Set job priority (0-10, lower = higher):

```php
SendEmailJob::withPriority(1)->dispatch([
    'email' => 'vip@example.com',
]);
```

### later()

Delay execution by seconds:

```php
// Execute in 5 minutes
SendEmailJob::later(300)->dispatch([
    'email' => 'user@example.com',
]);
```

---

## Chaining Options

Chain multiple options:

```php
SendEmailJob::onQueue('emails')
    ->priority(2)
    ->delay(60)
    ->maxAttempts(5)
    ->timeout(120)
    ->backoff('exponential')
    ->retryAfter(30)
    ->tags(['email', 'welcome'])
    ->dispatch([
        'email' => 'user@example.com',
    ]);
```

---

## PendingDispatch Options

| Method | Description |
|--------|-------------|
| `onQueue(string)` | Set target queue |
| `priority(int)` | Set priority (0-10) |
| `delay(int)` | Delay in seconds |
| `maxAttempts(int)` | Max retry attempts |
| `timeout(int)` | Execution timeout |
| `backoff(string)` | Backoff strategy |
| `retryAfter(int)` | Base retry delay |
| `tags(array)` | Add tags |
| `withOptions(array)` | Set multiple options |
| `dispatch(array)` | Execute dispatch |

---

## Using QueueManager

Dispatch directly through QueueManager:

```php
use Lalaz\Queue\QueueManager;

$manager = resolve(QueueManager::class);

$manager->addJob(
    jobClass: SendEmailJob::class,
    payload: ['email' => 'user@example.com'],
    queue: 'emails',
    priority: 5,
    delay: null,
    options: [
        'max_attempts' => 3,
        'timeout' => 300,
    ]
);
```

---

## Conditional Dispatch

Dispatch based on conditions:

```php
// Only dispatch if queue is enabled
if (QueueManager::isEnabled()) {
    SendEmailJob::dispatch(['email' => $email]);
} else {
    // Execute synchronously
    SendEmailJob::dispatchSync(['email' => $email]);
}
```

---

## Batch Dispatch

Dispatch multiple jobs:

```php
$users = User::all();

foreach ($users as $user) {
    SendNewsletterJob::dispatch([
        'user_id' => $user->id,
    ]);
}
```

---

## Queue Selection

### By Job Property

```php
class ImportantEmailJob extends Job
{
    protected string $queue = 'high-priority';
}

ImportantEmailJob::dispatch($payload); // Goes to 'high-priority'
```

### At Dispatch Time

```php
ImportantEmailJob::onQueue('urgent')->dispatch($payload);
```

---

## Priority Guidelines

| Priority | Use Case |
|----------|----------|
| 0-2 | Critical system jobs |
| 3-4 | Important user-facing jobs |
| 5 | Default priority |
| 6-7 | Background jobs |
| 8-10 | Low priority maintenance |

```php
// Critical
ProcessPaymentJob::withPriority(0)->dispatch($payload);

// Background
CleanupTempFilesJob::withPriority(9)->dispatch([]);
```

---

## Delay Examples

```php
// 1 minute delay
SendReminderJob::later(60)->dispatch($payload);

// 1 hour delay
GenerateReportJob::later(3600)->dispatch($payload);

// Specific time
$delay = strtotime('tomorrow 9:00') - time();
SendNewsletterJob::later($delay)->dispatch($payload);
```

---

## Dispatch Patterns

### From Controller

```php
class UserController
{
    public function store(Request $request): Response
    {
        $user = User::create($request->validated());
        
        SendWelcomeEmailJob::dispatch([
            'user_id' => $user->id,
        ]);
        
        return response()->json($user, 201);
    }
}
```

### From Service

```php
class OrderService
{
    public function complete(Order $order): void
    {
        $order->markComplete();
        
        SendOrderConfirmationJob::onQueue('emails')
            ->priority(3)
            ->dispatch(['order_id' => $order->id]);
        
        UpdateInventoryJob::dispatch(['order_id' => $order->id]);
    }
}
```

### From Event Listener

```php
class UserRegisteredListener
{
    public function handle(UserRegistered $event): void
    {
        SendWelcomeEmailJob::dispatch([
            'user_id' => $event->user->id,
        ]);
        
        SyncToMailingListJob::later(300)->dispatch([
            'email' => $event->user->email,
        ]);
    }
}
```

---

## Next Steps

- [Creating Jobs](creating-jobs.md) - Define job classes
- [Failed Jobs](../failed-jobs.md) - Handle failures
- [Retry Strategies](../retry-strategies.md) - Configure retries
