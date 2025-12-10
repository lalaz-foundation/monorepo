<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Runtime;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\After;
use Lalaz\Runtime\Application;
use Lalaz\Container\Container;
use Lalaz\Container\Contracts\ContainerInterface;
use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;

#[CoversClass(Application::class)]
final class ApplicationTest extends FrameworkUnitTestCase
{
    #[After]
    protected function tearDown(): void
    {
        Application::clearInstance();
    }

    #[Test]
    public function it_can_set_and_get_instance(): void
    {
        $container = new Container();
        $app = new Application($container);

        Application::setInstance($app);

        $this->assertTrue(Application::hasInstance());
        $this->assertSame($app, Application::getInstance());
    }

    #[Test]
    public function it_can_clear_instance(): void
    {
        $container = new Container();
        $app = new Application($container);

        Application::setInstance($app);
        $this->assertTrue(Application::hasInstance());

        Application::clearInstance();
        $this->assertFalse(Application::hasInstance());
        $this->assertNull(Application::getInstance());
    }

    #[Test]
    public function context_returns_instance(): void
    {
        $container = new Container();
        $app = new Application($container);

        Application::setInstance($app);

        $this->assertSame($app, Application::context());
    }

    #[Test]
    public function context_throws_when_no_instance(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No application context available');

        Application::context();
    }

    #[Test]
    public function static_container_returns_container(): void
    {
        $container = new Container();
        $app = new Application($container);

        Application::setInstance($app);

        $this->assertSame($container, Application::container());
    }

    #[Test]
    public function static_container_throws_when_no_instance(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No application context available');

        Application::container();
    }

    #[Test]
    public function it_provides_container_via_get_container(): void
    {
        $container = new Container();
        $app = new Application($container);

        $this->assertSame($container, $app->getContainer());
    }

    #[Test]
    public function it_stores_base_path(): void
    {
        $container = new Container();
        $app = new Application($container, '/var/www/app');

        $this->assertSame('/var/www/app', $app->basePath());
    }

    #[Test]
    public function it_can_set_base_path(): void
    {
        $container = new Container();
        $app = new Application($container);

        $this->assertNull($app->basePath());

        $result = $app->setBasePath('/new/path');

        $this->assertSame($app, $result);
        $this->assertSame('/new/path', $app->basePath());
    }

    #[Test]
    public function it_stores_debug_mode(): void
    {
        $container = new Container();

        $app = new Application($container, null, true);
        $this->assertTrue($app->isDebug());

        $app2 = new Application($container, null, false);
        $this->assertFalse($app2->isDebug());
    }

    #[Test]
    public function it_can_set_debug_mode(): void
    {
        $container = new Container();
        $app = new Application($container);

        $this->assertFalse($app->isDebug());

        $result = $app->setDebug(true);

        $this->assertSame($app, $result);
        $this->assertTrue($app->isDebug());
    }

    #[Test]
    public function events_returns_null_by_default(): void
    {
        $container = new Container();
        $app = new Application($container);

        $this->assertNull($app->events());
    }

    #[Test]
    public function set_events_with_null_returns_self(): void
    {
        $container = new Container();
        $app = new Application($container);

        $result = $app->setEvents(null);

        $this->assertSame($app, $result);
        $this->assertNull($app->events());
    }

    #[Test]
    public function resolve_resolves_from_container(): void
    {
        $container = new Container();
        $container->bind('test.service', fn() => new \stdClass());

        $app = new Application($container);

        $service = $app->resolve('test.service');

        $this->assertInstanceOf(\stdClass::class, $service);
    }

    #[Test]
    public function resolve_passes_parameters_to_container(): void
    {
        $container = new Container();
        $app = new Application($container);

        // Register a class that accepts parameters
        $container->bind('test.parameterized', function ($container, $params) {
            $obj = new \stdClass();
            $obj->value = $params['value'] ?? 'default';
            return $obj;
        });

        $service = $app->resolve('test.parameterized', ['value' => 'custom']);

        $this->assertSame('custom', $service->value);
    }

    #[Test]
    public function set_instance_with_null_clears_instance(): void
    {
        $container = new Container();
        $app = new Application($container);

        Application::setInstance($app);
        $this->assertTrue(Application::hasInstance());

        Application::setInstance(null);
        $this->assertFalse(Application::hasInstance());
    }
}
