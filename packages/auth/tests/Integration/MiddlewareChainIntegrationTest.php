<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Integration;

use Lalaz\Auth\Tests\Common\AuthIntegrationTestCase;
use Lalaz\Auth\AuthManager;
use Lalaz\Auth\AuthContext;
use Lalaz\Auth\Guards\SessionGuard;
use Lalaz\Auth\Guards\JwtGuard;
use Lalaz\Auth\Jwt\JwtEncoder;
use Lalaz\Auth\Jwt\JwtBlacklist;
use Lalaz\Auth\NativePasswordHasher;
use Lalaz\Auth\Middlewares\AuthenticationMiddleware;
use Lalaz\Auth\Middlewares\AuthorizationMiddleware;
use Lalaz\Auth\Middlewares\PermissionMiddleware;
use Lalaz\Auth\Contracts\AuthenticatableInterface;
use Lalaz\Auth\Tests\Common\FakeSession;
use Lalaz\Auth\Tests\Common\FakeUserProvider;
use Lalaz\Web\Http\Contracts\RequestInterface;
use Lalaz\Web\Http\Contracts\ResponseInterface;
use Lalaz\Exceptions\HttpException;
use PHPUnit\Framework\Attributes\Test;

/**
 * Integration tests for Authentication/Authorization Middleware Chain.
 *
 * Tests the complete middleware flow including:
 * - AuthenticationMiddleware with different guards
 * - AuthorizationMiddleware with role-based access
 * - PermissionMiddleware with permission checks
 * - Middleware chaining scenarios
 *
 * @package lalaz/auth
 */
