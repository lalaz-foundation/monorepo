<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Container;

use Lalaz\Container\Container;
use Lalaz\Container\ContainerScope;
use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(Container::class)]
/**
 * Tests for Container Scoped Bindings.
 */
final class ScopedBindingTest extends FrameworkUnitTestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();
    }

    public function testcreatesANewInstancePerScopeForScopedBindings(): void
    {
        $this->container->scoped(ScopedService::class);

        // First scope
        $this->container->beginScope();
        $scopeA1 = $this->container->resolve(ScopedService::class);
        $scopeA2 = $this->container->resolve(ScopedService::class);
        $this->container->endScope();

        // Second scope
        $this->container->beginScope();
        $scopeB1 = $this->container->resolve(ScopedService::class);
        $this->container->endScope();

        // Same instance within scope
        $this->assertSame($scopeA1, $scopeA2);

        // Different instance across scopes
        $this->assertNotSame($scopeA1, $scopeB1);
    }

    public function testreportsScopeStatusCorrectly(): void
    {
        $this->assertFalse($this->container->inScope());

        $this->container->beginScope();
        $this->assertTrue($this->container->inScope());

        $this->container->endScope();
        $this->assertFalse($this->container->inScope());
    }

    public function testclearsScopedInstancesOnEndScope(): void
    {
        $this->container->scoped(ScopedService::class);

        $this->container->beginScope();
        $instance1 = $this->container->resolve(ScopedService::class);
        $this->container->endScope();

        $this->container->beginScope();
        $instance2 = $this->container->resolve(ScopedService::class);
        $this->container->endScope();

        $this->assertNotSame($instance1, $instance2);
    }

    public function testallowsScopedBindingWithClosureFactory(): void
    {
        $callCount = 0;

        $this->container->scoped(ScopedService::class, function () use (&$callCount) {
            $callCount++;
            return new ScopedService();
        });

        $this->container->beginScope();
        $this->container->resolve(ScopedService::class);
        $this->container->resolve(ScopedService::class);
        $this->container->endScope();

        $this->container->beginScope();
        $this->container->resolve(ScopedService::class);
        $this->container->endScope();

        // Called once per scope
        $this->assertSame(2, $callCount);
    }

    public function testresolvesScopedBindingsAsRegularBindingsOutsideScope(): void
    {
        $this->container->scoped(ScopedService::class);

        // Outside scope - creates new instance each time (no caching)
        $outside1 = $this->container->resolve(ScopedService::class);
        $outside2 = $this->container->resolve(ScopedService::class);

        $this->assertNotSame($outside1, $outside2);
    }

    public function testrespectsBindingHierarchySingletonOverScoped(): void
    {
        // Singleton takes precedence
        $this->container->singleton(PriorityService::class);

        $this->container->beginScope();
        $instance1 = $this->container->resolve(PriorityService::class);
        $this->container->endScope();

        $this->container->beginScope();
        $instance2 = $this->container->resolve(PriorityService::class);
        $this->container->endScope();

        // Singleton persists across scopes
        $this->assertSame($instance1, $instance2);
    }

    public function testflushClearsScopedBindings(): void
    {
        $this->container->scoped(ScopedService::class);
        $this->container->beginScope();

        $this->container->flush();

        $this->assertFalse($this->container->has(ScopedService::class));
        $this->assertFalse($this->container->inScope());
    }

    // ============================================
    // Scoped Container Middleware Integration Tests
    // ============================================

    public function testcanBeUsedViaContainerScopeHelper(): void
    {
        $this->container->scoped(ScopedService::class);

        $result = ContainerScope::run($this->container, function () {
            $instance1 = $this->container->resolve(ScopedService::class);
            $instance2 = $this->container->resolve(ScopedService::class);

            return $instance1 === $instance2;
        });

        $this->assertTrue($result);
    }

    public function testendsScopeEvenIfCallbackThrows(): void
    {
        try {
            ContainerScope::run($this->container, function () {
                throw new RuntimeException('Test exception');
            });
        } catch (RuntimeException) {
            // Expected
        }

        $this->assertFalse($this->container->inScope());
    }
}

// Test classes
class ScopedService
{
    public string $id;

    public function __construct()
    {
        $this->id = uniqid('scoped_', true);
    }
}

class PriorityService
{
}
