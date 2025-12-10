# Creating Jobs

How to define custom job classes.

---

## Basic Job

Create a job by extending the `Job` class:

```php
<?php

namespace App\Jobs;

use Lalaz\Queue\Job;

class SendEmailJob extends Job
{
    public function handle(array $payload): void
    {
        $email = $payload['email'];
        $subject = $payload['subject'];
        $body = $payload['body'];
        
        mail($email, $subject, $body);
    }
}
```

---

## Job Properties

Configure job behavior with protected properties:

```php
<?php

namespace App\Jobs;

use Lalaz\Queue\Job;

class ProcessOrderJob extends Job
{
    /**
     * Queue name for this job.
     */
    protected string $queue = 'orders';
    
    /**
     * Priority (0-10, lower = higher priority).
     */
    protected int $priority = 3;
    
    /**
     * Maximum retry attempts.
     */
    protected int $maxAttempts = 5;
    
    /**
     * Execution timeout in seconds.
     */
    protected int $timeout = 600;
    
    /**
     * Retry delay strategy (exponential, linear, fixed).
     */
    protected string $backoffStrategy = 'exponential';
    
    /**
     * Base delay between retries in seconds.
     */
    protected int $retryDelay = 30;
    
    /**
     * Tags for categorization.
     */
    protected array $tags = ['order', 'critical'];
    
    public function handle(array $payload): void
    {
        // Process order...
    }
}
```

---

## Property Reference

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `$queue` | string | `'default'` | Target queue name |
| `$priority` | int | `5` | Priority (0=highest, 10=lowest) |
| `$maxAttempts` | int | `3` | Maximum retry attempts |
| `$timeout` | int | `300` | Execution timeout in seconds |
| `$backoffStrategy` | string | `'exponential'` | Retry delay strategy |
| `$retryDelay` | int | `60` | Base delay between retries |
| `$tags` | array | `[]` | Tags for organization |

---

## The handle() Method

The `handle()` method receives the payload and executes the job:

```php
public function handle(array $payload): void
{
    // $payload contains data passed during dispatch
    $userId = $payload['user_id'];
    $action = $payload['action'];
    
    // Perform the work
    $user = User::find($userId);
    $user->performAction($action);
}
```

### Payload Guidelines

- Keep payloads small (IDs, not full objects)
- Use serializable data types
- Include only what's needed

```php
// Good: Pass IDs
SendEmailJob::dispatch([
    'user_id' => $user->id,
    'template' => 'welcome',
]);

// Avoid: Pass full objects
SendEmailJob::dispatch([
    'user' => $user, // Bad - may not serialize
]);
```

---

## Error Handling

Throw exceptions to trigger retry logic:

```php
public function handle(array $payload): void
{
    $response = $this->callApi($payload['endpoint']);
    
    if ($response->failed()) {
        throw new \RuntimeException('API call failed: ' . $response->error());
    }
    
    // Process success response...
}
```

The job will be retried according to `$maxAttempts` and `$backoffStrategy`.

---

## Dependencies

Use dependency injection in the constructor:

```php
<?php

namespace App\Jobs;

use Lalaz\Queue\Job;
use App\Services\EmailService;

class SendEmailJob extends Job
{
    private EmailService $emailService;
    
    public function __construct()
    {
        $this->emailService = resolve(EmailService::class);
    }
    
    public function handle(array $payload): void
    {
        $this->emailService->send(
            to: $payload['email'],
            subject: $payload['subject'],
            body: $payload['body']
        );
    }
}
```

---

## Job Examples

### Email Job

```php
class SendWelcomeEmailJob extends Job
{
    protected string $queue = 'emails';
    protected int $maxAttempts = 3;
    
    public function handle(array $payload): void
    {
        $user = User::find($payload['user_id']);
        
        $mailer = resolve(Mailer::class);
        $mailer->send('welcome', $user->email, [
            'name' => $user->name,
        ]);
    }
}
```

### Report Generation Job

```php
class GenerateReportJob extends Job
{
    protected string $queue = 'reports';
    protected int $timeout = 1800; // 30 minutes
    protected int $maxAttempts = 1; // Don't retry
    
    public function handle(array $payload): void
    {
        $reportType = $payload['type'];
        $dateRange = $payload['date_range'];
        
        $generator = resolve(ReportGenerator::class);
        $report = $generator->generate($reportType, $dateRange);
        
        $storage = resolve(Storage::class);
        $storage->put("reports/{$report->id}.pdf", $report->content);
    }
}
```

### API Sync Job

```php
class SyncWithExternalApiJob extends Job
{
    protected string $queue = 'sync';
    protected int $maxAttempts = 5;
    protected string $backoffStrategy = 'exponential';
    protected int $retryDelay = 30;
    
    public function handle(array $payload): void
    {
        $client = resolve(ApiClient::class);
        
        $response = $client->sync($payload['data']);
        
        if (!$response->successful()) {
            throw new \RuntimeException('Sync failed');
        }
    }
}
```

---

## Next Steps

- [Dispatching Jobs](dispatching.md) - All dispatch options
- [Failed Jobs](../failed-jobs.md) - Handle failures
- [Retry Strategies](../retry-strategies.md) - Configure retries
