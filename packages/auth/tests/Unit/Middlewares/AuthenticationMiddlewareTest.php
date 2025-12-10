<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Unit\Middlewares;

use Lalaz\Auth\Tests\Common\AuthUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Lalaz\Auth\Middlewares\AuthenticationMiddleware;
use Lalaz\Auth\AuthContext;
use Lalaz\Auth\AuthManager;
use Lalaz\Auth\Contracts\GuardInterface;
use Lalaz\Auth\Contracts\SessionInterface;
use Lalaz\Auth\Tests\Common\StubRequest;
use Lalaz\Exceptions\HttpException;
use Lalaz\Web\Http\Contracts\ResponseInterface;

class AuthenticationMiddlewareTest extends AuthUnitTestCase
{
    private AuthManager&MockObject $authManager;
    private AuthContext $authContext;
    private SessionInterface&MockObject $session;
    private StubRequest $request;
    private ResponseInterface&MockObject $response;
    private GuardInterface&MockObject $guard;
    private bool $nextCalled;
    private mixed $nextArgs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authManager = $this->createMock(AuthManager::class);
        $this->authContext = new AuthContext();
        $this->session = $this->createMock(SessionInterface::class);

        // Use stub instead of mock (RequestInterface has method() which cannot be mocked)
        $this->request = new StubRequest();

        $this->response = $this->createMock(ResponseInterface::class);
        $this->guard = $this->createMock(GuardInterface::class);

