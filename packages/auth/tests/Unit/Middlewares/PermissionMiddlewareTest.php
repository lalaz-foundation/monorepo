<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Unit\Middlewares;

use PHPUnit\Framework\Attributes\CoversClass;

use Lalaz\Auth\AuthContext;
use Lalaz\Auth\Middlewares\PermissionMiddleware;
use Lalaz\Auth\Tests\Common\AuthUnitTestCase;

#[CoversClass(\Lalaz\Auth\Middlewares\PermissionMiddleware::class)]
final class PermissionMiddlewareTest extends AuthUnitTestCase
{
    // =========================================================================
    // Constructor and Factory Methods
    // =========================================================================

    public function testCreatesWithDefaultParameters(): void
    {
        $middleware = new PermissionMiddleware();

        $this->assertInstanceOf(PermissionMiddleware::class, $middleware);
    }

    public function testCreatesWithPermissions(): void
    {
        $middleware = new PermissionMiddleware(['edit', 'delete']);

        $this->assertInstanceOf(PermissionMiddleware::class, $middleware);
    }

    public function testAnyFactoryCreatesMiddleware(): void
    {
        $middleware = PermissionMiddleware::any('posts.create', 'posts.edit');

        $this->assertInstanceOf(PermissionMiddleware::class, $middleware);
    }

    public function testAllFactoryCreatesMiddleware(): void
    {
        $middleware = PermissionMiddleware::all('posts.create', 'posts.edit');

        $this->assertInstanceOf(PermissionMiddleware::class, $middleware);
    }

    public function testRedirectOnFailureFactoryCreatesMiddleware(): void
    {
        $middleware = PermissionMiddleware::redirectOnFailure(['admin.access'], '/unauthorized');

        $this->assertInstanceOf(PermissionMiddleware::class, $middleware);
    }

    public function testGuardFactoryCreatesMiddleware(): void
    {
        $middleware = PermissionMiddleware::guard('api', 'users.manage');

        $this->assertInstanceOf(PermissionMiddleware::class, $middleware);
    }

    // =========================================================================
    // ForGuard Method
    // =========================================================================

    public function testForGuardReturnsChainableSelf(): void
    {
        $middleware = new PermissionMiddleware(['edit']);

        $result = $middleware->forGuard('api');

        $this->assertSame($middleware, $result);
    }

    // =========================================================================
    // SetAuthContext Method
    // =========================================================================

    public function testSetAuthContextReturnsChainableSelf(): void
    {
        $middleware = new PermissionMiddleware(['edit']);
        $context = $this->createAuthContext();

        $result = $middleware->setAuthContext($context);

        $this->assertSame($middleware, $result);
    }

    // =========================================================================
    // Handle Method (via internal access)
    // =========================================================================

    public function testCreatesMiddlewareWithAuthContext(): void
    {
        $context = $this->createAuthContext();
        $user = $this->fakeUser(1, 'password', [], ['edit', 'delete']);
        $context->setUser($user);

        $middleware = new PermissionMiddleware(
            permissions: ['edit'],
            authContext: $context
        );

        $this->assertInstanceOf(PermissionMiddleware::class, $middleware);
    }

    public function testCreatesMiddlewareWithAllRequiredFlag(): void
    {
        $middleware = new PermissionMiddleware(
            permissions: ['edit', 'delete'],
            redirectUrl: null,
            requireAll: true
        );

        $this->assertInstanceOf(PermissionMiddleware::class, $middleware);
    }

    public function testCreatesMiddlewareWithRedirectUrl(): void
    {
        $middleware = new PermissionMiddleware(
            permissions: ['admin.access'],
            redirectUrl: '/login'
        );

        $this->assertInstanceOf(PermissionMiddleware::class, $middleware);
    }

    public function testCreatesMiddlewareWithGuard(): void
    {
        $middleware = new PermissionMiddleware(
            permissions: ['api.access'],
            redirectUrl: null,
            requireAll: false,
            guard: 'api'
        );

        $this->assertInstanceOf(PermissionMiddleware::class, $middleware);
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    private function createAuthContext(): AuthContext
    {
        $session = $this->fakeSession();
        $provider = $this->fakeUserProvider();

        return new AuthContext($session, $provider);
    }
}
