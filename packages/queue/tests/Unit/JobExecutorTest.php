<?php

declare(strict_types=1);

namespace Lalaz\Queue\Tests\Unit;

use Lalaz\Queue\Contracts\JobExecutorInterface;
use Lalaz\Queue\Contracts\JobInterface;
use Lalaz\Queue\Contracts\JobResolverInterface;
use Lalaz\Queue\Contracts\QueueLoggerInterface;
use Lalaz\Queue\JobExecutor;
use Lalaz\Queue\JobResolver;
use Lalaz\Queue\Tests\Common\QueueUnitTestCase;
use PHPUnit\Framework\Attributes\Test;

class ExecutorTestJob implements JobInterface
{
    public static ?array $lastPayload = null;
    public static int $handleCount = 0;

    public static function reset(): void
    {
        self::$lastPayload = null;
        self::$handleCount = 0;
    }

    public function handle(array $payload): void
    {
        self::$lastPayload = $payload;
        self::$handleCount++;
    }
}

class ExecutorFailingJob implements JobInterface
{
    public static int $attempts = 0;

    public static function reset(): void
    {
        self::$attempts = 0;
    }

    public function handle(array $payload): void
    {
        self::$attempts++;
        throw new \RuntimeException('Intentional failure');
    }
}

class JobExecutorTest extends QueueUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ExecutorTestJob::reset();
        ExecutorFailingJob::reset();
    }

    #[Test]
    public function implements_job_executor_interface(): void
    {
        $executor = new JobExecutor();

        $this->assertInstanceOf(JobExecutorInterface::class, $executor);
    }

    #[Test]
    public function execute_runs_job_handle_method(): void
    {
        $job = new ExecutorTestJob();

        $resolver = $this->createMock(JobResolverInterface::class);
        $resolver->method('resolve')->willReturn($job);

        $executor = new JobExecutor($resolver);

        $jobData = [
            'id' => 1,
            'queue' => 'default',
            'task' => ExecutorTestJob::class,
            'payload' => '{"key":"value"}',
            'attempts' => 0,
            'max_attempts' => 3,
        ];

        $executor->execute($jobData);

        $this->assertEquals(['key' => 'value'], ExecutorTestJob::$lastPayload);
        $this->assertSame(1, ExecutorTestJob::$handleCount);
    }

    #[Test]
    public function execute_throws_exception_when_handle_method_missing(): void
    {
        $executor = new JobExecutor();

        $jobData = [
            'id' => 1,
            'queue' => 'default',
            'task' => 'NonExistentJobClass',
            'payload' => '{}',
            'attempts' => 0,
            'max_attempts' => 3,
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("must implement handle() method");

        $executor->execute($jobData);
    }

    #[Test]
    public function execute_sync_runs_job_synchronously(): void
    {
        $job = new ExecutorTestJob();

        $resolver = $this->createMock(JobResolverInterface::class);
        $resolver->method('resolve')->willReturn($job);

        $executor = new JobExecutor($resolver);

        $result = $executor->executeSync(ExecutorTestJob::class, ['test' => 'data']);

        $this->assertTrue($result);
        $this->assertEquals(['test' => 'data'], ExecutorTestJob::$lastPayload);
    }

    #[Test]
    public function execute_sync_returns_false_for_non_existent_class(): void
    {
        $executor = new JobExecutor();

        // Redirect error_log to /dev/null
        $originalErrorLog = ini_get('error_log');
        ini_set('error_log', '/dev/null');

        try {
            $result = $executor->executeSync('NonExistentClass', []);
            $this->assertFalse($result);
        } finally {
            ini_set('error_log', $originalErrorLog);
        }
    }

    #[Test]
    public function execute_sync_returns_false_when_job_throws(): void
    {
        $job = new ExecutorFailingJob();

        $resolver = $this->createMock(JobResolverInterface::class);
        $resolver->method('resolve')->willReturn($job);

        $executor = new JobExecutor($resolver);

        // Redirect error_log to /dev/null
        $originalErrorLog = ini_get('error_log');
        ini_set('error_log', '/dev/null');

        try {
            $result = $executor->executeSync(ExecutorFailingJob::class, []);
            $this->assertFalse($result);
        } finally {
            ini_set('error_log', $originalErrorLog);
        }
    }

    #[Test]
    public function execute_sync_returns_false_when_resolver_throws(): void
    {
        $resolver = $this->createMock(JobResolverInterface::class);
        $resolver->method('resolve')->willThrowException(new \Exception('Resolution failed'));

        $executor = new JobExecutor($resolver);

        // Redirect error_log to /dev/null
        $originalErrorLog = ini_get('error_log');
        ini_set('error_log', '/dev/null');

        try {
            $result = $executor->executeSync(ExecutorTestJob::class, []);
            $this->assertFalse($result);
        } finally {
            ini_set('error_log', $originalErrorLog);
        }
    }

    #[Test]
    public function constructor_accepts_custom_resolver(): void
    {
        $resolver = $this->createMock(JobResolverInterface::class);

        $executor = new JobExecutor($resolver);

        $this->assertSame($resolver, $executor->getResolver());
    }

    #[Test]
    public function constructor_accepts_custom_logger(): void
    {
        $logger = $this->createMock(QueueLoggerInterface::class);

        $executor = new JobExecutor(null, $logger);

        $this->assertSame($logger, $executor->getLogger());
    }

    #[Test]
    public function constructor_creates_default_resolver_when_null(): void
    {
        $executor = new JobExecutor();

        $this->assertInstanceOf(JobResolverInterface::class, $executor->getResolver());
        $this->assertInstanceOf(JobResolver::class, $executor->getResolver());
    }

    #[Test]
    public function execute_logs_job_metrics_when_logger_provided(): void
    {
        $job = new ExecutorTestJob();

        $resolver = $this->createMock(JobResolverInterface::class);
        $resolver->method('resolve')->willReturn($job);

        $logger = $this->createMock(QueueLoggerInterface::class);
        $logger->expects($this->once())
            ->method('debug')
            ->with(
                'Starting job execution',
                $this->isType('array'),
                1,
                'default',
                ExecutorTestJob::class
            );
        $logger->expects($this->once())
            ->method('logJobMetrics')
            ->with(
                1,
                'default',
                ExecutorTestJob::class,
                $this->isType('float'),
                $this->isType('int')
            );

        $executor = new JobExecutor($resolver, $logger);

        $jobData = [
            'id' => 1,
            'queue' => 'default',
            'task' => ExecutorTestJob::class,
            'payload' => '{}',
            'attempts' => 0,
            'max_attempts' => 3,
        ];

        $executor->execute($jobData);
    }

    #[Test]
    public function execute_decodes_json_payload_correctly(): void
    {
        $job = new ExecutorTestJob();

        $resolver = $this->createMock(JobResolverInterface::class);
        $resolver->method('resolve')->willReturn($job);

        $executor = new JobExecutor($resolver);

        $jobData = [
            'id' => 1,
            'queue' => 'default',
            'task' => ExecutorTestJob::class,
            'payload' => '{"nested":{"key":"value"},"array":[1,2,3]}',
            'attempts' => 0,
            'max_attempts' => 3,
        ];

        $executor->execute($jobData);

        $this->assertEquals([
            'nested' => ['key' => 'value'],
            'array' => [1, 2, 3],
        ], ExecutorTestJob::$lastPayload);
    }

    #[Test]
    public function execute_handles_empty_json_object_payload(): void
    {
        $job = new ExecutorTestJob();

        $resolver = $this->createMock(JobResolverInterface::class);
        $resolver->method('resolve')->willReturn($job);

        $executor = new JobExecutor($resolver);

        $jobData = [
            'id' => 1,
            'queue' => 'default',
            'task' => ExecutorTestJob::class,
            'payload' => '{}',
            'attempts' => 0,
            'max_attempts' => 3,
        ];

        $executor->execute($jobData);

        $this->assertEquals([], ExecutorTestJob::$lastPayload);
    }

    #[Test]
    public function execute_sync_logs_error_via_logger(): void
    {
        $logger = $this->createMock(QueueLoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('does not exist'),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything()
            );

        $executor = new JobExecutor(null, $logger);

        $result = $executor->executeSync('NonExistentClass', []);

        $this->assertFalse($result);
    }

    #[Test]
    public function execute_sync_logs_resolution_failure_via_logger(): void
    {
        $resolver = $this->createMock(JobResolverInterface::class);
        $resolver->method('resolve')->willThrowException(new \Exception('Cannot resolve'));

        $logger = $this->createMock(QueueLoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('Failed to instantiate'),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything()
            );

        $executor = new JobExecutor($resolver, $logger);

        $result = $executor->executeSync(ExecutorTestJob::class, []);

        $this->assertFalse($result);
    }

    #[Test]
    public function execute_sync_logs_job_exception_via_logger(): void
    {
        $job = new ExecutorFailingJob();

        $resolver = $this->createMock(JobResolverInterface::class);
        $resolver->method('resolve')->willReturn($job);

        $logger = $this->createMock(QueueLoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('Failed to execute job'),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything()
            );

        $executor = new JobExecutor($resolver, $logger);

        $result = $executor->executeSync(ExecutorFailingJob::class, []);

        $this->assertFalse($result);
    }

    #[Test]
    public function execute_provides_correct_context_to_logger(): void
    {
        $job = new ExecutorTestJob();

        $resolver = $this->createMock(JobResolverInterface::class);
        $resolver->method('resolve')->willReturn($job);

        $loggedContext = null;

        $logger = $this->createMock(QueueLoggerInterface::class);
        $logger->method('debug')
            ->willReturnCallback(function ($message, $context) use (&$loggedContext) {
                $loggedContext = $context;
            });
        $logger->method('logJobMetrics');

        $executor = new JobExecutor($resolver, $logger);

        $jobData = [
            'id' => 42,
            'queue' => 'emails',
            'task' => ExecutorTestJob::class,
            'payload' => '{}',
            'attempts' => 2,
            'max_attempts' => 5,
        ];

        $executor->execute($jobData);

        $this->assertSame(42, $loggedContext['job_id']);
        $this->assertSame('emails', $loggedContext['queue']);
        $this->assertSame(ExecutorTestJob::class, $loggedContext['task']);
        $this->assertSame(3, $loggedContext['attempt']); // attempts + 1
        $this->assertSame(5, $loggedContext['max_attempts']);
    }

    #[Test]
    public function execute_sync_handles_empty_payload(): void
    {
        $job = new ExecutorTestJob();

        $resolver = $this->createMock(JobResolverInterface::class);
        $resolver->method('resolve')->willReturn($job);

        $executor = new JobExecutor($resolver);

        $result = $executor->executeSync(ExecutorTestJob::class, []);

        $this->assertTrue($result);
        $this->assertSame([], ExecutorTestJob::$lastPayload);
    }

    #[Test]
    public function execute_sync_handles_complex_payload(): void
    {
        $job = new ExecutorTestJob();

        $resolver = $this->createMock(JobResolverInterface::class);
        $resolver->method('resolve')->willReturn($job);

        $executor = new JobExecutor($resolver);

        $complexPayload = [
            'user_id' => 123,
            'email' => 'test@example.com',
            'data' => [
                'nested' => [
                    'deeply' => 'value'
                ],
                'array' => [1, 2, 3]
            ],
            'null_value' => null,
            'boolean' => true,
        ];

        $result = $executor->executeSync(ExecutorTestJob::class, $complexPayload);

        $this->assertTrue($result);
        $this->assertEquals($complexPayload, ExecutorTestJob::$lastPayload);
    }

    #[Test]
    public function get_resolver_returns_resolver_instance(): void
    {
        $executor = new JobExecutor();

        $resolver = $executor->getResolver();

        $this->assertInstanceOf(JobResolverInterface::class, $resolver);
    }

    #[Test]
    public function get_logger_returns_null_when_not_set(): void
    {
        $executor = new JobExecutor();

        $this->assertNull($executor->getLogger());
    }

    #[Test]
    public function get_logger_returns_logger_when_set(): void
    {
        $logger = $this->createMock(QueueLoggerInterface::class);
        $executor = new JobExecutor(null, $logger);

        $this->assertSame($logger, $executor->getLogger());
    }
}
