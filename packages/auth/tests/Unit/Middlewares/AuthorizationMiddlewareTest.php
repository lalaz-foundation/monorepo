<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Unit\Middlewares;

use PHPUnit\Framework\Attributes\CoversClass;

use Lalaz\Auth\AuthContext;
use Lalaz\Auth\Middlewares\AuthorizationMiddleware;
use Lalaz\Auth\Tests\Common\AuthUnitTestCase;
use Lalaz\Auth\Tests\Common\FakeRequest;
use Lalaz\Auth\Tests\Common\FakeResponse;
use Lalaz\Exceptions\HttpException;

#[CoversClass(\Lalaz\Auth\Middlewares\AuthorizationMiddleware::class)]
final class AuthorizationMiddlewareTest extends AuthUnitTestCase
{
    // =========================================================================
    // Authenticated Access with Required Roles
    // =========================================================================

    public function testAllowsAuthenticatedUserWithRequiredRole(): void
    {
        $user = $this->fakeUser(1, 'password', ['admin']);
        $context = $this->createAuthContext();
        $context->setUser($user);

        $middleware = new AuthorizationMiddleware(['admin'], null, $context);

        $request = new FakeRequest();
        $response = new FakeResponse();
        $nextCalled = false;

        $middleware->handle($request, $response, function ($req, $res) use (&$nextCalled) {
            $nextCalled = true;
        });

        $this->assertTrue($nextCalled);
    }

    public function testThrowsForbiddenForUnauthenticatedUser(): void
    {
        $this->expectException(HttpException::class);

        $context = $this->createAuthContext();

        $middleware = new AuthorizationMiddleware(['admin'], null, $context);

        $request = new FakeRequest();
        $response = new FakeResponse();

        $middleware->handle($request, $response, function ($req, $res) {
            // Should not be called
        });
    }

    // =========================================================================
    // Role-Based Access
    // =========================================================================

    public function testAllowsUserWithRequiredRole(): void
    {
        $user = $this->fakeUser(1, 'password', ['editor']);
        $context = $this->createAuthContext();
        $context->setUser($user);

        $middleware = new AuthorizationMiddleware(['editor'], null, $context);

        $request = new FakeRequest();
        $response = new FakeResponse();
        $nextCalled = false;

        $middleware->handle($request, $response, function ($req, $res) use (&$nextCalled) {
            $nextCalled = true;
        });

        $this->assertTrue($nextCalled);
    }

    public function testThrowsForbiddenForUserWithoutRequiredRole(): void
    {
        $this->expectException(HttpException::class);

        $user = $this->fakeUser(1, 'password', ['user']);
        $context = $this->createAuthContext();
        $context->setUser($user);

        $middleware = new AuthorizationMiddleware(['admin'], null, $context);

        $request = new FakeRequest();
        $response = new FakeResponse();

        $middleware->handle($request, $response, function ($req, $res) {
            // Should not be called
        });
    }

    // =========================================================================
    // Multiple Roles
    // =========================================================================

    public function testAllowsUserWithAnyOfTheRequiredRoles(): void
    {
        $user = $this->fakeUser(1, 'password', ['moderator']);
        $context = $this->createAuthContext();
        $context->setUser($user);

        $middleware = new AuthorizationMiddleware(['admin', 'moderator', 'editor'], null, $context);

        $request = new FakeRequest();
        $response = new FakeResponse();
        $nextCalled = false;

        $middleware->handle($request, $response, function ($req, $res) use (&$nextCalled) {
            $nextCalled = true;
        });

        $this->assertTrue($nextCalled);
    }

    public function testThrowsForbiddenForUserWithNoneOfTheRequiredRoles(): void
    {
        $this->expectException(HttpException::class);

        $user = $this->fakeUser(1, 'password', ['guest']);
        $context = $this->createAuthContext();
        $context->setUser($user);

        $middleware = new AuthorizationMiddleware(['admin', 'editor'], null, $context);

        $request = new FakeRequest();
        $response = new FakeResponse();

        $middleware->handle($request, $response, function ($req, $res) {
            // Should not be called
        });
    }

    // =========================================================================
    // No Role Requirement
    // =========================================================================

    public function testAllowsAnyUserWhenNoRolesRequired(): void
    {
        $user = $this->fakeUser(1, 'password', []);
        $context = $this->createAuthContext();
        $context->setUser($user);

        $middleware = new AuthorizationMiddleware([], null, $context);

        $request = new FakeRequest();
        $response = new FakeResponse();
        $nextCalled = false;

        $middleware->handle($request, $response, function ($req, $res) use (&$nextCalled) {
            $nextCalled = true;
        });

        $this->assertTrue($nextCalled);
    }

    // =========================================================================
    // Factory Methods
    // =========================================================================

    public function testRequireRolesFactoryMethod(): void
    {
        $middleware = AuthorizationMiddleware::requireRoles('admin', 'moderator');

        $this->assertInstanceOf(AuthorizationMiddleware::class, $middleware);
    }

    public function testAdminFactoryMethod(): void
    {
        $middleware = AuthorizationMiddleware::admin();

        $this->assertInstanceOf(AuthorizationMiddleware::class, $middleware);
    }

    public function testModeratorFactoryMethod(): void
    {
        $middleware = AuthorizationMiddleware::moderator();

        $this->assertInstanceOf(AuthorizationMiddleware::class, $middleware);
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    private function createAuthContext(): AuthContext
    {
        return new AuthContext();
    }
}
