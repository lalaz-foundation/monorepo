<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Container;

use Lalaz\Container\Container;
use Lalaz\Container\Contracts\BindingContainerInterface;
use Lalaz\Container\Contracts\ContainerInterface;
use Lalaz\Container\Contracts\FlushableContainerInterface;
use Lalaz\Container\Contracts\ResolvingContainerInterface;
use Lalaz\Container\Contracts\ScopedContainerInterface;
use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Container\ContainerInterface as PsrContainerInterface;
use stdClass;

#[CoversClass(Container::class)]
/**
 * Tests for Container Interface Segregation.
 */
final class InterfaceSegregationTest extends FrameworkUnitTestCase
{
    public function testimplementsAllSegregatedInterfaces(): void
    {
        $container = new Container();

        $this->assertInstanceOf(ContainerInterface::class, $container);
        $this->assertInstanceOf(PsrContainerInterface::class, $container);
        $this->assertInstanceOf(BindingContainerInterface::class, $container);
        $this->assertInstanceOf(ResolvingContainerInterface::class, $container);
        $this->assertInstanceOf(ScopedContainerInterface::class, $container);
        $this->assertInstanceOf(FlushableContainerInterface::class, $container);
    }

    public function testcanBeTypeHintedAsBindingContainerInterface(): void
    {
        $container = new Container();

        $registerServices = function (BindingContainerInterface $c): void {
            $c->bind('service', fn() => 'bound');
            $c->singleton('singleton', fn() => new stdClass());
            $c->instance('instance', 'value');
            $c->alias('service', 'service.alias');
        };

        $registerServices($container);

        $this->assertTrue($container->bound('service'));
        $this->assertTrue($container->bound('singleton'));
        $this->assertTrue($container->bound('instance'));
        $this->assertTrue($container->bound('service.alias'));
    }

    public function testcanBeTypeHintedAsResolvingContainerInterface(): void
    {
        $container = new Container();
        $container->bind('greeting', fn() => 'Hello');

        $useServices = function (ResolvingContainerInterface $c): string {
            return $c->resolve('greeting');
        };

        $this->assertSame('Hello', $useServices($container));
    }

    public function testcanBeTypeHintedAsScopedContainerInterface(): void
    {
        $container = new Container();

        $manageScope = function (ScopedContainerInterface $c): bool {
            $this->assertFalse($c->inScope());
            $c->beginScope();
            $inScope = $c->inScope();
            $c->endScope();
            return $inScope;
        };

        $this->assertTrue($manageScope($container));
    }

    public function testcanBeTypeHintedAsFlushableContainerInterface(): void
    {
        $container = new Container();
        $container->bind('temp', fn() => 'value');

        $cleanup = function (FlushableContainerInterface $c): void {
            $c->flush();
        };

        $this->assertTrue($container->bound('temp'));
        $cleanup($container);
        $this->assertFalse($container->bound('temp'));
    }

    public function testcanBeTypeHintedAsPsrContainerInterface(): void
    {
        $container = new Container();
        $container->instance('psr.service', 'psr-value');

        $psrConsumer = function (PsrContainerInterface $c): mixed {
            return $c->has('psr.service') ? $c->get('psr.service') : null;
        };

        $this->assertSame('psr-value', $psrConsumer($container));
    }

    public function testallowsMinimalDependencyOnResolvingContainerInterfaceForServiceConsumers(): void
    {
        $container = new Container();
        $container->singleton(stdClass::class, fn() => new stdClass());

        // Service that only needs to resolve dependencies
        $service = new class($container) {
            public function __construct(
                private ResolvingContainerInterface $container
            ) {}

            public function getObject(): stdClass {
                return $this->container->resolve(stdClass::class);
            }
        };

        $this->assertInstanceOf(stdClass::class, $service->getObject());
    }

    public function testallowsMinimalDependencyOnBindingContainerInterfaceForServiceProviders(): void
    {
        $container = new Container();

        // Provider that only needs to register services
        $provider = new class($container) {
            public function __construct(
                private BindingContainerInterface $container
            ) {}

            public function register(): void {
                $this->container->singleton('logger', fn() => 'FileLogger');
                $this->container->bind('cache', fn() => 'RedisCache');
            }

            public function isBound(string $id): bool {
                return $this->container->bound($id);
            }
        };

        $provider->register();

        $this->assertTrue($provider->isBound('logger'));
        $this->assertTrue($provider->isBound('cache'));
    }
}
