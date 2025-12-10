<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Web\Routing;

use Lalaz\Web\Routing\Router;
use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Router::class)]
final class MultiKeyIndexTest extends FrameworkUnitTestCase
{
    public function testmultiKeyBucketsSeparateRoutes(): void
    {
        $router = new Router();

        $router->get('/dynamic/route/1/{id}', fn() => 'r1');
        $router->get('/dynamic/route/2/{id}', fn() => 'r2');
        $router->get('/dynamic/route/3/{id}', fn() => 'r3');

        $match = $router->match('GET', '/dynamic/route/2/123');

        $this->assertSame('/dynamic/route/2/{id}', $match->definition()->path());
    }

    public function testwildcardPrefixBucketFallsBackToScan(): void
    {
        $router = new Router();

        $router->get('/{locale}/posts/{id}', fn() => 'post');

        $match = $router->match('GET', '/en/posts/123');

        $this->assertSame('/{locale}/posts/{id}', $match->definition()->path());
    }
}
