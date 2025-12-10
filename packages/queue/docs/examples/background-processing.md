# Background Processing Example

A complete example of background job processing in a web application.

---

## Overview

This example demonstrates:

- Email notification jobs
- Report generation
- File upload processing
- API synchronization
- Scheduled maintenance

---

## Project Structure

```
app/
├── Jobs/
│   ├── SendWelcomeEmailJob.php
│   ├── GenerateReportJob.php
│   ├── ProcessUploadJob.php
│   └── SyncExternalApiJob.php
├── Controllers/
│   └── UserController.php
├── Services/
│   └── ReportService.php
└── Console/
    └── QueueWorkerCommand.php
```

---

## Job Definitions

### SendWelcomeEmailJob

```php
<?php

namespace App\Jobs;

use Lalaz\Queue\Job;
use App\Models\User;
use App\Services\MailerService;

class SendWelcomeEmailJob extends Job
{
    protected string $queue = 'emails';
    protected int $priority = 3;
    protected int $maxAttempts = 3;
    protected int $timeout = 60;
    
    public function handle(array $payload): void
    {
        $user = User::find($payload['user_id']);
        
        if (!$user) {
            throw new \RuntimeException("User {$payload['user_id']} not found");
        }
        
        $mailer = resolve(MailerService::class);
        
        $mailer->send(
            to: $user->email,
            template: 'welcome',
            data: [
                'name' => $user->name,
                'login_url' => config('app.url') . '/login',
            ]
        );
    }
}
```

### GenerateReportJob

```php
<?php

namespace App\Jobs;

use Lalaz\Queue\Job;
use App\Services\ReportGenerator;
use App\Services\Storage;

class GenerateReportJob extends Job
{
    protected string $queue = 'reports';
    protected int $priority = 5;
    protected int $maxAttempts = 1; // Don't retry - expensive operation
    protected int $timeout = 1800; // 30 minutes
    
    public function handle(array $payload): void
    {
        $type = $payload['type'];
        $userId = $payload['user_id'];
        $dateRange = $payload['date_range'];
        
        $generator = resolve(ReportGenerator::class);
        $storage = resolve(Storage::class);
        
        // Generate the report
        $report = $generator->generate($type, $dateRange);
        
        // Save to storage
        $filename = sprintf(
            'reports/%s_%s_%s.pdf',
            $type,
            $userId,
            date('Y-m-d_His')
        );
        
        $storage->put($filename, $report->content);
        
        // Notify user
        SendReportReadyEmailJob::dispatch([
            'user_id' => $userId,
            'report_url' => $storage->url($filename),
        ]);
    }
}
```

### ProcessUploadJob

```php
<?php

namespace App\Jobs;

use Lalaz\Queue\Job;
use App\Services\ImageProcessor;
use App\Services\Storage;

class ProcessUploadJob extends Job
{
    protected string $queue = 'uploads';
    protected int $priority = 4;
    protected int $maxAttempts = 3;
    protected int $timeout = 300;
    protected string $backoffStrategy = 'linear';
    protected int $retryDelay = 30;
    
    public function handle(array $payload): void
    {
        $uploadId = $payload['upload_id'];
        $filePath = $payload['file_path'];
        
        $processor = resolve(ImageProcessor::class);
        $storage = resolve(Storage::class);
        
        // Read the uploaded file
        $content = $storage->get($filePath);
        
        // Generate thumbnails
        $thumbnails = [
            'small' => $processor->resize($content, 150, 150),
            'medium' => $processor->resize($content, 300, 300),
            'large' => $processor->resize($content, 800, 600),
        ];
        
        // Save thumbnails
        $basePath = pathinfo($filePath, PATHINFO_DIRNAME);
        $filename = pathinfo($filePath, PATHINFO_FILENAME);
        
        foreach ($thumbnails as $size => $data) {
            $storage->put("{$basePath}/{$filename}_{$size}.jpg", $data);
        }
        
        // Update upload record
        Upload::where('id', $uploadId)->update([
            'status' => 'processed',
            'processed_at' => now(),
        ]);
    }
}
```

### SyncExternalApiJob

```php
<?php

namespace App\Jobs;

use Lalaz\Queue\Job;
use App\Services\ExternalApiClient;

class SyncExternalApiJob extends Job
{
    protected string $queue = 'sync';
    protected int $priority = 6;
    protected int $maxAttempts = 5;
    protected int $timeout = 120;
    protected string $backoffStrategy = 'exponential';
    protected int $retryDelay = 60;
    protected array $tags = ['external', 'api', 'sync'];
    
    public function handle(array $payload): void
    {
        $recordId = $payload['record_id'];
        $recordType = $payload['record_type'];
        
        $client = resolve(ExternalApiClient::class);
        
        $record = $this->getRecord($recordType, $recordId);
        
        $response = $client->sync($recordType, $record->toArray());
        
        if (!$response->successful()) {
            throw new \RuntimeException(
                "API sync failed: {$response->status()} - {$response->body()}"
            );
        }
        
        // Update sync status
        $record->update([
            'last_synced_at' => now(),
            'external_id' => $response->json('id'),
        ]);
    }
    
    private function getRecord(string $type, int $id): Model
    {
        return match ($type) {
            'user' => User::findOrFail($id),
            'order' => Order::findOrFail($id),
            'product' => Product::findOrFail($id),
            default => throw new \InvalidArgumentException("Unknown type: {$type}"),
        };
    }
}
```

