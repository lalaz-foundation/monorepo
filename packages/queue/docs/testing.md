# Testing

How to test jobs and queue functionality.

---

## Test Setup

The Queue package uses the `lalaz/testing` package for testing.

### Base Test Classes

```php
// Unit tests
use Lalaz\Queue\Tests\Common\QueueUnitTestCase;

// Integration tests
use Lalaz\Queue\Tests\Common\QueueIntegrationTestCase;
```

---

## Unit Testing Jobs

### Test Job Creation

```php
use Lalaz\Queue\Tests\Common\QueueUnitTestCase;

class SendEmailJobTest extends QueueUnitTestCase
{
    public function test_job_has_correct_properties(): void
    {
        $job = new SendEmailJob();
        
        $reflection = new ReflectionClass($job);
        
        $queueProperty = $reflection->getProperty('queue');
        $queueProperty->setAccessible(true);
        
        $this->assertEquals('emails', $queueProperty->getValue($job));
    }
}
```

### Test Job Dispatch

```php
public function test_job_can_be_dispatched(): void
{
    $mockDispatcher = $this->createMock(JobDispatcherInterface::class);
    $mockDispatcher->expects($this->once())
        ->method('add')
        ->with(
            SendEmailJob::class,
            ['email' => 'test@example.com'],
            'emails',
            $this->anything(),
            $this->anything(),
            $this->anything()
        )
        ->willReturn(true);
    
    Job::setTestDispatcher($mockDispatcher);
    
    $result = SendEmailJob::dispatch(['email' => 'test@example.com']);
    
    $this->assertTrue($result);
    
    Job::setTestDispatcher(null);
}
```

### Test Job Handle Method

```php
public function test_job_sends_email(): void
{
    $mailer = $this->createMock(Mailer::class);
    $mailer->expects($this->once())
        ->method('send')
        ->with('test@example.com', 'Welcome!', $this->anything());
    
    $this->container->singleton(Mailer::class, fn() => $mailer);
    
    $job = new SendEmailJob();
    $job->handle([
        'email' => 'test@example.com',
        'subject' => 'Welcome!',
    ]);
}
```

---

## Testing with InMemory Driver

Use the InMemory driver for fast, isolated tests:

```php
use Lalaz\Queue\Drivers\InMemoryQueueDriver;

class QueueTest extends QueueUnitTestCase
{
    private InMemoryQueueDriver $driver;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = new InMemoryQueueDriver();
    }
    
    public function test_job_is_added_to_queue(): void
    {
        $result = $this->driver->add(
            SendEmailJob::class,
            ['email' => 'test@example.com']
        );
        
        $this->assertTrue($result);
        
        $jobs = $this->driver->all();
        $this->assertCount(1, $jobs);
        $this->assertEquals(SendEmailJob::class, $jobs[0]['task']);
    }
    
    public function test_job_is_processed(): void
    {
        $this->driver->add(SendEmailJob::class, ['email' => 'test@example.com']);
        
        $this->driver->process();
        
        $stats = $this->driver->getStats();
        $this->assertEquals(1, $stats['completed']);
    }
}
```

---

## Testing QueueManager

```php
class QueueManagerTest extends QueueUnitTestCase
{
    public function test_manager_adds_job(): void
    {
        $driver = $this->createMock(QueueDriverInterface::class);
        $driver->expects($this->once())
            ->method('add')
            ->willReturn(true);
        
        $manager = new QueueManager($driver);
        
        $result = $manager->addJob(SendEmailJob::class, ['email' => 'test@example.com']);
        
        $this->assertTrue($result);
    }
    
    public function test_manager_gets_stats(): void
    {
        $driver = $this->createMock(QueueDriverInterface::class);
        $driver->expects($this->once())
            ->method('getStats')
            ->willReturn([
                'pending' => 5,
                'processing' => 1,
                'completed' => 10,
            ]);
        
        $manager = new QueueManager($driver);
        $stats = $manager->getStats();
        
        $this->assertEquals(5, $stats['pending']);
    }
}
```

---

## Testing Retry Logic

```php
use Lalaz\Queue\RetryStrategy;

class RetryStrategyTest extends QueueUnitTestCase
{
    public function test_exponential_backoff(): void
    {
        $delay1 = RetryStrategy::getDelayForAttempt('exponential', 60, 1);
        $delay2 = RetryStrategy::getDelayForAttempt('exponential', 60, 2);
        $delay3 = RetryStrategy::getDelayForAttempt('exponential', 60, 3);
        
        $this->assertEquals(60, $delay1);
        $this->assertEquals(120, $delay2);
        $this->assertEquals(240, $delay3);
    }
    
    public function test_jitter_adds_variation(): void
    {
        $delays = [];
        
        for ($i = 0; $i < 100; $i++) {
            $delays[] = RetryStrategy::addJitter(100, 0.1);
        }
        
        $this->assertGreaterThan(min($delays), max($delays));
        $this->assertGreaterThanOrEqual(90, min($delays));
        $this->assertLessThanOrEqual(110, max($delays));
    }
}
```

---

## Testing Failed Jobs

```php
class FailedJobsTest extends QueueUnitTestCase
{
    public function test_failed_job_is_stored(): void
    {
        $driver = new InMemoryQueueDriver();
        
        // Add a job that will fail
        $driver->add(FailingJob::class, ['should_fail' => true]);
        
        // Process it
        $driver->process();
        
        // Check it failed
        $failedJobs = $driver->getFailedJobs();
        $this->assertCount(1, $failedJobs);
    }
    
    public function test_failed_job_can_be_retried(): void
    {
        $driver = new InMemoryQueueDriver();
        
        // Setup a failed job
        $driver->add(FailingJob::class, []);
        $driver->process();
        
        // Retry it
        $result = $driver->retryFailedJob(0);
        
        $this->assertTrue($result);
    }
}
```

---

## Running Tests

```bash
# Run all tests
./vendor/bin/phpunit

# Run specific test file
./vendor/bin/phpunit tests/Unit/JobTest.php

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage/
```

---

## Test Helpers

The `QueueUnitTestCase` provides helper methods:

```php
// Create a fake job
$job = $this->fakeJob(['key' => 'value']);

// Create a failing job
$job = $this->failingJob(new RuntimeException('Test error'));

// Create mock driver
$driver = $this->createMockDriver();

// Create mock logger
$logger = $this->createMockLogger();

// Create configured queue manager
$manager = $this->createQueueManager($driver, $executor);
```

---

## Next Steps

- [API Reference](api-reference.md) - Full API documentation
- [Creating Jobs](jobs/creating-jobs.md) - Define job classes
- [Drivers](drivers/index.md) - Driver documentation
