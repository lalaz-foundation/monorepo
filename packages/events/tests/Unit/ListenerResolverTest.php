<?php

declare(strict_types=1);

namespace Lalaz\Events\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Lalaz\Events\Tests\Common\EventsUnitTestCase;
use Lalaz\Events\Tests\Common\FakeEventListener;
use Lalaz\Events\ListenerResolver;

/**
 * Unit tests for ListenerResolver
 *
 * Tests the listener class resolution functionality
 */
final class ListenerResolverTest extends EventsUnitTestCase
{
    #[Test]
    public function it_uses_custom_resolver_when_provided(): void
    {
        $customInstance = new FakeEventListener(['custom']);
        $resolver = new ListenerResolver(fn(string $class) => $customInstance);

        $instance = $resolver->resolve(FakeEventListener::class);

        $this->assertSame($customInstance, $instance);
    }

    #[Test]
    public function it_creates_from_callable(): void
    {
        $customInstance = new FakeEventListener(['from']);
        $resolver = ListenerResolver::from(fn(string $class) => $customInstance);

        $instance = $resolver->resolve(FakeEventListener::class);

        $this->assertSame($customInstance, $instance);
    }

    #[Test]
    public function it_creates_direct_resolver(): void
    {
        $resolver = ListenerResolver::direct();

        $instance = $resolver->resolve(FakeEventListener::class);

        $this->assertInstanceOf(FakeEventListener::class, $instance);
    }

    #[Test]
    public function it_passes_class_name_to_custom_resolver(): void
    {
        $passedClass = null;
        $resolver = new ListenerResolver(function (string $class) use (&$passedClass) {
            $passedClass = $class;
            return new FakeEventListener(['test']);
        });

        $resolver->resolve(FakeEventListener::class);

        $this->assertSame(FakeEventListener::class, $passedClass);
    }

    #[Test]
    public function it_creates_new_instance_each_time_with_direct(): void
    {
        $resolver = ListenerResolver::direct();

        $instance1 = $resolver->resolve(FakeEventListener::class);
        $instance2 = $resolver->resolve(FakeEventListener::class);

        $this->assertNotSame($instance1, $instance2);
    }

    #[Test]
    public function it_allows_custom_resolver_to_cache_instances(): void
    {
        $cached = [];
        $resolver = new ListenerResolver(function (string $class) use (&$cached) {
            if (!isset($cached[$class])) {
                $cached[$class] = new $class();
            }
            return $cached[$class];
        });

        $instance1 = $resolver->resolve(FakeEventListener::class);
        $instance2 = $resolver->resolve(FakeEventListener::class);

        $this->assertSame($instance1, $instance2);
    }

    #[Test]
    public function it_resolves_stdclass_with_direct(): void
    {
        $resolver = ListenerResolver::direct();

        $instance = $resolver->resolve(\stdClass::class);

        $this->assertInstanceOf(\stdClass::class, $instance);
    }

    #[Test]
    public function it_handles_invokable_class(): void
    {
        $invokable = new class {
            public bool $called = false;
            public function __invoke(): void
            {
                $this->called = true;
            }
        };

        $resolver = new ListenerResolver(fn(string $class) => $invokable);
        $instance = $resolver->resolve('InvokableClass');

        $instance();
        $this->assertTrue($invokable->called);
    }
}