---

## Controller Integration

### UserController

```php
<?php

namespace App\Controllers;

use App\Jobs\SendWelcomeEmailJob;
use App\Jobs\SyncExternalApiJob;
use App\Models\User;

class UserController
{
    public function store(Request $request): Response
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8',
        ]);
        
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => password_hash($validated['password'], PASSWORD_ARGON2ID),
        ]);
        
        // Queue welcome email (5 minute delay)
        SendWelcomeEmailJob::later(300)->dispatch([
            'user_id' => $user->id,
        ]);
        
        // Queue external sync
        SyncExternalApiJob::dispatch([
            'record_id' => $user->id,
            'record_type' => 'user',
        ]);
        
        return response()->json($user, 201);
    }
}
```

---

## Service Integration

### ReportService

```php
<?php

namespace App\Services;

use App\Jobs\GenerateReportJob;

class ReportService
{
    public function requestReport(int $userId, string $type, array $dateRange): void
    {
        // Queue the report generation
        GenerateReportJob::onQueue('reports')
            ->priority(5)
            ->dispatch([
                'user_id' => $userId,
                'type' => $type,
                'date_range' => $dateRange,
            ]);
    }
    
    public function requestUrgentReport(int $userId, string $type): void
    {
        // High priority, process immediately
        GenerateReportJob::onQueue('reports')
            ->priority(1)
            ->dispatch([
                'user_id' => $userId,
                'type' => $type,
                'date_range' => ['start' => today(), 'end' => today()],
            ]);
    }
}
```

---

## Queue Worker

### Supervisor Configuration

```ini
[program:queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/app/lalaz queue:work --queue=emails,reports,uploads,sync
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/log/queue-worker.log
stopwaitsecs=60
```

### Worker Script

```php
<?php

// bin/worker.php

require __DIR__ . '/../vendor/autoload.php';

use Lalaz\Queue\Jobs;
use Lalaz\Queue\QueueManager;

$manager = resolve(QueueManager::class);

echo "Queue worker started\n";

$processed = 0;
$startTime = time();
$maxRuntime = 3600; // 1 hour

while (true) {
    // Process batch
    $stats = Jobs::batch(10, null, 55);
    $processed += $stats['processed'];
    
    // Log progress
    if ($stats['processed'] > 0) {
        echo sprintf(
            "[%s] Processed: %d, Success: %d, Failed: %d\n",
            date('Y-m-d H:i:s'),
            $stats['processed'],
            $stats['successful'],
            $stats['failed']
        );
    }
    
    // No jobs, wait
    if ($stats['processed'] === 0) {
        sleep(5);
    }
    
    // Check memory
    if (memory_get_usage(true) > 128 * 1024 * 1024) {
        echo "Memory limit reached, restarting...\n";
        break;
    }
    
    // Check runtime
    if (time() - $startTime > $maxRuntime) {
        echo "Max runtime reached, restarting...\n";
        break;
    }
}

echo "Total processed: {$processed}\n";
```

---

## Monitoring

### Health Check Endpoint

```php
<?php

// routes/api.php

Route::get('/queue/health', function () {
    $manager = resolve(QueueManager::class);
    $stats = $manager->getStats();
    
    $healthy = $stats['processing'] < 100 && $stats['failed'] < 50;
    
    return response()->json([
        'status' => $healthy ? 'healthy' : 'degraded',
        'stats' => $stats,
    ], $healthy ? 200 : 503);
});
```

### Dashboard Stats

```php
<?php

class QueueDashboardController
{
    public function index(): Response
    {
        $manager = resolve(QueueManager::class);
        
        return view('admin.queue.index', [
            'stats' => [
                'all' => $manager->getStats(),
                'emails' => $manager->getStats('emails'),
                'reports' => $manager->getStats('reports'),
                'uploads' => $manager->getStats('uploads'),
                'sync' => $manager->getStats('sync'),
            ],
            'failedJobs' => $manager->getFailedJobs(10),
        ]);
    }
}
```

---

## Scheduled Maintenance

```php
<?php

// Scheduled task (cron)

use Lalaz\Queue\QueueManager;

$manager = resolve(QueueManager::class);

// Cleanup old jobs (older than 7 days)
$deleted = $manager->purgeOldJobs(7);
echo "Purged {$deleted} old jobs\n";

// Release stuck jobs
$driver = $manager->getDriver();
$released = $driver->releaseStuckJobs();
echo "Released {$released} stuck jobs\n";
```

---

## Next Steps

- [Creating Jobs](../jobs/creating-jobs.md) - Define custom jobs
- [Failed Jobs](../failed-jobs.md) - Handle failures
- [Retry Strategies](../retry-strategies.md) - Configure retries
