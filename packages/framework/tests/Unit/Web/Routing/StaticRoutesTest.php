<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Web\Routing;

use Lalaz\Web\Routing\Router;
use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Router::class)]
final class StaticRoutesTest extends FrameworkUnitTestCase
{
    public function teststaticRouteExactMatch(): void
    {
        $router = new Router();

        $router->get('/health', fn() => 'ok');
        $router->get('/about', fn() => 'about');

        $match1 = $router->match('GET', '/health');
        $match2 = $router->match('GET', '/about');

        $this->assertSame('/health', $match1->definition()->path());
        $this->assertSame('/about', $match2->definition()->path());
    }

    public function testheadFallsBackToGetForStaticRoute(): void
    {
        $router = new Router();

        $router->get('/ping', fn() => 'pong');

        $head = $router->match('HEAD', '/ping');

        $this->assertSame('/ping', $head->definition()->path());
        $this->assertSame('GET', $head->definition()->method());
    }

    public function testlastRegistrationWinsForStaticRoute(): void
    {
        $router = new Router();

        $router->get('/dup', fn() => 'first');
        $router->get('/dup', fn() => 'second');

        $match = $router->match('GET', '/dup');

        // Expect the last registration to be the effective handler
        $this->assertSame('/dup', $match->definition()->path());
    }

    public function testgroupedStaticRouteRegistration(): void
    {
        $router = new Router();

        $router->group('/api', function ($r) {
            $r->get('/status', fn() => 'ok');
        });

        $match = $router->match('GET', '/api/status');

        $this->assertSame('/api/status', $match->definition()->path());
    }
}