final class MiddlewareChainIntegrationTest extends AuthIntegrationTestCase
{
    private AuthManager $manager;
    private AuthContext $context;
    private FakeSession $session;
    private NativePasswordHasher $hasher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = new AuthManager();
        $this->context = new AuthContext();
        $this->session = $this->fakeSession();
        $this->hasher = new NativePasswordHasher();
    }

    // =========================================================================
    // AuthenticationMiddleware Tests
    // =========================================================================

    #[Test]
    public function authentication_middleware_allows_authenticated_user(): void
    {
        // Setup
        $user = $this->fakeUser(id: 'user@test.com', password: $this->hasher->hash('pass'));
        $provider = new FakeUserProvider([$user->getAuthIdentifier() => $user]);
        $guard = new SessionGuard($this->session, $provider);

        $this->manager->register('session', $guard);

        // Login user
        $guard->attempt(['email' => 'user@test.com', 'password' => 'pass']);

        // Create middleware with explicit dependencies
        $middleware = new AuthenticationMiddleware(
            guard: 'session',
            authManager: $this->manager,
            authContext: $this->context
        );

        // Create fake request/response
        $request = $this->createFakeRequest();
        $response = $this->createFakeResponse();

        $nextCalled = false;
        $next = function ($req, $res) use (&$nextCalled) {
            $nextCalled = true;
            return 'success';
        };

        // Execute
        $result = $middleware->handle($request, $response, $next);

        // Assert
        $this->assertTrue($nextCalled);
        $this->assertEquals('success', $result);
        $this->assertEquals('user@test.com', $request->user->getAuthIdentifier());
        $this->assertTrue($this->context->check('session'));
    }

    #[Test]
    public function authentication_middleware_throws_401_for_unauthenticated_user(): void
    {
        // Setup - no login performed
        $provider = new FakeUserProvider([]);
        $guard = new SessionGuard($this->session, $provider);

        $this->manager->register('session', $guard);

        $middleware = new AuthenticationMiddleware(
            guard: 'session',
            authManager: $this->manager,
            authContext: $this->context
        );

        $request = $this->createFakeRequest();
        $response = $this->createFakeResponse();
        $next = fn($req, $res) => 'should not reach';

        // Assert exception
        $this->expectException(HttpException::class);

        $middleware->handle($request, $response, $next);
    }

    #[Test]
    public function authentication_middleware_redirects_when_login_url_provided(): void
    {
        // Setup - no login performed
        $provider = new FakeUserProvider([]);
        $guard = new SessionGuard($this->session, $provider);

        $this->manager->register('session', $guard);

        $middleware = new AuthenticationMiddleware(
            guard: 'session',
            loginUrl: '/login',
            authManager: $this->manager,
            authContext: $this->context
        );

        $request = $this->createFakeRequest();
        $response = $this->createFakeResponse();
        $next = fn($req, $res) => 'should not reach';

        // Execute
        $result = $middleware->handle($request, $response, $next);

        // Assert redirect was called
        $this->assertNull($result);
        $this->assertEquals('/login', $response->redirectedTo);
    }

    #[Test]
    public function authentication_middleware_works_with_jwt_guard(): void
    {
        // Setup
        $user = $this->fakeUser(id: 'jwt-user');
        $provider = new FakeUserProvider([$user->getAuthIdentifier() => $user]);

        $encoder = new JwtEncoder(self::JWT_SECRET);
        $guard = new JwtGuard($encoder, new JwtBlacklist(), $provider);

        $this->manager->register('jwt', $guard);

        // Login via JWT
        $jwtUser = $guard->authenticateToken($encoder->createAccessToken('jwt-user'));
        $guard->login($jwtUser);

        $middleware = new AuthenticationMiddleware(
            guard: 'jwt',
            authManager: $this->manager,
            authContext: $this->context
        );

        $request = $this->createFakeRequest();
        $response = $this->createFakeResponse();

        $nextCalled = false;
        $next = function ($req, $res) use (&$nextCalled) {
            $nextCalled = true;
            return 'jwt-success';
        };

        // Execute
        $result = $middleware->handle($request, $response, $next);

        // Assert
        $this->assertTrue($nextCalled);
        $this->assertEquals('jwt-success', $result);
        $this->assertEquals('jwt-user', $request->user->getAuthIdentifier());
    }

    #[Test]
    public function authentication_middleware_factory_methods_create_correct_guards(): void
    {
        $session = AuthenticationMiddleware::session('/login');
        $this->assertEquals('session', $session->getGuardName());

        $web = AuthenticationMiddleware::web('/login');
        $this->assertEquals('web', $web->getGuardName());

        $jwt = AuthenticationMiddleware::jwt();
        $this->assertEquals('jwt', $jwt->getGuardName());

        $api = AuthenticationMiddleware::api();
        $this->assertEquals('api', $api->getGuardName());

        $apiKey = AuthenticationMiddleware::apiKey();
        $this->assertEquals('api-key', $apiKey->getGuardName());

        $custom = AuthenticationMiddleware::guard('custom', '/auth');
        $this->assertEquals('custom', $custom->getGuardName());
    }

    // =========================================================================
    // AuthorizationMiddleware Tests
    // =========================================================================

    #[Test]
    public function authorization_middleware_allows_user_with_required_role(): void
    {
        // Setup user with admin role
        $user = $this->createUserWithRoles(['admin', 'editor']);
        $this->context->setUser($user, 'web');
        $this->context->setCurrentGuard('web');

        $middleware = new AuthorizationMiddleware(
            requiredRoles: ['admin'],
            authContext: $this->context
        );

        $request = $this->createFakeRequest();
        $response = $this->createFakeResponse();

        $nextCalled = false;
        $next = function ($req, $res) use (&$nextCalled) {
            $nextCalled = true;
            return 'authorized';
        };

        // Execute
        $result = $middleware->handle($request, $response, $next);

        // Assert
        $this->assertTrue($nextCalled);
        $this->assertEquals('authorized', $result);
    }

    #[Test]
    public function authorization_middleware_allows_user_with_any_required_role(): void
    {
        // User has 'editor' but not 'admin'
        $user = $this->createUserWithRoles(['editor']);
        $this->context->setUser($user, 'web');
        $this->context->setCurrentGuard('web');

        // Requires admin OR editor
        $middleware = new AuthorizationMiddleware(
            requiredRoles: ['admin', 'editor'],
            authContext: $this->context
        );

        $request = $this->createFakeRequest();
        $response = $this->createFakeResponse();

        $nextCalled = false;
        $next = function ($req, $res) use (&$nextCalled) {
            $nextCalled = true;
            return 'authorized';
        };

        $result = $middleware->handle($request, $response, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals('authorized', $result);
    }

    #[Test]
    public function authorization_middleware_throws_403_for_user_without_role(): void
    {
        // User has only 'user' role
        $user = $this->createUserWithRoles(['user']);
        $this->context->setUser($user, 'web');
        $this->context->setCurrentGuard('web');

        // Requires admin
        $middleware = new AuthorizationMiddleware(
            requiredRoles: ['admin'],
            authContext: $this->context
        );

        $request = $this->createFakeRequest();
        $response = $this->createFakeResponse();
        $next = fn($req, $res) => 'should not reach';

        $this->expectException(HttpException::class);

        $middleware->handle($request, $response, $next);
    }

    #[Test]
    public function authorization_middleware_throws_403_for_unauthenticated_user(): void
    {
        // No user in context
        $middleware = new AuthorizationMiddleware(
            requiredRoles: ['admin'],
            authContext: $this->context
        );

        $request = $this->createFakeRequest();
        $response = $this->createFakeResponse();
        $next = fn($req, $res) => 'should not reach';

        $this->expectException(HttpException::class);

        $middleware->handle($request, $response, $next);
    }

    #[Test]
    public function authorization_middleware_skips_check_when_no_roles_required(): void
    {
        // No user, but no roles required either
        $middleware = new AuthorizationMiddleware(
            requiredRoles: [],
            authContext: $this->context
        );

        $request = $this->createFakeRequest();
        $response = $this->createFakeResponse();

        $nextCalled = false;
        $next = function ($req, $res) use (&$nextCalled) {
            $nextCalled = true;
            return 'passed';
        };

        $result = $middleware->handle($request, $response, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals('passed', $result);
    }

    #[Test]
    public function authorization_middleware_factory_methods_work(): void
    {
        $admin = AuthorizationMiddleware::admin();
        $moderator = AuthorizationMiddleware::moderator();
        $custom = AuthorizationMiddleware::requireRoles('role1', 'role2');
        $guard = AuthorizationMiddleware::guard('api', 'admin');

        // These just verify factory methods don't throw
        $this->assertInstanceOf(AuthorizationMiddleware::class, $admin);
        $this->assertInstanceOf(AuthorizationMiddleware::class, $moderator);
        $this->assertInstanceOf(AuthorizationMiddleware::class, $custom);
        $this->assertInstanceOf(AuthorizationMiddleware::class, $guard);
    }

    // =========================================================================
    // PermissionMiddleware Tests
    // =========================================================================

    #[Test]
    public function permission_middleware_allows_user_with_any_permission(): void
    {
        $user = $this->createUserWithPermissions(['posts.create', 'posts.edit']);
        $this->context->setUser($user, 'web');
        $this->context->setCurrentGuard('web');

        // Requires any: posts.delete OR posts.create
        $middleware = new PermissionMiddleware(
            permissions: ['posts.delete', 'posts.create'],
            requireAll: false,
            authContext: $this->context
        );

        $request = $this->createFakeRequest();
        $response = $this->createFakeResponse();

        $nextCalled = false;
        $next = function ($req, $res) use (&$nextCalled) {
            $nextCalled = true;
            return 'permitted';
        };

        $result = $middleware->handle($request, $response, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals('permitted', $result);
    }

    #[Test]
    public function permission_middleware_allows_user_with_all_permissions(): void
    {
        $user = $this->createUserWithPermissions(['posts.create', 'posts.edit', 'posts.delete']);
        $this->context->setUser($user, 'web');
        $this->context->setCurrentGuard('web');

        // Requires ALL: posts.create AND posts.edit
        $middleware = new PermissionMiddleware(
            permissions: ['posts.create', 'posts.edit'],
            requireAll: true,
            authContext: $this->context
        );

        $request = $this->createFakeRequest();
        $response = $this->createFakeResponse();

        $nextCalled = false;
        $next = function ($req, $res) use (&$nextCalled) {
            $nextCalled = true;
            return 'all-permitted';
        };

        $result = $middleware->handle($request, $response, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals('all-permitted', $result);
    }

    #[Test]
    public function permission_middleware_throws_403_when_missing_all_permissions(): void
    {
        $user = $this->createUserWithPermissions(['posts.create']);
        $this->context->setUser($user, 'web');
        $this->context->setCurrentGuard('web');

        // Requires ALL: posts.create AND posts.edit (user only has posts.create)
        $middleware = new PermissionMiddleware(
            permissions: ['posts.create', 'posts.edit'],
            requireAll: true,
            authContext: $this->context
        );

        $request = $this->createFakeRequest();
        $response = $this->createFakeResponse();
        $next = fn($req, $res) => 'should not reach';

        $this->expectException(HttpException::class);

        $middleware->handle($request, $response, $next);
    }

    #[Test]
    public function permission_middleware_throws_403_when_missing_any_permission(): void
    {
        $user = $this->createUserWithPermissions(['posts.view']);
        $this->context->setUser($user, 'web');
        $this->context->setCurrentGuard('web');

        // Requires ANY: posts.create OR posts.edit (user has neither)
        $middleware = new PermissionMiddleware(
            permissions: ['posts.create', 'posts.edit'],
            requireAll: false,
            authContext: $this->context
        );

        $request = $this->createFakeRequest();
        $response = $this->createFakeResponse();
        $next = fn($req, $res) => 'should not reach';

        $this->expectException(HttpException::class);

        $middleware->handle($request, $response, $next);
    }

    #[Test]
    public function permission_middleware_redirects_on_failure_when_url_provided(): void
    {
        $user = $this->createUserWithPermissions([]);
        $this->context->setUser($user, 'web');
        $this->context->setCurrentGuard('web');

        $middleware = new PermissionMiddleware(
            permissions: ['admin.access'],
            redirectUrl: '/unauthorized',
            authContext: $this->context
        );

        $request = $this->createFakeRequest();
        $response = $this->createFakeResponse();
        $next = fn($req, $res) => 'should not reach';

        $result = $middleware->handle($request, $response, $next);

        $this->assertNull($result);
        $this->assertEquals('/unauthorized', $response->redirectedTo);
    }

    #[Test]
    public function permission_middleware_factory_methods_work(): void
    {
        $any = PermissionMiddleware::any('perm1', 'perm2');
        $all = PermissionMiddleware::all('perm1', 'perm2');
        $redirect = PermissionMiddleware::redirectOnFailure(['perm1'], '/error');
        $guard = PermissionMiddleware::guard('api', 'admin.access');

        $this->assertInstanceOf(PermissionMiddleware::class, $any);
        $this->assertInstanceOf(PermissionMiddleware::class, $all);
        $this->assertInstanceOf(PermissionMiddleware::class, $redirect);
        $this->assertInstanceOf(PermissionMiddleware::class, $guard);
    }

    // =========================================================================
    // Middleware Chain Integration Tests
    // =========================================================================

    #[Test]
    public function middleware_chain_authentication_then_authorization(): void
    {
        // Setup authenticated user with admin role
        $user = $this->createUserWithRoles(['admin']);

        // Use a simple provider that accepts any AuthenticatableInterface
        $provider = new GenericFakeProvider([$user->getAuthIdentifier() => $user]);
        $guard = new SessionGuard($this->session, $provider);

        $this->manager->register('session', $guard);
        $guard->login($user);

        // Create middleware chain
        $authMiddleware = new AuthenticationMiddleware(
            guard: 'session',
            authManager: $this->manager,
            authContext: $this->context
        );

        $authzMiddleware = new AuthorizationMiddleware(
            requiredRoles: ['admin'],
            authContext: $this->context
        );

        $request = $this->createFakeRequest();
        $response = $this->createFakeResponse();

        $finalReached = false;
        $finalHandler = function ($req, $res) use (&$finalReached) {
            $finalReached = true;
            return 'chain-complete';
        };

        // Chain: Auth -> Authz -> Final
        $authzHandler = fn($req, $res) => $authzMiddleware->handle($req, $res, $finalHandler);
        $result = $authMiddleware->handle($request, $response, $authzHandler);

        $this->assertTrue($finalReached);
        $this->assertEquals('chain-complete', $result);
    }

    #[Test]
    public function middleware_chain_fails_at_authorization(): void
    {
        // Setup authenticated user WITHOUT admin role
        $user = $this->createUserWithRoles(['user']);
        $provider = new GenericFakeProvider([$user->getAuthIdentifier() => $user]);
        $guard = new SessionGuard($this->session, $provider);

        $this->manager->register('session', $guard);
        $guard->login($user);

        $authMiddleware = new AuthenticationMiddleware(
            guard: 'session',
            authManager: $this->manager,
            authContext: $this->context
        );

        $authzMiddleware = new AuthorizationMiddleware(
            requiredRoles: ['admin'],
            authContext: $this->context
        );

        $request = $this->createFakeRequest();
        $response = $this->createFakeResponse();

        $finalHandler = fn($req, $res) => 'should not reach';
        $authzHandler = fn($req, $res) => $authzMiddleware->handle($req, $res, $finalHandler);

        $this->expectException(HttpException::class);

        $authMiddleware->handle($request, $response, $authzHandler);
    }

    #[Test]
    public function middleware_chain_authentication_authorization_permission(): void
    {
        // Setup user with role and permissions
        $user = $this->createUserWithRolesAndPermissions(['editor'], ['posts.create', 'posts.edit']);
        $provider = new GenericFakeProvider([$user->getAuthIdentifier() => $user]);
        $guard = new SessionGuard($this->session, $provider);

        $this->manager->register('session', $guard);
        $guard->login($user);

        $authMiddleware = new AuthenticationMiddleware(
            guard: 'session',
            authManager: $this->manager,
            authContext: $this->context
        );

        $authzMiddleware = new AuthorizationMiddleware(
            requiredRoles: ['editor', 'admin'],
            authContext: $this->context
        );

        $permMiddleware = new PermissionMiddleware(
            permissions: ['posts.create'],
            authContext: $this->context
        );

        $request = $this->createFakeRequest();
        $response = $this->createFakeResponse();

        $finalReached = false;
        $finalHandler = function ($req, $res) use (&$finalReached) {
            $finalReached = true;
            return 'full-chain-complete';
        };

        // Chain: Auth -> Authz -> Perm -> Final
        $permHandler = fn($req, $res) => $permMiddleware->handle($req, $res, $finalHandler);
        $authzHandler = fn($req, $res) => $authzMiddleware->handle($req, $res, $permHandler);
        $result = $authMiddleware->handle($request, $response, $authzHandler);

        $this->assertTrue($finalReached);
        $this->assertEquals('full-chain-complete', $result);
    }

    #[Test]
    public function middleware_works_with_user_from_request_property(): void
    {
        // User set directly on request (not via AuthContext)
        $user = $this->createUserWithRoles(['admin']);

        $authzMiddleware = new AuthorizationMiddleware(
            requiredRoles: ['admin'],
            authContext: $this->context
        );

        $request = $this->createFakeRequest();
        $request->user = $user;
        $response = $this->createFakeResponse();

        $nextCalled = false;
        $next = function ($req, $res) use (&$nextCalled) {
            $nextCalled = true;
            return 'from-request-user';
        };

        $result = $authzMiddleware->handle($request, $response, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals('from-request-user', $result);
    }

    #[Test]
    public function middleware_checks_specific_guard_when_configured(): void
    {
        // User in 'api' guard but middleware checks 'web' guard
        $apiUser = $this->createUserWithRoles(['admin']);
        $this->context->setUser($apiUser, 'api');

        // No user in 'web' guard
        $authzMiddleware = (new AuthorizationMiddleware(
            requiredRoles: ['admin'],
            authContext: $this->context
        ))->forGuard('web');

        $request = $this->createFakeRequest();
        $response = $this->createFakeResponse();
        $next = fn($req, $res) => 'should not reach';

        $this->expectException(HttpException::class);

        $authzMiddleware->handle($request, $response, $next);
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    private function createFakeRequest(): FakeRequest
    {
        return new FakeRequest();
    }

    private function createFakeResponse(): FakeResponse
    {
        return new FakeResponse();
    }

    private function createUserWithRoles(array $roles): FakeUserWithRoles
    {
        return new FakeUserWithRoles(
            id: 'user-' . uniqid(),
            roles: $roles,
            permissions: []
        );
    }

    private function createUserWithPermissions(array $permissions): FakeUserWithRoles
    {
        return new FakeUserWithRoles(
            id: 'user-' . uniqid(),
            roles: [],
            permissions: $permissions
        );
    }

    private function createUserWithRolesAndPermissions(array $roles, array $permissions): FakeUserWithRoles
    {
        return new FakeUserWithRoles(
            id: 'user-' . uniqid(),
            roles: $roles,
            permissions: $permissions
        );
    }
}

// =========================================================================
// Test Helpers (Fake Implementations)
// =========================================================================

/**
 * Fake Request for middleware testing.
 */
class FakeRequest implements RequestInterface
{
    public mixed $user = null;

    public function method(): string { return 'GET'; }
    public function setMethod(string $method): void {}
    public function path(): string { return '/test'; }
    public function uri(): string { return '/test'; }
    public function params(): array { return []; }
    public function param(string $name, mixed $default = null): mixed { return $default; }
    public function routeParams(): array { return []; }
    public function routeParam(string $name, mixed $default = null): mixed { return $default; }
    public function queryParams(): array { return []; }
    public function queryParam(string $name, mixed $default = null): mixed { return $default; }
    public function body(): mixed { return []; }
    public function input(string $name, mixed $default = null): mixed { return $default; }
    public function all(): array { return []; }
    public function json(?string $key = null, mixed $default = null): mixed { return $default; }
    public function cookie(string $name): mixed { return null; }
    public function hasCookie(string $name): bool { return false; }
    public function isJson(): bool { return false; }
    public function headers(): array { return []; }
    public function header(string $name, mixed $default = null): mixed { return $default; }
    public function has(string $key): bool { return false; }
    public function boolean(string $key, bool $default = false): bool { return $default; }
    public function file(string $key) { return null; }
    public function ip(): string { return '127.0.0.1'; }
    public function userAgent(): string { return 'Test'; }
    public function wantsJson(): bool { return false; }
    public function isSecure(): bool { return false; }
}

/**
 * Fake Response for middleware testing.
 */
class FakeResponse implements ResponseInterface
{
    public ?string $redirectedTo = null;
    private int $statusCode = 200;
    private array $headers = [];
    private string $body = '';

    public function status(int $code): self { $this->statusCode = $code; return $this; }
    public function getStatusCode(): int { return $this->statusCode; }
    public function addHeader(string $name, string $value): self { $this->headers[$name] = $value; return $this; }
    public function header(string $name, string $value): self { return $this->addHeader($name, $value); }
    public function withHeaders(array $headers): self { $this->headers = array_merge($this->headers, $headers); return $this; }
    public function headers(): array { return $this->headers; }
    public function body(): string { return $this->body; }
    public function isStreamed(): bool { return false; }
    public function sendBody(\Lalaz\Web\Http\Contracts\ResponseBodyEmitterInterface $emitter): void {}
    public function setBody(string $content): self { $this->body = $content; return $this; }
    public function append(string $content): self { $this->body .= $content; return $this; }
    public function redirect(string $url, bool $allowExternal = false): void { $this->redirectedTo = $url; }
    public function noContent(array $headers = []): void { $this->statusCode = 204; }
    public function created(string $location, mixed $data = null): void { $this->statusCode = 201; }
    public function download(string $filePath, ?string $fileName = null, array $headers = []): void {}
    public function stream(callable $callback, int $statusCode = 200, array $headers = []): void {}
    public function json($data = [], $statusCode = 200): void { $this->statusCode = $statusCode; }
    public function send(string $content, int $statusCode = 200, array $headers = [], ?string $contentType = null): void {}
    public function end(): void {}
}

/**
 * Generic Fake User Provider that accepts any AuthenticatableInterface.
 */
class GenericFakeProvider implements \Lalaz\Auth\Contracts\UserProviderInterface
{
    /** @var array<string, AuthenticatableInterface> */
    private array $users;

    public function __construct(array $users = [])
    {
        $this->users = $users;
    }

    public function retrieveById(mixed $identifier): mixed
    {
        return $this->users[$identifier] ?? null;
    }

    public function retrieveByCredentials(array $credentials): mixed
    {
        $email = $credentials['email'] ?? null;
        return $email ? ($this->users[$email] ?? null) : null;
    }

    public function validateCredentials(mixed $user, array $credentials): bool
    {
        return true; // Always valid for testing
    }
}

/**
 * Fake User with Roles and Permissions for testing.
 */
class FakeUserWithRoles implements AuthenticatableInterface
{
    private ?string $rememberToken = null;

    public function __construct(
        private string $id,
        private array $roles = [],
        private array $permissions = []
    ) {}

    public function getAuthIdentifier(): mixed { return $this->id; }
    public function getAuthIdentifierName(): string { return 'id'; }
    public function getAuthPassword(): string { return ''; }
    public function getRememberToken(): ?string { return $this->rememberToken; }
    public function setRememberToken(?string $value): void { $this->rememberToken = $value; }
    public function getRememberTokenName(): string { return 'remember_token'; }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    public function hasAnyRole(array $roles): bool
    {
        return count(array_intersect($this->roles, $roles)) > 0;
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }

    public function hasAnyPermission(array $permissions): bool
    {
        return count(array_intersect($this->permissions, $permissions)) > 0;
    }

    public function hasAllPermissions(array $permissions): bool
    {
        return count(array_intersect($this->permissions, $permissions)) === count($permissions);
    }
}
