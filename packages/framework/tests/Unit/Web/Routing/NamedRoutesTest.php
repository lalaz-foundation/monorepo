<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Web\Routing;

use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Lalaz\Web\Routing\RouteDefinition;
use Lalaz\Web\Routing\Router;
use Lalaz\Web\Routing\RouteUrlGenerator;
use Lalaz\Exceptions\RoutingException;

class NamedRoutesTest extends FrameworkUnitTestCase
{
    private Router $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new Router();
    }

    // ============================================
    // Named Routes Tests
    // ============================================

    public function testrouteCanBeNamedWithNameMethod(): void
    {
        $route = $this->router->get('/test', fn() => 'test');
        $result = $route->name('test.route');

        $this->assertInstanceOf(RouteDefinition::class, $result);
        $this->assertSame('test.route', $route->getName());
    }

    public function testrouteCanBeNamedWithAsMethod(): void
    {
        $route = $this->router->get('/test', fn() => 'test');
        $result = $route->as('test.route');

        $this->assertInstanceOf(RouteDefinition::class, $result);
        $this->assertSame('test.route', $route->getName());
    }

    public function testnamedRouteCanBeFoundByName(): void
    {
        $this->router->get('/test', fn() => 'test')->name('test.route');

        $route = $this->router->findRouteByName('test.route');

        $this->assertNotNull($route);
        $this->assertSame('test.route', $route->getName());
    }

    public function testunnamedRouteCannotBeFoundByName(): void
    {
        $this->router->get('/test', fn() => 'test');

        $route = $this->router->findRouteByName('non.existent');

        $this->assertNull($route);
    }

    // ============================================
    // URL Generator Tests
    // ============================================

    public function testurlGeneratorGeneratesSimpleUrl(): void
    {
        $this->router->get('/users', fn() => 'users')->name('users.index');
        $generator = new RouteUrlGenerator($this->router);

        $url = $generator->route('users.index');

        $this->assertSame('/users', $url);
    }

    public function testurlGeneratorGeneratesUrlWithSingleParameter(): void
    {
        $this->router->get('/users/{id}', fn() => 'user')->name('users.show');
        $generator = new RouteUrlGenerator($this->router);

        $url = $generator->route('users.show', ['id' => 123]);

        $this->assertSame('/users/123', $url);
    }

    public function testurlGeneratorGeneratesUrlWithMultipleParameters(): void
    {
        $this->router->get('/users/{userId}/posts/{postId}', fn() => 'post')->name('users.posts.show');
        $generator = new RouteUrlGenerator($this->router);

        $url = $generator->route('users.posts.show', ['userId' => 1, 'postId' => 42]);

        $this->assertSame('/users/1/posts/42', $url);
    }

    public function testurlGeneratorAppendsExtraParamsAsQueryString(): void
    {
        $this->router->get('/users', fn() => 'users')->name('users.index');
        $generator = new RouteUrlGenerator($this->router);

        $url = $generator->route('users.index', ['page' => 2, 'sort' => 'name']);

        $this->assertSame('/users?page=2&sort=name', $url);
    }

    public function testurlGeneratorCombinesRouteParamsAndQueryString(): void
    {
        $this->router->get('/users/{id}', fn() => 'user')->name('users.show');
        $generator = new RouteUrlGenerator($this->router);

        $url = $generator->route('users.show', ['id' => 123, 'tab' => 'posts']);

        $this->assertSame('/users/123?tab=posts', $url);
    }

    public function testurlGeneratorReturnsNullForNonExistentRoute(): void
    {
        $generator = new RouteUrlGenerator($this->router);

        $url = $generator->routeOrNull('non.existent');

        $this->assertNull($url);
    }

    public function testurlGeneratorThrowsExceptionForNonExistentRoute(): void
    {
        $generator = new RouteUrlGenerator($this->router);

        $this->expectException(RoutingException::class);
        $this->expectExceptionMessage("Route 'non.existent' is not defined");

        $generator->route('non.existent');
    }
}
