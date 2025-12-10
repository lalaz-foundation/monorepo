<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Web\Routing;

use Lalaz\Exceptions\HttpException;
use Lalaz\Web\Routing\Router;
use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(HttpException::class)]
/**
 * Tests for the Router class.
 */
final class RouterTest extends FrameworkUnitTestCase
{
    public function testmatchesRoutesWithParameters(): void
    {
        $router = new Router();
        $router->get("/users/{id}", fn() => null);

        $matched = $router->match("GET", "/users/42");

        $this->assertSame(["id" => "42"], $matched->params());
    }

    public function testthrowsMethodNotAllowedWhenPathExistsWithDifferentMethod(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage("Method not allowed.");

        $router = new Router();
        $router->post("/users", fn() => null);

        $router->match("POST", "/users"); // sanity check
        $router->match("GET", "/users");
    }

    public function testthrowsNotFoundWhenRouteMissing(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage("Route not found");

        $router = new Router();
        $router->match("GET", "/missing");
    }

    public function testexportsAndLoadsRouteDefinitions(): void
    {
        $router = new Router();
        $router->get("/cache", [CachedController::class, "index"]);

        $definitions = $router->exportDefinitions();
        $this->assertCount(1, $definitions);

        $newRouter = new Router();
        $newRouter->loadFromDefinitions($definitions);

        $match = $newRouter->match("GET", "/cache");
        $this->assertSame([CachedController::class, "index"], $match->definition()->handler());
    }

    // ============================================
    // Router OCP Compliance Tests
    // ============================================

    public function testregistersMultipleMethodsAtOnceViaMethods(): void
    {
        $router = new Router();

        $definitions = $router->methods(
            ['GET', 'POST'],
            '/resource',
            fn() => 'handled'
        );

        $this->assertCount(2, $definitions);

        $getMatch = $router->match('GET', '/resource');
        $postMatch = $router->match('POST', '/resource');

        $this->assertSame('GET', $getMatch->definition()->method());
        $this->assertSame('POST', $postMatch->definition()->method());
    }

    public function testsupportsCustomHttpMethodsLikePurge(): void
    {
        $router = new Router();

        $router->route('PURGE', '/cache/{key}', fn() => 'purged');

        $matched = $router->match('PURGE', '/cache/users');

        $this->assertSame('PURGE', $matched->definition()->method());
        $this->assertSame(['key' => 'users'], $matched->params());
    }

    public function testsupportsWebdavMethods(): void
    {
        $router = new Router();

        $router->methods(
            ['PROPFIND', 'LOCK', 'UNLOCK'],
            '/webdav/{resource}',
            fn() => 'webdav'
        );

        $propfind = $router->match('PROPFIND', '/webdav/file.txt');
        $lock = $router->match('LOCK', '/webdav/file.txt');
        $unlock = $router->match('UNLOCK', '/webdav/file.txt');

        $this->assertSame('PROPFIND', $propfind->definition()->method());
        $this->assertSame('LOCK', $lock->definition()->method());
        $this->assertSame('UNLOCK', $unlock->definition()->method());
    }

    public function testusesStandardMethodsConstantForAny(): void
    {
        $router = new Router();
        $router->any('/api', fn() => 'any');

        $this->assertCount(count(Router::STANDARD_METHODS), $router->all());

        foreach (Router::STANDARD_METHODS as $method) {
            $matched = $router->match($method, '/api');
            $this->assertSame($method, $matched->definition()->method());
        }
    }

    public function testappliesMiddlewaresToCustomMethods(): void
    {
        $router = new Router();

        $definitions = $router->methods(
            ['PURGE', 'BAN'],
            '/cache',
            fn() => 'cache',
            ['CacheMiddleware']
        );

        $this->assertSame(['CacheMiddleware'], $definitions[0]->middlewares());
        $this->assertSame(['CacheMiddleware'], $definitions[1]->middlewares());
    }

    public function testreportsAllowedMethodsForCustomMethods(): void
    {
        $router = new Router();

        $router->route('PURGE', '/cache', fn() => 'purge');
        $router->route('BAN', '/cache', fn() => 'ban');

        try {
            $router->match('GET', '/cache');
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame('Method not allowed.', $e->getMessage());
            $this->assertContains('PURGE', $e->getContext()['allowed']);
            $this->assertContains('BAN', $e->getContext()['allowed']);
        }
    }

    public function testnormalizesCustomMethodNamesToUppercase(): void
    {
        $router = new Router();

        $router->route('purge', '/cache', fn() => 'purged');

        $matched = $router->match('PURGE', '/cache');

        $this->assertSame('PURGE', $matched->definition()->method());
    }

    // ============================================
    // Route Groups Tests
    // ============================================

    public function testcreatesRouteGroupWithPrefix(): void
    {
        $router = new Router();

        $router->group('/api', function ($r) {
            $r->get('/users', fn() => 'users');
            $r->get('/posts', fn() => 'posts');
        });

        $usersMatch = $router->match('GET', '/api/users');
        $postsMatch = $router->match('GET', '/api/posts');

        $this->assertSame('/api/users', $usersMatch->definition()->path());
        $this->assertSame('/api/posts', $postsMatch->definition()->path());
    }

