<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Web\Routing;

use Lalaz\Web\Routing\Router;
use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Router::class)]
final class DynamicIndexTest extends FrameworkUnitTestCase
{
    public function testdynamicRoutesBucketedBySegmentCount(): void
    {
        $router = new Router();

        $router->get('/users/{id}', fn() => 'user');
        $router->get('/users/{id}/posts', fn() => 'posts');

        $matchA = $router->match('GET', '/users/42');
        $matchB = $router->match('GET', '/users/42/posts');

        $this->assertSame('/users/{id}', $matchA->definition()->path());
        $this->assertSame('/users/{id}/posts', $matchB->definition()->path());
    }

    public function testsplatPatternRoutesAreChecked(): void
    {
        $router = new Router();

        // simple splat capturing multiple segments
        $router->get('/files/{path:.+}', fn() => 'files');

        $match = $router->match('GET', '/files/a/b/c.txt');

        $this->assertSame('/files/{path:.+}', $match->definition()->path());
    }

    public function testfirstStaticSegmentBucketsSeparateRoutes(): void
    {
        $router = new Router();

        $router->get('/users/{id}', fn() => 'user');
        $router->get('/orders/{id}', fn() => 'order');

        $a = $router->match('GET', '/users/10');
        $b = $router->match('GET', '/orders/99');

        $this->assertSame('/users/{id}', $a->definition()->path());
        $this->assertSame('/orders/{id}', $b->definition()->path());
    }

    public function testfirstStaticSegmentDeeperInPath(): void
    {
        $router = new Router();

        // first literal is the second segment 'posts'
        $router->get('/{locale}/posts/{id}', fn() => 'post');

        $match = $router->match('GET', '/en/posts/123');

        $this->assertSame('/{locale}/posts/{id}', $match->definition()->path());
    }
}
