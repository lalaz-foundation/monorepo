<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Container;

use Lalaz\Container\Container;
use Lalaz\Container\Exceptions\ContainerException;
use Lalaz\Container\Exceptions\NotFoundException;
use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Container::class)]
/**
 * Tests for the Container class.
 */
final class ContainerTest extends FrameworkUnitTestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();
    }

    // ============================================
    // Container Binding Tests
    // ============================================

    public function testbindsAndResolvesAClass(): void
    {
        $this->container->bind(SimpleService::class);

        $instance = $this->container->resolve(SimpleService::class);

        $this->assertInstanceOf(SimpleService::class, $instance);
    }

    public function testbindsAnInterfaceToAConcreteImplementation(): void
    {
        $this->container->bind(ServiceInterface::class, ConcreteService::class);

        $instance = $this->container->resolve(ServiceInterface::class);

        $this->assertInstanceOf(ConcreteService::class, $instance);
    }

    public function testbindsUsingAClosureFactory(): void
    {
        $this->container->bind(SimpleService::class, fn () => new SimpleService('factory'));

        $instance = $this->container->resolve(SimpleService::class);

        $this->assertSame('factory', $instance->name);
    }

    public function testcreatesANewInstanceOnEachResolutionForNonSingletons(): void
    {
        $this->container->bind(SimpleService::class);

        $instance1 = $this->container->resolve(SimpleService::class);
        $instance2 = $this->container->resolve(SimpleService::class);

        $this->assertNotSame($instance1, $instance2);
    }

    // ============================================
    // Container Singleton Tests
    // ============================================

    public function testreturnsTheSameInstanceForSingletons(): void
    {
        $this->container->singleton(SimpleService::class);

        $instance1 = $this->container->resolve(SimpleService::class);
        $instance2 = $this->container->resolve(SimpleService::class);

        $this->assertSame($instance1, $instance2);
    }

    public function teststoresSingletonWithClosureFactory(): void
    {
        $callCount = 0;
        $this->container->singleton(SimpleService::class, function () use (&$callCount) {
            $callCount++;
            return new SimpleService('singleton');
        });

        $this->container->resolve(SimpleService::class);
        $this->container->resolve(SimpleService::class);

        $this->assertSame(1, $callCount);
    }

    // ============================================
    // Container Instance Tests
    // ============================================

    public function testregistersAnExistingInstance(): void
    {
        $instance = new SimpleService('existing');
        $this->container->instance(SimpleService::class, $instance);

        $resolved = $this->container->resolve(SimpleService::class);

        $this->assertSame($instance, $resolved);
        $this->assertSame('existing', $resolved->name);
    }

    // ============================================
    // Container Alias Tests
    // ============================================

    public function testresolvesByAlias(): void
    {
        $this->container->bind(SimpleService::class);
        $this->container->alias(SimpleService::class, 'service');

        $instance = $this->container->resolve('service');

        $this->assertInstanceOf(SimpleService::class, $instance);
    }

    public function testsharesSingletonAcrossAliases(): void
    {
        $this->container->singleton(SimpleService::class);
        $this->container->alias(SimpleService::class, 'service');

        $byClass = $this->container->resolve(SimpleService::class);
        $byAlias = $this->container->resolve('service');

        $this->assertSame($byClass, $byAlias);
    }

    // ============================================
    // Container Auto-Wiring Tests
    // ============================================

    public function testautoWiresConstructorDependencies(): void
    {
        $instance = $this->container->resolve(ServiceWithDependency::class);

        $this->assertInstanceOf(ServiceWithDependency::class, $instance);
        $this->assertInstanceOf(SimpleService::class, $instance->service);
    }

    public function testautoWiresNestedDependencies(): void
    {
        $instance = $this->container->resolve(ServiceWithNestedDependency::class);

        $this->assertInstanceOf(ServiceWithNestedDependency::class, $instance);
        $this->assertInstanceOf(SimpleService::class, $instance->dependency->service);
    }

    public function testusesProvidedParametersOverAutoWiring(): void
    {
        $custom = new SimpleService('custom');

        $instance = $this->container->resolve(ServiceWithDependency::class, [
            'service' => $custom,
        ]);

        $this->assertSame($custom, $instance->service);
        $this->assertSame('custom', $instance->service->name);
    }

    public function testresolvesDefaultParameterValues(): void
    {
        $instance = $this->container->resolve(ServiceWithDefaults::class);

        $this->assertSame('default', $instance->value);
        $this->assertSame(10, $instance->count);
    }

    public function testresolvesNullableParametersAsNullWhenNotBoundAndClassDoesNotExist(): void
    {
        $instance = $this->container->resolve(ServiceWithNullableNonExistent::class);

        $this->assertNull($instance->optional);
    }

    public function testautoWiresNullableParametersWhenClassExists(): void
    {
        $instance = $this->container->resolve(ServiceWithNullable::class);

        $this->assertInstanceOf(UnboundService::class, $instance->optional);
    }

    // ============================================
    // Container Error Handling Tests
    // ============================================

    public function testthrowsNotFoundExceptionForUnboundAbstractWithoutClass(): void
    {
        $this->expectException(NotFoundException::class);

        $this->container->get('NonExistentClass');
    }

    public function testthrowsContainerExceptionForNonInstantiableClass(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('is not instantiable');

        $this->container->resolve(ServiceInterface::class);
    }

    public function testthrowsContainerExceptionForUnresolvableParameter(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Unable to resolve parameter');

        $this->container->resolve(ServiceWithUnresolvableParam::class);
    }

    public function testdetectsCircularDependencies(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Circular dependency detected');

        $this->container->resolve(CircularA::class);
    }

    // ============================================
    // Container PSR-11 Compliance Tests
    // ============================================

    public function testimplementsHasCorrectly(): void
    {
        $this->container->bind(SimpleService::class);

        $this->assertTrue($this->container->has(SimpleService::class));
        $this->assertFalse($this->container->has('UnboundService'));
    }

    public function testimplementsGetCorrectly(): void
    {
        $this->container->bind(SimpleService::class);

        $instance = $this->container->get(SimpleService::class);

        $this->assertInstanceOf(SimpleService::class, $instance);
    }

    public function testgetThrowsNotFoundExceptionForMissingService(): void
    {
        $this->expectException(NotFoundException::class);

        $this->container->get('MissingService');
    }

    // ============================================
    // Container Method Call Tests
    // ============================================

    public function testcallsClosureWithDependencyInjection(): void
    {
        $result = $this->container->call(function (SimpleService $service) {
            return $service->name;
        });

        $this->assertSame('default', $result);
    }

    public function testcallsMethodOnObjectWithDependencyInjection(): void
    {
        $obj = new ServiceWithMethod();

        $result = $this->container->call([$obj, 'doSomething']);

        $this->assertSame('did something with default', $result);
    }

    public function testmergesProvidedParametersWithAutoWiredOnes(): void
    {
        $result = $this->container->call(
            fn (SimpleService $service, string $extra) => $service->name . '-' . $extra,
            ['extra' => 'provided'],
        );

        $this->assertSame('default-provided', $result);
    }

    // ============================================
    // Container Flush Tests
    // ============================================

    public function testclearsAllBindingsAndInstances(): void
    {
        $this->container->singleton(SimpleService::class);
        $this->container->resolve(SimpleService::class);

        $this->container->flush();

        $this->assertFalse($this->container->has(SimpleService::class));
    }
}

