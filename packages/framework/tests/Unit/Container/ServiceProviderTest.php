<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Container;

use Lalaz\Container\Container;
use Lalaz\Container\Contracts\ContainerInterface;
use Lalaz\Container\ProviderRegistry;
use Lalaz\Container\ServiceProvider;
use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Throwable;

#[CoversClass(Container::class)]
/**
 * Tests for ServiceProvider and ProviderRegistry classes.
 */
final class ServiceProviderTest extends FrameworkUnitTestCase
{
    private Container $container;
    private ProviderRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new Container();
        $this->registry = new ProviderRegistry($this->container);
        TestServiceProvider::reset();
        SecondTestServiceProvider::reset();
    }

    // ============================================
    // ServiceProvider Tests
    // ============================================

    public function testreceivesContainerInstanceInConstructor(): void
    {
        $provider = new TestServiceProvider($this->container);

        $this->assertSame($this->container, $provider->getContainer());
    }

    public function testregistersBindingsViaProtectedHelpers(): void
    {
        $provider = new TestServiceProvider($this->container);
        $provider->register();

        $this->assertTrue($this->container->has('test.binding'));
        $this->assertTrue($this->container->has('test.singleton'));
        $this->assertTrue($this->container->has('test.instance'));
    }

    public function testcanRegisterAliases(): void
    {
        $provider = new AliasServiceProvider($this->container);
        $provider->register();

        $byClass = $this->container->resolve(AliasedService::class);
        $byAlias = $this->container->resolve('aliased');

        $this->assertInstanceOf(AliasedService::class, $byClass);
        $this->assertSame($byClass, $byAlias);
    }

    public function testbootMethodIsOptional(): void
    {
        $provider = new MinimalServiceProvider($this->container);
        $provider->register();

        // boot should not throw
        $exception = null;
        try {
            $provider->boot();
        } catch (Throwable $e) {
            $exception = $e;
        }
        $this->assertNull($exception);
    }

    // ============================================
    // ProviderRegistry Tests
    // ============================================

    public function testregistersAProviderByClassName(): void
    {
        $this->registry->register(TestServiceProvider::class);

        $this->assertTrue($this->container->has('test.binding'));
        $this->assertContains('register', TestServiceProvider::$events);
    }

    public function testregistersAProviderInstance(): void
    {
        $provider = new TestServiceProvider($this->container);

        $returned = $this->registry->register($provider);

        $this->assertSame($provider, $returned);
        $this->assertContains('register', TestServiceProvider::$events);
    }

    public function testregistersMultipleProviders(): void
    {
        $this->registry->registerProviders([
            TestServiceProvider::class,
            SecondTestServiceProvider::class,
        ]);

        $this->assertContains('register', TestServiceProvider::$events);
        $this->assertContains('register', SecondTestServiceProvider::$events);
    }

    public function testbootsAllRegisteredProviders(): void
    {
        $this->registry->register(TestServiceProvider::class);
        $this->registry->register(SecondTestServiceProvider::class);

        $this->registry->boot();

        $this->assertContains('boot', TestServiceProvider::$events);
        $this->assertContains('boot', SecondTestServiceProvider::$events);
    }

    public function testmaintainsRegistrationOrderDuringBoot(): void
    {
        $this->registry->register(TestServiceProvider::class);
        $this->registry->register(SecondTestServiceProvider::class);

        $this->registry->boot();

        $testBootIndex = array_search('boot', TestServiceProvider::$events);
        $secondBootIndex = array_search('boot', SecondTestServiceProvider::$events);

        $this->assertNotFalse($testBootIndex);
        $this->assertNotFalse($secondBootIndex);
    }

    public function testdoesNotBootAProviderTwice(): void
    {
        $this->registry->register(TestServiceProvider::class);

        $this->registry->boot();
        $this->registry->boot();

        $bootCount = count(array_filter(TestServiceProvider::$events, fn ($e) => $e === 'boot'));

        $this->assertSame(1, $bootCount);
    }

    public function testreturnsListOfRegisteredProviders(): void
    {
        $this->registry->register(TestServiceProvider::class);
        $this->registry->register(SecondTestServiceProvider::class);

        $providers = $this->registry->getProviders();

        $this->assertCount(2, $providers);
        $this->assertInstanceOf(TestServiceProvider::class, $providers[0]);
        $this->assertInstanceOf(SecondTestServiceProvider::class, $providers[1]);
    }
}

// Test providers
class TestServiceProvider extends ServiceProvider
{
    public static array $events = [];

    public static function reset(): void
    {
        self::$events = [];
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    public function register(): void
    {
        self::$events[] = 'register';

        $this->bind('test.binding', fn () => 'bound');
        $this->singleton('test.singleton', fn () => new \stdClass());
        $this->instance('test.instance', 'instance-value');
    }

    public function boot(): void
    {
        self::$events[] = 'boot';
    }
}

class SecondTestServiceProvider extends ServiceProvider
{
    public static array $events = [];

    public static function reset(): void
    {
        self::$events = [];
    }

    public function register(): void
    {
        self::$events[] = 'register';
        $this->bind('second.binding', fn () => 'second');
    }

    public function boot(): void
    {
        self::$events[] = 'boot';
    }
}

class MinimalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Minimal implementation
    }
}

class AliasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->singleton(AliasedService::class);
        $this->alias(AliasedService::class, 'aliased');
    }
}

class AliasedService
{
}
