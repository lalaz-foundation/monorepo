<?php

declare(strict_types=1);

namespace Lalaz\Queue\Tests\Unit;

use Lalaz\Queue\Contracts\FailedJobsInterface;
use Lalaz\Queue\Contracts\JobDispatcherInterface;
use Lalaz\Queue\Contracts\JobProcessorInterface;
use Lalaz\Queue\Contracts\QueueDriverInterface;
use Lalaz\Queue\Contracts\QueueMaintenanceInterface;
use Lalaz\Queue\Contracts\QueueStatsInterface;
use Lalaz\Queue\Tests\Common\QueueUnitTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests to verify Interface Segregation Principle (ISP) compliance.
 *
 * QueueDriverInterface extends all segregated interfaces,
 * allowing drivers to implement subsets if needed.
 */
class InterfaceSegregationTest extends QueueUnitTestCase
{
    #[Test]
    public function queue_driver_interface_extends_job_dispatcher(): void
    {
        $this->assertTrue(
            is_a(QueueDriverInterface::class, JobDispatcherInterface::class, true)
        );
    }

    #[Test]
    public function queue_driver_interface_extends_job_processor(): void
    {
        $this->assertTrue(
            is_a(QueueDriverInterface::class, JobProcessorInterface::class, true)
        );
    }

    #[Test]
    public function queue_driver_interface_extends_queue_stats(): void
    {
        $this->assertTrue(
            is_a(QueueDriverInterface::class, QueueStatsInterface::class, true)
        );
    }

    #[Test]
    public function queue_driver_interface_extends_failed_jobs(): void
    {
        $this->assertTrue(
            is_a(QueueDriverInterface::class, FailedJobsInterface::class, true)
        );
    }

    #[Test]
    public function queue_driver_interface_extends_queue_maintenance(): void
    {
        $this->assertTrue(
            is_a(QueueDriverInterface::class, QueueMaintenanceInterface::class, true)
        );
    }

    #[Test]
    public function job_dispatcher_interface_has_add_method(): void
    {
        $this->assertTrue(method_exists(JobDispatcherInterface::class, 'add'));
    }

    #[Test]
    public function job_processor_interface_has_process_methods(): void
    {
        $this->assertTrue(method_exists(JobProcessorInterface::class, 'process'));
        $this->assertTrue(method_exists(JobProcessorInterface::class, 'processBatch'));
    }

    #[Test]
    public function queue_stats_interface_has_get_stats_method(): void
    {
        $this->assertTrue(method_exists(QueueStatsInterface::class, 'getStats'));
    }

    #[Test]
    public function failed_jobs_interface_has_required_methods(): void
    {
        $this->assertTrue(method_exists(FailedJobsInterface::class, 'getFailedJobs'));
        $this->assertTrue(method_exists(FailedJobsInterface::class, 'getFailedJob'));
        $this->assertTrue(method_exists(FailedJobsInterface::class, 'retryFailedJob'));
        $this->assertTrue(method_exists(FailedJobsInterface::class, 'retryAllFailedJobs'));
    }

    #[Test]
    public function queue_maintenance_interface_has_purge_methods(): void
    {
        $this->assertTrue(method_exists(QueueMaintenanceInterface::class, 'purgeOldJobs'));
        $this->assertTrue(method_exists(QueueMaintenanceInterface::class, 'purgeFailedJobs'));
    }

    #[Test]
    public function can_type_hint_against_job_dispatcher_only(): void
    {
        $dispatcher = new class implements JobDispatcherInterface {
            public function add(
                string $jobClass,
                array $payload = [],
                string $queue = 'default',
                int $priority = 5,
                ?int $delay = null,
                array $options = [],
            ): bool {
                return true;
            }
        };

        $this->assertInstanceOf(JobDispatcherInterface::class, $dispatcher);
        $this->assertNotInstanceOf(QueueDriverInterface::class, $dispatcher);
    }

    #[Test]
    public function can_type_hint_against_job_processor_only(): void
    {
        $processor = new class implements JobProcessorInterface {
            public function process(?string $queue = null): void
            {
                // No-op
            }

            public function processBatch(
                int $batchSize = 10,
                ?string $queue = null,
                int $maxExecutionTime = 55,
            ): array {
                return [];
            }
        };

        $this->assertInstanceOf(JobProcessorInterface::class, $processor);
        $this->assertNotInstanceOf(QueueDriverInterface::class, $processor);
    }

    #[Test]
    public function can_type_hint_against_queue_stats_only(): void
    {
        $stats = new class implements QueueStatsInterface {
            public function getStats(?string $queue = null): array
            {
                return ['pending' => 0, 'processing' => 0];
            }
        };

        $this->assertInstanceOf(QueueStatsInterface::class, $stats);
        $this->assertNotInstanceOf(QueueDriverInterface::class, $stats);
    }
}
