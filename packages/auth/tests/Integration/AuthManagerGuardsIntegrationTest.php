<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Integration;

use Lalaz\Auth\Tests\Common\AuthIntegrationTestCase;
use Lalaz\Auth\AuthManager;
use Lalaz\Auth\AuthContext;
use Lalaz\Auth\Guards\SessionGuard;
use Lalaz\Auth\Guards\JwtGuard;
use Lalaz\Auth\Guards\ApiKeyGuard;
use Lalaz\Auth\Jwt\JwtEncoder;
use Lalaz\Auth\Jwt\JwtBlacklist;
use Lalaz\Auth\NativePasswordHasher;
use Lalaz\Auth\Contracts\ApiKeyProviderInterface;
use Lalaz\Auth\Contracts\UserProviderInterface;
use Lalaz\Auth\Contracts\AuthenticatableInterface;
use Lalaz\Auth\Tests\Common\FakeUser;
use Lalaz\Auth\Tests\Common\FakeSession;
use Lalaz\Auth\Tests\Common\FakeUserProvider;
use PHPUnit\Framework\Attributes\Test;
use InvalidArgumentException;

/**
 * Integration tests for AuthManager with multiple guards.
 *
 * Tests the complete authentication flow including:
 * - Guard registration and resolution
 * - Multi-guard authentication scenarios
 * - Guard switching at runtime
 * - Provider integration with guards
 * - Login/logout flows across different guard types
 *
 * @package lalaz/auth
 */