        $this->nextCalled = false;
        $this->nextArgs = null;
    }

    private function createNext(): callable
    {
        return function ($req, $res) {
            $this->nextCalled = true;
            $this->nextArgs = [$req, $res];
        };
    }

    // ===== Constructor and Basic Setup =====

    public function test_default_guard_is_web(): void
    {
        $middleware = new AuthenticationMiddleware();
        $this->assertSame('web', $middleware->getGuardName());
    }

    public function test_constructor_accepts_guard_name(): void
    {
        $middleware = new AuthenticationMiddleware('api');
        $this->assertSame('api', $middleware->getGuardName());
    }

    public function test_constructor_accepts_login_url(): void
    {
        $middleware = new AuthenticationMiddleware('web', '/login');
        $this->assertSame('web', $middleware->getGuardName());
    }

    public function test_constructor_accepts_all_dependencies(): void
    {
        $middleware = new AuthenticationMiddleware(
            guard: 'jwt',
            loginUrl: '/auth',
            authManager: $this->authManager,
            authContext: $this->authContext,
            session: $this->session
        );

        $this->assertSame('jwt', $middleware->getGuardName());
    }

    // ===== Factory Methods =====

    public function test_session_factory_creates_session_guard(): void
    {
        $middleware = AuthenticationMiddleware::session();
        $this->assertSame('session', $middleware->getGuardName());
    }

    public function test_session_factory_accepts_login_url(): void
    {
        $middleware = AuthenticationMiddleware::session('/login');
        $this->assertSame('session', $middleware->getGuardName());
    }

    public function test_web_factory_creates_web_guard(): void
    {
        $middleware = AuthenticationMiddleware::web();
        $this->assertSame('web', $middleware->getGuardName());
    }

    public function test_web_factory_accepts_login_url(): void
    {
        $middleware = AuthenticationMiddleware::web('/signin');
        $this->assertSame('web', $middleware->getGuardName());
    }

    public function test_jwt_factory_creates_jwt_guard(): void
    {
        $middleware = AuthenticationMiddleware::jwt();
        $this->assertSame('jwt', $middleware->getGuardName());
    }

    public function test_api_factory_creates_api_guard(): void
    {
        $middleware = AuthenticationMiddleware::api();
        $this->assertSame('api', $middleware->getGuardName());
    }

    public function test_api_key_factory_creates_api_key_guard(): void
    {
        $middleware = AuthenticationMiddleware::apiKey();
        $this->assertSame('api-key', $middleware->getGuardName());
    }

    public function test_guard_factory_creates_custom_guard(): void
    {
        $middleware = AuthenticationMiddleware::guard('custom');
        $this->assertSame('custom', $middleware->getGuardName());
    }

    public function test_guard_factory_accepts_login_url(): void
    {
        $middleware = AuthenticationMiddleware::guard('oauth', '/oauth/login');
        $this->assertSame('oauth', $middleware->getGuardName());
    }

    public function test_redirect_to_factory_sets_url_and_guard(): void
    {
        $middleware = AuthenticationMiddleware::redirectTo('/login', 'session');
        $this->assertSame('session', $middleware->getGuardName());
    }

    public function test_redirect_to_factory_defaults_to_web_guard(): void
    {
        $middleware = AuthenticationMiddleware::redirectTo('/auth');
        $this->assertSame('web', $middleware->getGuardName());
    }

    public function test_strict_factory_creates_guard_without_redirect(): void
    {
        $middleware = AuthenticationMiddleware::strict('api');
        $this->assertSame('api', $middleware->getGuardName());
    }

    public function test_strict_factory_defaults_to_web_guard(): void
    {
        $middleware = AuthenticationMiddleware::strict();
        $this->assertSame('web', $middleware->getGuardName());
    }

    // ===== Setters =====

    public function test_set_auth_manager_returns_self(): void
    {
        $middleware = new AuthenticationMiddleware();
        $result = $middleware->setAuthManager($this->authManager);
        $this->assertSame($middleware, $result);
    }

    public function test_set_auth_context_returns_self(): void
    {
        $middleware = new AuthenticationMiddleware();
        $result = $middleware->setAuthContext($this->authContext);
        $this->assertSame($middleware, $result);
    }

    public function test_set_session_returns_self(): void
    {
        $middleware = new AuthenticationMiddleware();
        $result = $middleware->setSession($this->session);
        $this->assertSame($middleware, $result);
    }

    // ===== Handle - Authenticated User =====

    public function test_handle_calls_next_when_authenticated(): void
    {
        $user = (object) ['id' => 1, 'name' => 'Test User'];

        $this->guard->expects($this->once())
            ->method('check')
            ->willReturn(true);

        $this->guard->expects($this->once())
            ->method('user')
            ->willReturn($user);

        $this->authManager->expects($this->once())
            ->method('hasGuard')
            ->with('jwt')
            ->willReturn(true);

        $this->authManager->expects($this->once())
            ->method('guard')
            ->with('jwt')
            ->willReturn($this->guard);

        $middleware = new AuthenticationMiddleware(
            guard: 'jwt',
            authManager: $this->authManager,
            authContext: $this->authContext,
        );

        $middleware->handle($this->request, $this->response, $this->createNext());

        $this->assertTrue($this->nextCalled);
    }

    public function test_handle_sets_user_on_auth_context(): void
    {
        $user = (object) ['id' => 42, 'email' => 'test@example.com'];

        $this->guard->expects($this->once())
            ->method('check')
            ->willReturn(true);

        $this->guard->expects($this->once())
            ->method('user')
            ->willReturn($user);

        $this->authManager->expects($this->once())
            ->method('hasGuard')
            ->with('api')
            ->willReturn(true);

        $this->authManager->expects($this->once())
            ->method('guard')
            ->with('api')
            ->willReturn($this->guard);

        $middleware = new AuthenticationMiddleware(
            guard: 'api',
            authManager: $this->authManager,
            authContext: $this->authContext,
        );

        $middleware->handle($this->request, $this->response, $this->createNext());

        $this->assertSame($user, $this->authContext->user('api'));
    }

    public function test_handle_sets_current_guard_on_context(): void
    {
        $user = (object) ['id' => 1];

        $this->guard->expects($this->once())
            ->method('check')
            ->willReturn(true);

        $this->guard->expects($this->once())
            ->method('user')
            ->willReturn($user);

        $this->authManager->expects($this->once())
            ->method('hasGuard')
            ->with('custom')
            ->willReturn(true);

        $this->authManager->expects($this->once())
            ->method('guard')
            ->with('custom')
            ->willReturn($this->guard);

        $middleware = new AuthenticationMiddleware(
            guard: 'custom',
            authManager: $this->authManager,
            authContext: $this->authContext,
        );

        $middleware->handle($this->request, $this->response, $this->createNext());

        $this->assertSame('custom', $this->authContext->getCurrentGuard());
    }

    public function test_handle_sets_user_on_request(): void
    {
        $user = (object) ['id' => 1, 'name' => 'John'];

        $this->guard->expects($this->once())
            ->method('check')
            ->willReturn(true);

        $this->guard->expects($this->once())
            ->method('user')
            ->willReturn($user);

        $this->authManager->expects($this->once())
            ->method('hasGuard')
            ->with('jwt')
            ->willReturn(true);

        $this->authManager->expects($this->once())
            ->method('guard')
            ->with('jwt')
            ->willReturn($this->guard);

        $middleware = new AuthenticationMiddleware(
            guard: 'jwt',
            authManager: $this->authManager,
            authContext: $this->authContext,
        );

        $middleware->handle($this->request, $this->response, $this->createNext());

        $this->assertSame($user, $this->request->user);
    }

    // ===== Handle - Unauthenticated User =====

    public function test_handle_throws_401_when_not_authenticated_and_no_redirect(): void
    {
        $this->guard->expects($this->once())
            ->method('check')
            ->willReturn(false);

        $this->authManager->expects($this->once())
            ->method('hasGuard')
            ->with('jwt')
            ->willReturn(true);

        $this->authManager->expects($this->once())
            ->method('guard')
            ->with('jwt')
            ->willReturn($this->guard);

        $middleware = new AuthenticationMiddleware(
            guard: 'jwt',
            authManager: $this->authManager,
            authContext: $this->authContext,
        );

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Authentication required');

        $middleware->handle($this->request, $this->response, $this->createNext());
    }

    public function test_handle_redirects_when_not_authenticated_with_login_url(): void
    {
        $this->guard->expects($this->once())
            ->method('check')
            ->willReturn(false);

        $this->authManager->expects($this->once())
            ->method('hasGuard')
            ->with('web')
            ->willReturn(true);

        $this->authManager->expects($this->once())
            ->method('guard')
            ->with('web')
            ->willReturn($this->guard);

        $this->response->expects($this->once())
            ->method('redirect')
            ->with('/login');

        $middleware = new AuthenticationMiddleware(
            guard: 'web',
            loginUrl: '/login',
            authManager: $this->authManager,
            authContext: $this->authContext,
        );

        $middleware->handle($this->request, $this->response, $this->createNext());

        $this->assertFalse($this->nextCalled);
    }

    public function test_handle_does_not_call_next_when_redirecting(): void
    {
        $this->guard->expects($this->once())
            ->method('check')
            ->willReturn(false);

        $this->authManager->expects($this->once())
            ->method('hasGuard')
            ->with('session')
            ->willReturn(true);

        $this->authManager->expects($this->once())
            ->method('guard')
            ->with('session')
            ->willReturn($this->guard);

        $this->response->expects($this->once())
            ->method('redirect')
            ->with('/auth/login');

        $middleware = new AuthenticationMiddleware(
            guard: 'session',
            loginUrl: '/auth/login',
            authManager: $this->authManager,
            authContext: $this->authContext,
        );

        $middleware->handle($this->request, $this->response, $this->createNext());

        $this->assertFalse($this->nextCalled);
    }

    // ===== Guard Not Found =====

    public function test_handle_fallback_to_session_for_web_guard(): void
    {
        $user = ['id' => 1, 'name' => 'Session User'];

        $this->authManager->expects($this->once())
            ->method('hasGuard')
            ->with('web')
            ->willReturn(false);

        $this->session->expects($this->once())
            ->method('get')
            ->with('__luser')
            ->willReturn($user);

        $middleware = new AuthenticationMiddleware(
            guard: 'web',
            authManager: $this->authManager,
            authContext: $this->authContext,
            session: $this->session,
        );

        $middleware->handle($this->request, $this->response, $this->createNext());

        $this->assertTrue($this->nextCalled);
        $this->assertSame($user, $this->request->user);
    }

    public function test_handle_fallback_to_session_for_session_guard(): void
    {
        $user = (object) ['id' => 99];

        $this->authManager->expects($this->once())
            ->method('hasGuard')
            ->with('session')
            ->willReturn(false);

        $this->session->expects($this->once())
            ->method('get')
            ->with('__luser')
            ->willReturn($user);

        $middleware = new AuthenticationMiddleware(
            guard: 'session',
            authManager: $this->authManager,
            authContext: $this->authContext,
            session: $this->session,
        );

        $middleware->handle($this->request, $this->response, $this->createNext());

        $this->assertTrue($this->nextCalled);
    }

    public function test_handle_throws_401_for_non_web_guard_without_manager(): void
    {
        $this->authManager->expects($this->once())
            ->method('hasGuard')
            ->with('api')
            ->willReturn(false);

        $middleware = new AuthenticationMiddleware(
            guard: 'api',
            authManager: $this->authManager,
            authContext: $this->authContext,
        );

        $this->expectException(HttpException::class);

        $middleware->handle($this->request, $this->response, $this->createNext());
    }

    // ===== Session Fallback =====

    public function test_handle_returns_null_when_session_user_not_found(): void
    {
        $this->authManager->expects($this->once())
            ->method('hasGuard')
            ->with('web')
            ->willReturn(false);

        $this->session->expects($this->once())
            ->method('get')
            ->with('__luser')
            ->willReturn(null);

        $middleware = new AuthenticationMiddleware(
            guard: 'web',
            loginUrl: '/signin',
            authManager: $this->authManager,
            authContext: $this->authContext,
            session: $this->session,
        );

        $this->response->expects($this->once())
            ->method('redirect')
            ->with('/signin');

        $middleware->handle($this->request, $this->response, $this->createNext());

        $this->assertFalse($this->nextCalled);
    }

    // ===== Multiple Guards =====

    public function test_different_factory_methods_produce_different_guards(): void
    {
        $session = AuthenticationMiddleware::session();
        $jwt = AuthenticationMiddleware::jwt();
        $apiKey = AuthenticationMiddleware::apiKey();
        $api = AuthenticationMiddleware::api();
        $web = AuthenticationMiddleware::web();

        $this->assertSame('session', $session->getGuardName());
        $this->assertSame('jwt', $jwt->getGuardName());
        $this->assertSame('api-key', $apiKey->getGuardName());
        $this->assertSame('api', $api->getGuardName());
        $this->assertSame('web', $web->getGuardName());
    }

    // ===== Edge Cases =====

    public function test_handle_with_null_user_from_guard(): void
    {
        $this->guard->expects($this->once())
            ->method('check')
            ->willReturn(false);

        $this->authManager->expects($this->once())
            ->method('hasGuard')
            ->with('jwt')
            ->willReturn(true);

        $this->authManager->expects($this->once())
            ->method('guard')
            ->with('jwt')
            ->willReturn($this->guard);

        $middleware = new AuthenticationMiddleware(
            guard: 'jwt',
            loginUrl: '/api/auth',
            authManager: $this->authManager,
            authContext: $this->authContext,
        );

        $this->response->expects($this->once())
            ->method('redirect')
            ->with('/api/auth');

        $middleware->handle($this->request, $this->response, $this->createNext());

        $this->assertFalse($this->nextCalled);
    }

    public function test_authenticated_user_with_array_shape(): void
    {
        $user = ['id' => 123, 'email' => 'array@user.com'];

        $this->guard->expects($this->once())
            ->method('check')
            ->willReturn(true);

        $this->guard->expects($this->once())
            ->method('user')
            ->willReturn($user);

        $this->authManager->expects($this->once())
            ->method('hasGuard')
            ->with('api')
            ->willReturn(true);

        $this->authManager->expects($this->once())
            ->method('guard')
            ->with('api')
            ->willReturn($this->guard);

        $middleware = new AuthenticationMiddleware(
            guard: 'api',
            authManager: $this->authManager,
            authContext: $this->authContext,
        );

        $middleware->handle($this->request, $this->response, $this->createNext());

        $this->assertTrue($this->nextCalled);
        $this->assertSame($user, $this->authContext->user('api'));
    }
}