    public function testsupportsNestedGroups(): void
    {
        $router = new Router();

        $router->group('/api', function ($r) {
            $r->group('/v1', function ($r) {
                $r->get('/users', fn() => 'v1 users');
            });
            $r->group('/v2', function ($r) {
                $r->get('/users', fn() => 'v2 users');
            });
        });

        $v1Match = $router->match('GET', '/api/v1/users');
        $v2Match = $router->match('GET', '/api/v2/users');

        $this->assertSame('/api/v1/users', $v1Match->definition()->path());
        $this->assertSame('/api/v2/users', $v2Match->definition()->path());
    }

    public function testappliesMiddlewareToAllRoutesInGroup(): void
    {
        $router = new Router();

        $router->group('/admin', function ($r) {
            $r->get('/dashboard', fn() => 'dashboard');
            $r->get('/settings', fn() => 'settings');
        })->middleware('AuthMiddleware');

        $dashboard = $router->match('GET', '/admin/dashboard');
        $settings = $router->match('GET', '/admin/settings');

        $this->assertContains('AuthMiddleware', $dashboard->definition()->middlewares());
        $this->assertContains('AuthMiddleware', $settings->definition()->middlewares());
    }

    public function testappliesMultipleMiddlewaresToGroup(): void
    {
        $router = new Router();

        $router->group('/api', function ($r) {
            $r->get('/data', fn() => 'data');
        })->middlewares(['RateLimitMiddleware', 'CorsMiddleware']);

        $match = $router->match('GET', '/api/data');

        $this->assertContains('RateLimitMiddleware', $match->definition()->middlewares());
        $this->assertContains('CorsMiddleware', $match->definition()->middlewares());
    }

    public function testchainsMiddlewareCallsOnGroup(): void
    {
        $router = new Router();

        $router->group('/secure', function ($r) {
            $r->get('/resource', fn() => 'resource');
        })
        ->middleware('AuthMiddleware')
        ->middleware('LogMiddleware');

        $match = $router->match('GET', '/secure/resource');

        $this->assertContains('AuthMiddleware', $match->definition()->middlewares());
        $this->assertContains('LogMiddleware', $match->definition()->middlewares());
    }

    public function testdoesNotAffectRoutesOutsideGroup(): void
    {
        $router = new Router();

        $router->get('/public', fn() => 'public');

        $router->group('/private', function ($r) {
            $r->get('/secret', fn() => 'secret');
        })->middleware('AuthMiddleware');

        $public = $router->match('GET', '/public');
        $private = $router->match('GET', '/private/secret');

        $this->assertSame([], $public->definition()->middlewares());
        $this->assertContains('AuthMiddleware', $private->definition()->middlewares());
    }

    // ============================================
    // Resource Routes Tests
    // ============================================

    public function testcreatesAllCrudRoutesForResource(): void
    {
        $router = new Router();

        $router->resource('users', 'UserController');

        $routes = $router->all();

        $this->assertCount(8, $routes);

        // index
        $index = $router->match('GET', '/users');
        $this->assertSame(['UserController', 'index'], $index->definition()->handler());

        // create
        $create = $router->match('GET', '/users/create');
        $this->assertSame(['UserController', 'create'], $create->definition()->handler());

        // store
        $store = $router->match('POST', '/users');
        $this->assertSame(['UserController', 'store'], $store->definition()->handler());

        // show
        $show = $router->match('GET', '/users/123');
        $this->assertSame(['UserController', 'show'], $show->definition()->handler());
        $this->assertArrayHasKey('userId', $show->params());

        // edit
        $edit = $router->match('GET', '/users/123/edit');
        $this->assertSame(['UserController', 'edit'], $edit->definition()->handler());

        // update PUT
        $updatePut = $router->match('PUT', '/users/123');
        $this->assertSame(['UserController', 'update'], $updatePut->definition()->handler());

        // update PATCH
        $updatePatch = $router->match('PATCH', '/users/123');
        $this->assertSame(['UserController', 'update'], $updatePatch->definition()->handler());

        // destroy
        $destroy = $router->match('DELETE', '/users/123');
        $this->assertSame(['UserController', 'destroy'], $destroy->definition()->handler());
    }

    public function testfiltersResourceRoutesWithOnly(): void
    {
        $router = new Router();

        $router->resource('posts', 'PostController', only: ['index', 'show']);

        $routes = $router->all();
        $this->assertCount(2, $routes);

        $router->match('GET', '/posts');
        $router->match('GET', '/posts/1');
    }

    public function testexcludesResourceRoutesWithExcept(): void
    {
        $router = new Router();

        $router->resource('comments', 'CommentController', except: ['destroy']);

        $routes = $router->all();
        $this->assertCount(7, $routes); // All except destroy

        $this->expectException(HttpException::class);
        $router->match('DELETE', '/comments/1');
    }

    public function testappliesMiddlewareToResourceRoutes(): void
    {
        $router = new Router();

        $router->resource('products', 'ProductController')
            ->middleware('AuthMiddleware');

        $index = $router->match('GET', '/products');
        $show = $router->match('GET', '/products/1');
        $store = $router->match('POST', '/products');

        $this->assertContains('AuthMiddleware', $index->definition()->middlewares());
        $this->assertContains('AuthMiddleware', $show->definition()->middlewares());
        $this->assertContains('AuthMiddleware', $store->definition()->middlewares());
    }
}

class CachedController
{
    public function index(): void {}
}
