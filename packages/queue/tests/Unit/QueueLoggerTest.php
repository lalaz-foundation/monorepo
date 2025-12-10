<?php declare(strict_types=1);

namespace Lalaz\Queue\Tests\Unit;

use Lalaz\Queue\Tests\Common\QueueUnitTestCase;
use Lalaz\Queue\Tests\Common\MockQueueLogger;
use Lalaz\Queue\Contracts\QueueLoggerInterface;
use PHPUnit\Framework\Attributes\Test;

/**
 * Unit tests for QueueLogger interface compliance.
 *
 * Tests the mock logger implementation that follows QueueLoggerInterface.
 * The actual QueueLogger requires database connection, so we test the interface.
 *
 * @package lalaz/queue
 */
class QueueLoggerTest extends QueueUnitTestCase
{
    private MockQueueLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = $this->createMockLogger();
    }

    // =========================================================================
    // Interface Implementation Tests
    // =========================================================================

    #[Test]
    public function it_implements_queue_logger_interface(): void
    {
        $this->assertInstanceOf(QueueLoggerInterface::class, $this->logger);
    }

    // =========================================================================
    // Log Method Tests
    // =========================================================================

    #[Test]
    public function it_logs_with_custom_level(): void
    {
        $this->logger->log('custom', 'Test message');

        $logs = $this->logger->getLogs();
        $this->assertCount(1, $logs);
        $this->assertSame('custom', $logs[0]['level']);
        $this->assertSame('Test message', $logs[0]['message']);
    }

    #[Test]
    public function it_logs_with_context(): void
    {
        $context = ['key' => 'value', 'nested' => ['data' => true]];
        $this->logger->log('info', 'Test message', $context);

        $logs = $this->logger->getLogs();
        $this->assertSame($context, $logs[0]['context']);
    }

    #[Test]
    public function it_logs_with_job_id(): void
    {
        $this->logger->log('info', 'Test message', [], 123);

        $logs = $this->logger->getLogs();
        $this->assertSame(123, $logs[0]['jobId']);
    }

    #[Test]
    public function it_logs_with_queue_name(): void
    {
        $this->logger->log('info', 'Test message', [], null, 'emails');

        $logs = $this->logger->getLogs();
        $this->assertSame('emails', $logs[0]['queue']);
    }

    #[Test]
    public function it_logs_with_task_name(): void
    {
        $this->logger->log('info', 'Test message', [], null, null, 'SendEmailJob');

        $logs = $this->logger->getLogs();
        $this->assertSame('SendEmailJob', $logs[0]['task']);
    }

    #[Test]
    public function it_logs_with_all_parameters(): void
    {
        $this->logger->log(
            'error',
            'Job failed',
            ['error' => 'Connection timeout'],
            42,
            'notifications',
            'SendPushNotification'
        );

        $logs = $this->logger->getLogs();
        $this->assertCount(1, $logs);
        $this->assertSame('error', $logs[0]['level']);
        $this->assertSame('Job failed', $logs[0]['message']);
        $this->assertSame(['error' => 'Connection timeout'], $logs[0]['context']);
        $this->assertSame(42, $logs[0]['jobId']);
        $this->assertSame('notifications', $logs[0]['queue']);
        $this->assertSame('SendPushNotification', $logs[0]['task']);
    }

    // =========================================================================
    // Debug Method Tests
    // =========================================================================

    #[Test]
    public function it_logs_debug_messages(): void
    {
        $this->logger->debug('Debug message', ['trace' => 'info']);

        $logs = $this->logger->getLogsByLevel('debug');
        $this->assertCount(1, $logs);
        $this->assertSame('Debug message', array_values($logs)[0]['message']);
    }

    #[Test]
    public function it_logs_debug_with_job_context(): void
    {
        $this->logger->debug('Starting job', [], 1, 'default', 'TestJob');

        $logs = $this->logger->getLogsByLevel('debug');
        $this->assertCount(1, $logs);
        $log = array_values($logs)[0];
        $this->assertSame(1, $log['jobId']);
        $this->assertSame('default', $log['queue']);
        $this->assertSame('TestJob', $log['task']);
    }

    // =========================================================================
    // Info Method Tests
    // =========================================================================

    #[Test]
    public function it_logs_info_messages(): void
    {
        $this->logger->info('Job started');

        $logs = $this->logger->getLogsByLevel('info');
        $this->assertCount(1, $logs);
        $this->assertSame('Job started', array_values($logs)[0]['message']);
    }

    #[Test]
    public function it_logs_info_with_context(): void
    {
        $this->logger->info('Processing batch', ['size' => 100]);

        $logs = $this->logger->getLogsByLevel('info');
        $this->assertSame(['size' => 100], array_values($logs)[0]['context']);
    }

    // =========================================================================
    // Warning Method Tests
    // =========================================================================

    #[Test]
    public function it_logs_warning_messages(): void
    {
        $this->logger->warning('Slow job execution');

        $logs = $this->logger->getLogsByLevel('warning');
        $this->assertCount(1, $logs);
        $this->assertSame('Slow job execution', array_values($logs)[0]['message']);
    }

    #[Test]
    public function it_logs_warning_with_context(): void
    {
        $this->logger->warning('Retrying job', ['attempt' => 2, 'max' => 3]);

        $logs = $this->logger->getLogsByLevel('warning');
        $this->assertSame(['attempt' => 2, 'max' => 3], array_values($logs)[0]['context']);
    }

    // =========================================================================
    // Error Method Tests
    // =========================================================================

    #[Test]
    public function it_logs_error_messages(): void
    {
        $this->logger->error('Job failed');

        $logs = $this->logger->getLogsByLevel('error');
        $this->assertCount(1, $logs);
        $this->assertSame('Job failed', array_values($logs)[0]['message']);
    }

    #[Test]
    public function it_logs_error_with_exception_context(): void
    {
        $this->logger->error(
            'Job failed',
            ['exception' => 'RuntimeException', 'message' => 'Connection lost'],
            5,
            'default',
            'ProcessPaymentJob'
        );

        $logs = $this->logger->getLogsByLevel('error');
        $log = array_values($logs)[0];
        $this->assertSame(['exception' => 'RuntimeException', 'message' => 'Connection lost'], $log['context']);
        $this->assertSame(5, $log['jobId']);
    }

    // =========================================================================
    // Log Job Metrics Tests
    // =========================================================================

    #[Test]
    public function it_logs_job_metrics(): void
    {
        $this->logger->logJobMetrics(
            jobId: 1,
            queue: 'default',
            task: 'TestJob',
            executionTime: 0.5,
            memoryUsage: 1024 * 1024
        );

        $metrics = $this->logger->getMetrics();
        $this->assertCount(1, $metrics);
        $this->assertSame(1, $metrics[0]['jobId']);
        $this->assertSame('default', $metrics[0]['queue']);
        $this->assertSame('TestJob', $metrics[0]['task']);
        $this->assertSame(0.5, $metrics[0]['executionTime']);
        $this->assertSame(1024 * 1024, $metrics[0]['memoryUsage']);
    }

    #[Test]
    public function it_tracks_multiple_metrics(): void
    {
        $this->logger->logJobMetrics(1, 'default', 'Job1', 0.1, 1000);
        $this->logger->logJobMetrics(2, 'emails', 'Job2', 0.2, 2000);
        $this->logger->logJobMetrics(3, 'default', 'Job3', 0.3, 3000);

        $metrics = $this->logger->getMetrics();
        $this->assertCount(3, $metrics);
    }

    // =========================================================================
    // Helper Method Tests
    // =========================================================================

    #[Test]
    public function it_checks_if_has_logs(): void
    {
        $this->assertFalse($this->logger->hasLogs());

        $this->logger->info('Test');

        $this->assertTrue($this->logger->hasLogs());
    }

    #[Test]
    public function it_checks_if_has_metrics(): void
    {
        $this->assertFalse($this->logger->hasMetrics());

        $this->logger->logJobMetrics(1, 'default', 'Test', 0.1, 1000);

        $this->assertTrue($this->logger->hasMetrics());
    }

    #[Test]
    public function it_counts_logs(): void
    {
        $this->assertSame(0, $this->logger->getLogCount());

        $this->logger->info('First');
        $this->logger->info('Second');
        $this->logger->error('Third');

        $this->assertSame(3, $this->logger->getLogCount());
    }

    #[Test]
    public function it_gets_last_log(): void
    {
        $this->assertNull($this->logger->getLastLog());

        $this->logger->info('First');
        $this->logger->error('Last');

        $last = $this->logger->getLastLog();
        $this->assertSame('error', $last['level']);
        $this->assertSame('Last', $last['message']);
    }

    #[Test]
    public function it_checks_if_logged_message_contains_text(): void
    {
        $this->logger->info('Job started successfully');
        $this->logger->error('Job failed with timeout');

        $this->assertTrue($this->logger->hasLoggedMessage('started'));
        $this->assertTrue($this->logger->hasLoggedMessage('failed', 'error'));
        $this->assertFalse($this->logger->hasLoggedMessage('started', 'error'));
        $this->assertFalse($this->logger->hasLoggedMessage('nonexistent'));
    }

    #[Test]
    public function it_resets_all_state(): void
    {
        $this->logger->info('Test');
        $this->logger->logJobMetrics(1, 'default', 'Test', 0.1, 1000);

        $this->logger->reset();

        $this->assertFalse($this->logger->hasLogs());
        $this->assertFalse($this->logger->hasMetrics());
    }

    // =========================================================================
    // Multiple Logs Tests
    // =========================================================================

    #[Test]
    public function it_stores_logs_in_order(): void
    {
        $this->logger->info('First');
        $this->logger->debug('Second');
        $this->logger->warning('Third');
        $this->logger->error('Fourth');

        $logs = $this->logger->getLogs();
        $this->assertSame('First', $logs[0]['message']);
        $this->assertSame('Second', $logs[1]['message']);
        $this->assertSame('Third', $logs[2]['message']);
        $this->assertSame('Fourth', $logs[3]['message']);
    }

    #[Test]
    public function it_filters_logs_by_level(): void
    {
        $this->logger->info('Info 1');
        $this->logger->error('Error 1');
        $this->logger->info('Info 2');
        $this->logger->error('Error 2');

        $infoLogs = $this->logger->getLogsByLevel('info');
        $errorLogs = $this->logger->getLogsByLevel('error');

        $this->assertCount(2, $infoLogs);
        $this->assertCount(2, $errorLogs);
    }

    #[Test]
    public function it_includes_timestamp_in_logs(): void
    {
        $before = microtime(true);
        $this->logger->info('Test');
        $after = microtime(true);

        $log = $this->logger->getLastLog();
        $this->assertArrayHasKey('timestamp', $log);
        $this->assertGreaterThanOrEqual($before, $log['timestamp']);
        $this->assertLessThanOrEqual($after, $log['timestamp']);
    }
}
