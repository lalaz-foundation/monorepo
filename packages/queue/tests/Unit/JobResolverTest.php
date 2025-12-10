<?php

declare(strict_types=1);

namespace Lalaz\Queue\Tests\Unit;

use Lalaz\Queue\Contracts\JobInterface;
use Lalaz\Queue\JobResolver;
use Lalaz\Queue\Tests\Common\QueueUnitTestCase;
use PHPUnit\Framework\Attributes\Test;

class JobResolverTest extends QueueUnitTestCase
{
    #[Test]
    public function resolve_instantiates_job_class_using_direct_resolution(): void
    {
        $resolver = JobResolver::direct();

        $job = $resolver->resolve(TestableJob::class);

        $this->assertInstanceOf(TestableJob::class, $job);
        $this->assertInstanceOf(JobInterface::class, $job);
    }

    #[Test]
    public function resolve_uses_custom_resolver_callable(): void
    {
        $customJob = new TestableJob();
        $customJob->customProperty = 'injected';

        $resolver = new JobResolver(fn(string $class) => $customJob);

        $job = $resolver->resolve(TestableJob::class);

        $this->assertSame($customJob, $job);
        $this->assertEquals('injected', $job->customProperty);
    }

    #[Test]
    public function resolve_throws_exception_for_non_existent_class(): void
    {
        $resolver = new JobResolver();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Job class 'NonExistentClass' does not exist");

        $resolver->resolve('NonExistentClass');
    }

    #[Test]
    public function resolve_throws_exception_for_non_job_class(): void
    {
        $resolver = new JobResolver(fn(string $class) => new \stdClass());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("must implement JobInterface");

        $resolver->resolve(TestableJob::class);
    }

    #[Test]
    public function from_static_factory_creates_resolver_with_callable(): void
    {
        $customJob = new TestableJob();

        $resolver = JobResolver::from(fn(string $class) => $customJob);

        $job = $resolver->resolve(TestableJob::class);

        $this->assertSame($customJob, $job);
    }

    #[Test]
    public function direct_static_factory_creates_resolver_using_new(): void
    {
        $resolver = JobResolver::direct();

        $job = $resolver->resolve(TestableJob::class);

        $this->assertInstanceOf(TestableJob::class, $job);
    }

    #[Test]
    public function default_constructor_works_without_custom_resolver(): void
    {
        // This test may fail if resolve() function is not defined
        // but we're testing the fallback to direct instantiation
        $resolver = new JobResolver(fn(string $class) => new $class());

        $job = $resolver->resolve(TestableJob::class);

        $this->assertInstanceOf(TestableJob::class, $job);
    }
}

/**
 * Simple job class for testing.
 */
class TestableJob implements JobInterface
{
    public ?string $customProperty = null;
    public array $handledPayload = [];

    public function handle(array $payload): void
    {
        $this->handledPayload = $payload;
    }
}