// Test classes
class SimpleService
{
    public function __construct(public string $name = 'default')
    {
    }
}

interface ServiceInterface
{
    public function execute(): void;
}

class ConcreteService implements ServiceInterface
{
    public function execute(): void
    {
    }
}

class ServiceWithDependency
{
    public function __construct(public SimpleService $service)
    {
    }
}

class ServiceWithNestedDependency
{
    public function __construct(public ServiceWithDependency $dependency)
    {
    }
}

class ServiceWithDefaults
{
    public function __construct(
        public string $value = 'default',
        public int $count = 10,
    ) {
    }
}

class ServiceWithNullable
{
    public function __construct(public ?UnboundService $optional = null)
    {
    }
}

class ServiceWithNullableNonExistent
{
    public function __construct(public ?NonExistentService $optional = null)
    {
    }
}

class UnboundService
{
}

class ServiceWithUnresolvableParam
{
    public function __construct(string $required)
    {
    }
}

class CircularA
{
    public function __construct(CircularB $b)
    {
    }
}

class CircularB
{
    public function __construct(CircularA $a)
    {
    }
}

class SelfReferencing
{
    public function __construct(SelfReferencing $self)
    {
    }
}

class ServiceWithMethod
{
    public function doSomething(SimpleService $service): string
    {
        return 'did something with ' . $service->name;
    }
}
