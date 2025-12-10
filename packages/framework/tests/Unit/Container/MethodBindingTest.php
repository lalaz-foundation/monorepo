<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Container;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use Lalaz\Container\Container;
use Lalaz\Container\MethodBindingBuilder;
use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;

#[CoversClass(Container::class)]
#[CoversClass(MethodBindingBuilder::class)]
class MethodBindingTest extends FrameworkUnitTestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    protected function tearDown(): void
    {
        $this->container->flush();
    }

    #[Test]
    public function when_returns_method_binding_builder(): void
    {
        $builder = $this->container->when(ServiceWithSetter::class);

        $this->assertInstanceOf(MethodBindingBuilder::class, $builder);
    }

    #[Test]
    public function it_can_register_method_binding(): void
    {
        $this->container->bind(LoggerContract::class, SimpleLogger::class);

        $this->container->when(ServiceWithSetter::class)
            ->method('setLogger')
            ->give(LoggerContract::class);

        $this->assertTrue($this->container->hasMethodBindings(ServiceWithSetter::class));
    }

    #[Test]
    public function it_injects_dependency_via_setter(): void
    {
        $this->container->bind(LoggerContract::class, SimpleLogger::class);

        $this->container->when(ServiceWithSetter::class)
            ->method('setLogger')
            ->give(LoggerContract::class);

        $service = $this->container->resolve(ServiceWithSetter::class);

        $this->assertInstanceOf(ServiceWithSetter::class, $service);
        $this->assertInstanceOf(SimpleLogger::class, $service->getLogger());
    }

    #[Test]
    public function it_injects_via_closure(): void
    {
        $this->container->when(ServiceWithSetter::class)
            ->method('setLogger')
            ->give(fn(Container $c) => new SimpleLogger());

        $service = $this->container->resolve(ServiceWithSetter::class);

        $this->assertInstanceOf(SimpleLogger::class, $service->getLogger());
    }

    #[Test]
    public function it_supports_multiple_method_bindings(): void
    {
        $this->container->bind(LoggerContract::class, SimpleLogger::class);
        $this->container->bind(CacheContract::class, SimpleCache::class);

        $this->container->when(ServiceWithMultipleSetters::class)
            ->method('setLogger')->give(LoggerContract::class)
            ->method('setCache')->give(CacheContract::class);

        $service = $this->container->resolve(ServiceWithMultipleSetters::class);

        $this->assertInstanceOf(SimpleLogger::class, $service->getLogger());
        $this->assertInstanceOf(SimpleCache::class, $service->getCache());
    }

    #[Test]
    public function it_supports_needs_alias_for_method(): void
    {
        $this->container->bind(LoggerContract::class, SimpleLogger::class);

        $this->container->when(ServiceWithSetter::class)
            ->needs('setLogger')
            ->give(LoggerContract::class);

        $service = $this->container->resolve(ServiceWithSetter::class);

        $this->assertInstanceOf(SimpleLogger::class, $service->getLogger());
    }

    #[Test]
    public function it_supports_inject_alias_for_give(): void
    {
        $this->container->bind(LoggerContract::class, SimpleLogger::class);

        $this->container->when(ServiceWithSetter::class)
            ->method('setLogger')
            ->inject(LoggerContract::class);

        $service = $this->container->resolve(ServiceWithSetter::class);

        $this->assertInstanceOf(SimpleLogger::class, $service->getLogger());
    }

    #[Test]
    public function it_can_inject_concrete_instance(): void
    {
        $logger = new SimpleLogger();

        $this->container->when(ServiceWithSetter::class)
            ->method('setLogger')
            ->give($logger);

        $service = $this->container->resolve(ServiceWithSetter::class);

        $this->assertSame($logger, $service->getLogger());
    }

    #[Test]
    public function it_can_inject_scalar_values(): void
    {
        $this->container->when(ServiceWithConfiguration::class)
            ->method('configure')
            ->give(['debug' => true, 'timeout' => 30]);

        $service = $this->container->resolve(ServiceWithConfiguration::class);

        $this->assertTrue($service->isDebug());
        $this->assertEquals(30, $service->getTimeout());
    }

    #[Test]
    public function get_method_bindings_returns_empty_array_for_unbound_class(): void
    {
        $bindings = $this->container->getMethodBindings(ServiceWithSetter::class);

        $this->assertIsArray($bindings);
        $this->assertEmpty($bindings);
    }

    #[Test]
    public function has_method_bindings_returns_false_for_unbound_class(): void
    {
        $this->assertFalse($this->container->hasMethodBindings(ServiceWithSetter::class));
    }

    #[Test]
    public function flush_clears_method_bindings(): void
    {
        $this->container->when(ServiceWithSetter::class)
            ->method('setLogger')
            ->give(SimpleLogger::class);

        $this->assertTrue($this->container->hasMethodBindings(ServiceWithSetter::class));

        $this->container->flush();

        $this->assertFalse($this->container->hasMethodBindings(ServiceWithSetter::class));
    }

    #[Test]
    public function it_skips_nonexistent_methods_gracefully(): void
    {
        $this->container->when(ServiceWithSetter::class)
            ->method('nonExistentMethod')
            ->give(SimpleLogger::class);

        // Should not throw exception
        $service = $this->container->resolve(ServiceWithSetter::class);

        $this->assertInstanceOf(ServiceWithSetter::class, $service);
        $this->assertNull($service->getLogger());
    }

    #[Test]
    public function method_binding_works_with_singletons(): void
    {
        $this->container->singleton(ServiceWithSetter::class);
        $this->container->bind(LoggerContract::class, SimpleLogger::class);

        $this->container->when(ServiceWithSetter::class)
            ->method('setLogger')
            ->give(LoggerContract::class);

        $service1 = $this->container->resolve(ServiceWithSetter::class);
        $service2 = $this->container->resolve(ServiceWithSetter::class);

        $this->assertSame($service1, $service2);
        $this->assertInstanceOf(SimpleLogger::class, $service1->getLogger());
    }

    #[Test]
    public function method_binding_works_with_constructor_injection(): void
    {
        $this->container->bind(DependencyContract::class, SimpleDependency::class);
        $this->container->bind(LoggerContract::class, SimpleLogger::class);

        $this->container->when(ServiceWithConstructorAndSetter::class)
            ->method('setLogger')
            ->give(LoggerContract::class);

        $service = $this->container->resolve(ServiceWithConstructorAndSetter::class);

        $this->assertInstanceOf(SimpleDependency::class, $service->getDependency());
        $this->assertInstanceOf(SimpleLogger::class, $service->getLogger());
    }

    #[Test]
    public function give_throws_when_no_method_specified(): void
    {
        $builder = $this->container->when(ServiceWithSetter::class);

        $this->expectException(\Lalaz\Exceptions\ConfigurationException::class);
        $this->expectExceptionMessage('You must call method() before give()');

        $builder->give(SimpleLogger::class);
    }

    #[Test]
    public function builder_returns_correct_concrete(): void
    {
        $builder = $this->container->when(ServiceWithSetter::class);

        $this->assertEquals(ServiceWithSetter::class, $builder->getConcrete());
    }

    #[Test]
    public function method_binding_resolves_class_string_automatically(): void
    {
        // Give class name string directly (not via container binding)
        $this->container->when(ServiceWithSetter::class)
            ->method('setLogger')
            ->give(SimpleLogger::class);

        $service = $this->container->resolve(ServiceWithSetter::class);

        $this->assertInstanceOf(SimpleLogger::class, $service->getLogger());
    }
}