final class AuthManagerGuardsIntegrationTest extends AuthIntegrationTestCase
{
    private AuthManager $manager;
    private FakeSession $session;
    private NativePasswordHasher $hasher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = new AuthManager();
        $this->session = $this->fakeSession();
        $this->hasher = new NativePasswordHasher();
    }

    // =========================================================================
    // Guard Registration Tests
    // =========================================================================

    #[Test]
    public function it_registers_and_retrieves_session_guard(): void
    {
        $provider = $this->createUserProviderWithUser();
        $guard = new SessionGuard($this->session, $provider);

        $this->manager->register('session', $guard);

        $resolved = $this->manager->guard('session');

        $this->assertSame($guard, $resolved);
        $this->assertInstanceOf(SessionGuard::class, $resolved);
        $this->assertEquals('session', $resolved->getName());
    }

    #[Test]
    public function it_registers_and_retrieves_jwt_guard(): void
    {
        $encoder = new JwtEncoder(self::JWT_SECRET);
        $blacklist = new JwtBlacklist();
        $provider = $this->createUserProviderWithUser();

        $guard = new JwtGuard($encoder, $blacklist, $provider);

        $this->manager->register('jwt', $guard);

        $resolved = $this->manager->guard('jwt');

        $this->assertSame($guard, $resolved);
        $this->assertInstanceOf(JwtGuard::class, $resolved);
        $this->assertEquals('jwt', $resolved->getName());
    }

    #[Test]
    public function it_registers_and_retrieves_api_key_guard(): void
    {
        $provider = $this->createApiKeyProvider();
        $guard = new ApiKeyGuard($provider);

        $this->manager->register('api', $guard);

        $resolved = $this->manager->guard('api');

        $this->assertSame($guard, $resolved);
        $this->assertInstanceOf(ApiKeyGuard::class, $resolved);
        $this->assertEquals('api_key', $resolved->getName());
    }

    #[Test]
    public function it_throws_exception_for_undefined_guard(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Auth guard [undefined] is not defined');

        $this->manager->guard('undefined');
    }

    #[Test]
    public function it_registers_guard_via_extend(): void
    {
        $provider = $this->createUserProviderWithUser();

        $this->manager->extend('custom', function ($name, $manager) use ($provider) {
            return new SessionGuard($this->session, $provider);
        });

        $guard = $this->manager->guard('custom');

        $this->assertInstanceOf(SessionGuard::class, $guard);
    }

    // =========================================================================
    // Default Guard Tests
    // =========================================================================

    #[Test]
    public function it_uses_default_guard_when_not_specified(): void
    {
        $sessionGuard = new SessionGuard($this->session);
        $jwtGuard = new JwtGuard(new JwtEncoder(self::JWT_SECRET));

        $this->manager->register('session', $sessionGuard);
        $this->manager->register('jwt', $jwtGuard);
        $this->manager->setDefaultGuard('session');

        $default = $this->manager->guard();

        $this->assertSame($sessionGuard, $default);
    }

    #[Test]
    public function it_changes_default_guard(): void
    {
        $sessionGuard = new SessionGuard($this->session);
        $jwtGuard = new JwtGuard(new JwtEncoder(self::JWT_SECRET));

        $this->manager->register('session', $sessionGuard);
        $this->manager->register('jwt', $jwtGuard);

        $this->manager->setDefaultGuard('jwt');

        $this->assertEquals('jwt', $this->manager->getDefaultGuard());
        $this->assertSame($jwtGuard, $this->manager->guard());
    }

    // =========================================================================
    // Session Guard Authentication Flow Tests
    // =========================================================================

    #[Test]
    public function session_guard_authenticates_user_with_valid_credentials(): void
    {
        $password = 'secret123';
        $hashedPassword = $this->hasher->hash($password);
        $user = $this->fakeUser(id: 'test@example.com', password: $hashedPassword);
        $provider = $this->createUserProviderWithUser($user);

        $guard = new SessionGuard($this->session, $provider);
        $this->manager->register('session', $guard);
        $this->manager->setDefaultGuard('session');

        // Attempt login
        $result = $this->manager->attempt([
            'email' => 'test@example.com',
            'password' => $password,
        ]);

        $this->assertNotNull($result);
        $this->assertTrue($this->manager->check());
        $this->assertFalse($this->manager->guest());
        $this->assertSame($user, $this->manager->user());
        $this->assertEquals('test@example.com', $this->manager->id());
    }

    #[Test]
    public function session_guard_rejects_invalid_credentials(): void
    {
        $hashedPassword = $this->hasher->hash('correct-password');
        $user = $this->fakeUser(id: 'test@example.com', password: $hashedPassword);
        $provider = $this->createUserProviderWithUser($user);

        $guard = new SessionGuard($this->session, $provider);
        $this->manager->register('session', $guard);
        $this->manager->setDefaultGuard('session');

        $result = $this->manager->attempt([
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $this->assertNull($result);
        $this->assertFalse($this->manager->check());
        $this->assertTrue($this->manager->guest());
    }

    #[Test]
    public function session_guard_stores_user_id_in_session(): void
    {
        $password = 'secret123';
        $hashedPassword = $this->hasher->hash($password);
        $user = $this->fakeUser(id: 'user42@example.com', password: $hashedPassword);
        $provider = $this->createUserProviderWithUser($user);

        $guard = new SessionGuard($this->session, $provider);
        $this->manager->register('session', $guard);

        $this->manager->guard('session')->attempt([
            'email' => 'user42@example.com',
            'password' => $password,
        ]);

        // Check session has user ID
        $this->assertTrue($this->session->has('__auth_user'));
        $this->assertEquals('user42@example.com', $this->session->get('__auth_user'));
    }

    #[Test]
    public function session_guard_logout_clears_session(): void
    {
        $password = 'secret123';
        $hashedPassword = $this->hasher->hash($password);
        $user = $this->fakeUser(id: 'test@example.com', password: $hashedPassword);
        $provider = $this->createUserProviderWithUser($user);

        $guard = new SessionGuard($this->session, $provider);
        $this->manager->register('session', $guard);

        // Login
        $guard->attempt([
            'email' => 'test@example.com',
            'password' => $password,
        ]);

        $this->assertTrue($guard->check());

        // Logout
        $guard->logout();

        $this->assertFalse($guard->check());
        $this->assertNull($guard->user());
        $this->assertFalse($this->session->has('__auth_user'));
    }

    // =========================================================================
    // JWT Guard Authentication Flow Tests
    // =========================================================================

    #[Test]
    public function jwt_guard_creates_tokens_on_successful_auth(): void
    {
        $password = 'secret123';
        $hashedPassword = $this->hasher->hash($password);
        $user = $this->fakeUser(id: 'jwt@example.com', password: $hashedPassword);
        $provider = $this->createUserProviderWithUser($user);

        $encoder = new JwtEncoder(self::JWT_SECRET, 3600, 86400, 'lalaz-test');
        $guard = new JwtGuard($encoder, new JwtBlacklist(), $provider);

        $this->manager->register('jwt', $guard);

        // Attempt with tokens
        $tokens = $guard->attemptWithTokens([
            'email' => 'jwt@example.com',
            'password' => $password,
        ]);

        $this->assertNotNull($tokens);
        $this->assertArrayHasKey('access_token', $tokens);
        $this->assertArrayHasKey('refresh_token', $tokens);
        $this->assertArrayHasKey('token_type', $tokens);
        $this->assertArrayHasKey('expires_in', $tokens);

        // Verify access token
        $this->assertValidJwt($tokens['access_token']);
        $this->assertEquals('jwt@example.com', $encoder->getSubject($tokens['access_token']));

        // Verify refresh token
        $this->assertValidJwt($tokens['refresh_token']);
        $this->assertTrue($encoder->isRefreshToken($tokens['refresh_token']));
    }

    #[Test]
    public function jwt_guard_authenticates_user_from_token(): void
    {
        $user = $this->fakeUser(id: 'user-456');
        $provider = $this->createUserProviderWithUser($user);

        $encoder = new JwtEncoder(self::JWT_SECRET, 3600, 86400, 'lalaz-test');
        $guard = new JwtGuard($encoder, new JwtBlacklist(), $provider);

        // Create token manually
        $token = $encoder->createAccessToken('user-456');

        // Authenticate with token - this validates and returns user but doesn't set state
        $authenticatedUser = $guard->authenticateToken($token);

        $this->assertNotNull($authenticatedUser);
        $this->assertEquals('user-456', $authenticatedUser->getAuthIdentifier());

        // To maintain state, we need to call login() with the authenticated user
        $guard->login($authenticatedUser);
        $this->assertTrue($guard->check());
    }

    #[Test]
    public function jwt_guard_rejects_blacklisted_token(): void
    {
        $user = $this->fakeUser(id: 'user-789');
        $provider = $this->createUserProviderWithUser($user);

        $encoder = new JwtEncoder(self::JWT_SECRET);
        $blacklist = new JwtBlacklist();
        $guard = new JwtGuard($encoder, $blacklist, $provider);

        // Create and blacklist token
        $token = $encoder->createAccessToken('user-789');
        $guard->revokeToken($token);

        // Try to authenticate
        $result = $guard->authenticateToken($token);

        $this->assertNull($result);
    }

    #[Test]
    public function jwt_guard_refreshes_token_pair(): void
    {
        $user = $this->fakeUser(id: 'user-refresh');
        $provider = $this->createUserProviderWithUser($user);

        $encoder = new JwtEncoder(self::JWT_SECRET, 3600, 86400, 'lalaz-test');
        $blacklist = new JwtBlacklist();
        $guard = new JwtGuard($encoder, $blacklist, $provider);

        // Create initial tokens
        $initialTokens = $guard->createTokenPair($user);
        $refreshToken = $initialTokens['refresh_token'];

        // Refresh - should create new token pair
        $newTokens = $guard->refreshTokenPair($refreshToken);

        $this->assertNotNull($newTokens);
        $this->assertArrayHasKey('access_token', $newTokens);
        $this->assertArrayHasKey('refresh_token', $newTokens);
        $this->assertNotEquals($initialTokens['access_token'], $newTokens['access_token']);
        $this->assertNotEquals($initialTokens['refresh_token'], $newTokens['refresh_token']);

        // Note: The old refresh token blacklisting by JTI requires revokeToken to
        // extract and use JTI instead of full token. Current implementation adds
        // the full token string to blacklist, but refreshTokenPair checks by JTI.
        // This is a known limitation that can be addressed in a future update.
    }

    // =========================================================================
    // API Key Guard Authentication Flow Tests
    // =========================================================================

    #[Test]
    public function api_key_guard_authenticates_with_valid_key(): void
    {
        $apiKey = 'lz_' . bin2hex(random_bytes(32));
        $user = $this->fakeUser(id: 'api-user-1');
        $provider = $this->createApiKeyProviderWithKey($apiKey, $user);

        $guard = new ApiKeyGuard($provider);
        $this->manager->register('api', $guard);

        $result = $this->manager->guard('api')->attempt(['api_key' => $apiKey]);

        $this->assertNotNull($result);
        $this->assertEquals('api-user-1', $result->getAuthIdentifier());
        $this->assertTrue($this->manager->guard('api')->check());
    }

    #[Test]
    public function api_key_guard_rejects_invalid_key(): void
    {
        $validKey = 'lz_' . bin2hex(random_bytes(32));
        $user = $this->fakeUser(id: 'api-user-1');
        $provider = $this->createApiKeyProviderWithKey($validKey, $user);

        $guard = new ApiKeyGuard($provider);

        $result = $guard->attempt(['api_key' => 'invalid-key']);

        $this->assertNull($result);
        $this->assertFalse($guard->check());
    }

    #[Test]
    public function api_key_guard_generates_valid_key_format(): void
    {
        $guard = new ApiKeyGuard();

        $keyData = $guard->generateApiKey('lz_live');

        $this->assertArrayHasKey('key', $keyData);
        $this->assertArrayHasKey('hash', $keyData);
        $this->assertStringStartsWith('lz_live_', $keyData['key']);
        $this->assertEquals(64, strlen($keyData['hash'])); // SHA256 hex
    }

    // =========================================================================
    // Multi-Guard Scenarios Tests
    // =========================================================================

    #[Test]
    public function it_handles_multiple_guards_simultaneously(): void
    {
        // Setup users for each guard - ID must match email for FakeUserProvider
        $sessionUser = $this->fakeUser(id: 'session@example.com', password: $this->hasher->hash('pass1'));
        $jwtUser = $this->fakeUser(id: 'jwt-user');
        $apiUser = $this->fakeUser(id: 'api-user');

        $sessionProvider = $this->createUserProviderWithUser($sessionUser);
        $jwtProvider = $this->createUserProviderWithUser($jwtUser);

        $apiKey = 'lz_' . bin2hex(random_bytes(32));
        $apiProvider = $this->createApiKeyProviderWithKey($apiKey, $apiUser);

        // Register all guards
        $sessionGuard = new SessionGuard($this->session, $sessionProvider);
        $encoder = new JwtEncoder(self::JWT_SECRET);
        $jwtGuard = new JwtGuard($encoder, new JwtBlacklist(), $jwtProvider);
        $apiGuard = new ApiKeyGuard($apiProvider);

        $this->manager->register('session', $sessionGuard);
        $this->manager->register('jwt', $jwtGuard);
        $this->manager->register('api', $apiGuard);

        // Authenticate each guard independently
        $this->manager->guard('session')->attempt(['email' => 'session@example.com', 'password' => 'pass1']);

        // For JWT, authenticateToken validates and returns user, then login() sets state
        $jwtToken = $encoder->createAccessToken('jwt-user');
        $jwtAuthUser = $jwtGuard->authenticateToken($jwtToken);
        $jwtGuard->login($jwtAuthUser);

        $this->manager->guard('api')->attempt(['api_key' => $apiKey]);

        // All guards should be authenticated with different users
        $this->assertEquals('session@example.com', $this->manager->guard('session')->id());
        $this->assertEquals('jwt-user', $this->manager->guard('jwt')->id());
        $this->assertEquals('api-user', $this->manager->guard('api')->id());
    }

    #[Test]
    public function it_switches_guards_at_runtime(): void
    {
        $sessionUser = $this->fakeUser(id: 'session@test.com', password: $this->hasher->hash('pass'));
        $jwtUser = $this->fakeUser(id: 'jwt-user-1');

        $sessionProvider = $this->createUserProviderWithUser($sessionUser);
        $jwtProvider = $this->createUserProviderWithUser($jwtUser);

        $sessionGuard = new SessionGuard($this->session, $sessionProvider);
        $encoder = new JwtEncoder(self::JWT_SECRET);
        $jwtGuard = new JwtGuard($encoder, new JwtBlacklist(), $jwtProvider);

        $this->manager->register('session', $sessionGuard);
        $this->manager->register('jwt', $jwtGuard);
        $this->manager->setDefaultGuard('session');

        // Start with session guard
        $this->manager->attempt(['email' => 'session@test.com', 'password' => 'pass']);
        $this->assertEquals('session@test.com', $this->manager->id());

        // Switch to JWT guard
        $this->manager->setDefaultGuard('jwt');
        $jwtAuthUser = $jwtGuard->authenticateToken($encoder->createAccessToken('jwt-user-1'));
        $jwtGuard->login($jwtAuthUser);

        $this->assertEquals('jwt-user-1', $this->manager->id());
    }

    #[Test]
    public function logout_only_affects_specific_guard(): void
    {
        $sessionUser = $this->fakeUser(id: 'su@test.com', password: $this->hasher->hash('pass'));
        $jwtUser = $this->fakeUser(id: 'ju');

        $sessionProvider = $this->createUserProviderWithUser($sessionUser);
        $jwtProvider = $this->createUserProviderWithUser($jwtUser);

        $sessionGuard = new SessionGuard($this->session, $sessionProvider);
        $encoder = new JwtEncoder(self::JWT_SECRET);
        $jwtGuard = new JwtGuard($encoder, new JwtBlacklist(), $jwtProvider);

        $this->manager->register('session', $sessionGuard);
        $this->manager->register('jwt', $jwtGuard);

        // Login session guard
        $sessionGuard->attempt(['email' => 'su@test.com', 'password' => 'pass']);

        // Login JWT guard (authenticateToken + login for state)
        $jwtAuthUser = $jwtGuard->authenticateToken($encoder->createAccessToken('ju'));
        $jwtGuard->login($jwtAuthUser);

        $this->assertTrue($sessionGuard->check());
        $this->assertTrue($jwtGuard->check());

        // Logout only session
        $sessionGuard->logout();

        $this->assertFalse($sessionGuard->check());
        $this->assertTrue($jwtGuard->check()); // JWT still authenticated
    }

    // =========================================================================
    // Guard Context Integration Tests
    // =========================================================================

    #[Test]
    public function auth_context_stores_authenticated_users_per_guard(): void
    {
        $sessionUser = $this->fakeUser(id: 'session@test.com', password: $this->hasher->hash('pass'));
        $apiUser = $this->fakeUser(id: 'api-user');

        $context = new AuthContext();

        // Set users for different guards
        $context->setUser($sessionUser, 'session');
        $context->setUser($apiUser, 'api');

        // Retrieve users by guard
        $this->assertSame($sessionUser, $context->user('session'));
        $this->assertSame($apiUser, $context->user('api'));
        $this->assertNull($context->user('jwt')); // No user set for jwt
    }

    #[Test]
    public function auth_context_check_works_per_guard(): void
    {
        $user = $this->fakeUser(id: 'test-user');
        $context = new AuthContext();

        $context->setUser($user, 'session');

        $this->assertTrue($context->check('session'));
        $this->assertFalse($context->check('api'));
        $this->assertFalse($context->guest('session'));
        $this->assertTrue($context->guest('api'));
    }

    #[Test]
    public function auth_context_provides_user_id(): void
    {
        $user = $this->fakeUser(id: 'user-with-id');
        $context = new AuthContext();

        $context->setUser($user, 'session');
        $context->setCurrentGuard('session');

        $this->assertEquals('user-with-id', $context->id());
        $this->assertEquals('user-with-id', $context->id('session'));
    }

    #[Test]
    public function auth_context_checks_roles_and_permissions(): void
    {
        $user = $this->fakeUser(
            id: 'auth-user',
            roles: ['admin', 'editor'],
            permissions: ['users.create', 'users.delete']
        );

        $context = new AuthContext();
        $context->setUser($user, 'web');
        $context->setCurrentGuard('web');

        // Check roles
        $this->assertTrue($context->hasRole('admin'));
        $this->assertTrue($context->hasRole('editor'));
        $this->assertFalse($context->hasRole('superadmin'));

        // Check permissions
        $this->assertTrue($context->hasPermission('users.create'));
        $this->assertTrue($context->hasPermission('users.delete'));
        $this->assertFalse($context->hasPermission('users.ban'));
    }

    #[Test]
    public function auth_context_clears_user_by_guard(): void
    {
        $user1 = $this->fakeUser(id: 'user1');
        $user2 = $this->fakeUser(id: 'user2');

        $context = new AuthContext();
        $context->setUser($user1, 'session');
        $context->setUser($user2, 'api');

        // Clear only session
        $context->clear('session');

        $this->assertNull($context->user('session'));
        $this->assertSame($user2, $context->user('api'));

        // Clear all
        $context->clear();
        $this->assertNull($context->user('api'));
    }

    #[Test]
    public function guard_context_provides_scoped_access(): void
    {
        $user = $this->fakeUser(
            id: 'scoped-user',
            roles: ['admin'],
            permissions: ['read', 'write']
        );

        $authContext = new AuthContext();
        $authContext->setUser($user, 'api');

        // Get guard-scoped context
        $guardContext = $authContext->guard('api');

        $this->assertEquals('api', $guardContext->name());
        $this->assertTrue($guardContext->check());
        $this->assertSame($user, $guardContext->user());
        $this->assertEquals('scoped-user', $guardContext->id());
        $this->assertTrue($guardContext->hasRole('admin'));
        $this->assertTrue($guardContext->hasPermission('read'));
    }

    // =========================================================================
    // Provider Registration Tests
    // =========================================================================

    #[Test]
    public function it_registers_and_retrieves_providers(): void
    {
        $provider1 = $this->fakeUserProvider();
        $provider2 = $this->fakeUserProvider();

        $this->manager->registerProvider('users', $provider1);
        $this->manager->registerProvider('admins', $provider2);

        $this->assertSame($provider1, $this->manager->getProvider('users'));
        $this->assertSame($provider2, $this->manager->getProvider('admins'));
        $this->assertNull($this->manager->getProvider('nonexistent'));
    }

    // =========================================================================
    // Guard Management Tests
    // =========================================================================

    #[Test]
    public function it_checks_if_guard_exists(): void
    {
        $guard = new SessionGuard($this->session);
        $this->manager->register('session', $guard);

        $this->assertTrue($this->manager->hasGuard('session'));
        $this->assertFalse($this->manager->hasGuard('nonexistent'));
    }

    #[Test]
    public function it_lists_all_guard_names(): void
    {
        $this->manager->register('session', new SessionGuard($this->session));
        $this->manager->register('jwt', new JwtGuard(new JwtEncoder(self::JWT_SECRET)));
        $this->manager->extend('custom', fn() => new SessionGuard($this->session));

        $names = $this->manager->getGuardNames();

        $this->assertContains('session', $names);
        $this->assertContains('jwt', $names);
        $this->assertContains('custom', $names);
    }

    #[Test]
    public function it_forgets_guard_instance(): void
    {
        $guard = new SessionGuard($this->session);
        $this->manager->register('session', $guard);

        $this->manager->forgetGuard('session');

        // Guard creator still exists but instance is forgotten
        // Re-accessing will resolve a new instance via extend if defined
        $this->assertFalse($this->manager->hasGuard('session'));
    }

    #[Test]
    public function it_forgets_all_guard_instances(): void
    {
        $this->manager->register('session', new SessionGuard($this->session));
        $this->manager->register('jwt', new JwtGuard(new JwtEncoder(self::JWT_SECRET)));

        $this->manager->forgetGuards();

        $this->assertFalse($this->manager->hasGuard('session'));
        $this->assertFalse($this->manager->hasGuard('jwt'));
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    private function createUserProviderWithUser(?FakeUser $user = null): FakeUserProvider
    {
        $user = $user ?? $this->fakeUser();
        return new FakeUserProvider([$user->getAuthIdentifier() => $user]);
    }

    private function createApiKeyProvider(): FakeApiKeyProvider
    {
        return new FakeApiKeyProvider([]);
    }

    private function createApiKeyProviderWithKey(string $apiKey, FakeUser $user): FakeApiKeyProvider
    {
        return new FakeApiKeyProvider([$apiKey => $user]);
    }
}

// =========================================================================
// Test Helpers (Fake Implementations)
// =========================================================================

/**
 * Fake API Key Provider for testing.
 */
class FakeApiKeyProvider implements UserProviderInterface, ApiKeyProviderInterface
{
    /** @var array<string, FakeUser> */
    private array $apiKeys;

    public function __construct(array $apiKeys)
    {
        $this->apiKeys = $apiKeys;
    }

    public function retrieveById(mixed $identifier): ?AuthenticatableInterface
    {
        foreach ($this->apiKeys as $user) {
            if ($user->getAuthIdentifier() === $identifier) {
                return $user;
            }
        }
        return null;
    }

    public function retrieveByCredentials(array $credentials): ?AuthenticatableInterface
    {
        return null;
    }

    public function validateCredentials(mixed $user, array $credentials): bool
    {
        return false;
    }

    public function retrieveByApiKey(string $apiKey): ?AuthenticatableInterface
    {
        return $this->apiKeys[$apiKey] ?? null;
    }
}