// Test doubles

interface LoggerContract
{
    public function log(string $message): void;
}

interface CacheContract
{
    public function get(string $key): mixed;
}

interface DependencyContract
{
    public function execute(): void;
}

class SimpleLogger implements LoggerContract
{
    public function log(string $message): void
    {
        // Implementation
    }
}

class SimpleCache implements CacheContract
{
    public function get(string $key): mixed
    {
        return null;
    }
}

class SimpleDependency implements DependencyContract
{
    public function execute(): void
    {
        // Implementation
    }
}

class ServiceWithSetter
{
    private ?LoggerContract $logger = null;

    public function setLogger(LoggerContract $logger): void
    {
        $this->logger = $logger;
    }

    public function getLogger(): ?LoggerContract
    {
        return $this->logger;
    }
}

class ServiceWithMultipleSetters
{
    private ?LoggerContract $logger = null;
    private ?CacheContract $cache = null;

    public function setLogger(LoggerContract $logger): void
    {
        $this->logger = $logger;
    }

    public function setCache(CacheContract $cache): void
    {
        $this->cache = $cache;
    }

    public function getLogger(): ?LoggerContract
    {
        return $this->logger;
    }

    public function getCache(): ?CacheContract
    {
        return $this->cache;
    }
}

class ServiceWithConfiguration
{
    private bool $debug = false;
    private int $timeout = 0;

    public function configure(bool $debug, int $timeout): void
    {
        $this->debug = $debug;
        $this->timeout = $timeout;
    }

    public function isDebug(): bool
    {
        return $this->debug;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }
}

class ServiceWithConstructorAndSetter
{
    private ?LoggerContract $logger = null;

    public function __construct(
        private DependencyContract $dependency,
    ) {}

    public function setLogger(LoggerContract $logger): void
    {
        $this->logger = $logger;
    }

    public function getDependency(): DependencyContract
    {
        return $this->dependency;
    }

    public function getLogger(): ?LoggerContract
    {
        return $this->logger;
    }
}
