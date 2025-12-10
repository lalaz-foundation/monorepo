<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversClass;
use Lalaz\Auth\AuthManager;
use Lalaz\Auth\AuthContext;
use Lalaz\Auth\Jwt\JwtEncoder;
use Lalaz\Auth\Jwt\JwtBlacklist;
use Lalaz\Auth\Guards\SessionGuard;
use Lalaz\Auth\Guards\JwtGuard;
use Lalaz\Auth\Guards\ApiKeyGuard;
use Lalaz\Auth\Providers\GenericUserProvider;
use Lalaz\Auth\Providers\ModelUserProvider;
use Lalaz\Auth\Contracts\SessionInterface;
use Lalaz\Auth\Contracts\AuthenticatableInterface;
use Lalaz\Auth\NativePasswordHasher;

/**
 * Integration tests for Auth Service Registration
 *
 * Tests the service wiring that AuthServiceProvider would do,
 * by manually assembling components as the provider would.
 * This tests the integration points without requiring the framework container.
 */
#[CoversClass(AuthManager::class)]
#[CoversClass(AuthContext::class)]
#[Group('integration')]
class AuthServiceRegistrationIntegrationTest extends TestCase
{
    private NativePasswordHasher $hasher;

    protected function setUp(): void
    {
        $this->hasher = new NativePasswordHasher();
    }

    // =========================================================================
    // AuthContext Configuration
    // =========================================================================

    #[Test]
    public function auth_context_stores_default_guard(): void
    {
        $context = new AuthContext();
        $context->setDefaultGuard('api');

        $this->assertEquals('api', $context->getDefaultGuard());
    }

    #[Test]
    public function auth_context_stores_authenticated_user(): void
    {
        $context = new AuthContext();
        $user = new ServiceTestUser(1, 'test@example.com');

        $context->setUser($user);

        $this->assertSame($user, $context->user());
    }

    #[Test]
    public function auth_context_tracks_authentication_state(): void
    {
        $context = new AuthContext();

        $this->assertFalse($context->check());

        $context->setUser(new ServiceTestUser(1, 'test@example.com'));

        $this->assertTrue($context->check());
    }

    // =========================================================================
    // JWT Components Wiring
    // =========================================================================

    #[Test]
    public function jwt_encoder_works_with_configured_secret(): void
    {
        $secret = 'test-secret-key-minimum-32-characters!';
        $encoder = new JwtEncoder($secret);

        $payload = ['sub' => '123', 'email' => 'test@example.com'];
        $token = $encoder->encode($payload);
        $decoded = $encoder->decode($token);

        $this->assertEquals('123', $decoded['sub']);
        $this->assertEquals('test@example.com', $decoded['email']);
    }

    #[Test]
    public function jwt_blacklist_singleton_behavior(): void
    {
        $blacklist = new JwtBlacklist();

        $blacklist->add('token-123', time() + 3600);

        $this->assertTrue($blacklist->isBlacklisted('token-123'));
        $this->assertFalse($blacklist->isBlacklisted('token-456'));
    }

    // =========================================================================
    // AuthManager with Guard Drivers
    // =========================================================================

    #[Test]
    public function auth_manager_registers_session_guard_driver(): void
    {
        $manager = new AuthManager();
        $session = new ServiceTestSession();
        $provider = $this->createTestProvider();

        $manager->extend('session', fn() => new SessionGuard($session, $provider));

        $this->assertTrue($manager->hasGuard('session'));
    }

    #[Test]
    public function auth_manager_registers_jwt_guard_driver(): void
    {
        $manager = new AuthManager();
        $encoder = new JwtEncoder('test-secret-key-minimum-32-characters!');
        $blacklist = new JwtBlacklist();
        $provider = $this->createTestProvider();

        $manager->extend('jwt', fn() => new JwtGuard($encoder, $blacklist, $provider, null, 3600));

        $this->assertTrue($manager->hasGuard('jwt'));
    }

    #[Test]
    public function auth_manager_registers_api_key_guard_driver(): void
    {
        $manager = new AuthManager();
        $provider = $this->createTestProvider();

        $manager->extend('api_key', fn() => new ApiKeyGuard($provider));

        $this->assertTrue($manager->hasGuard('api_key'));
    }

    #[Test]
    public function auth_manager_creates_guards_lazily(): void
    {
        $creationCount = 0;
        $manager = new AuthManager();

        $manager->extend('web', function () use (&$creationCount) {
            $creationCount++;
            return new SessionGuard(new ServiceTestSession(), $this->createTestProvider());
        });

        // Guard not created yet
        $this->assertEquals(0, $creationCount);

        // First access creates the guard
        $manager->guard('web');
        $this->assertEquals(1, $creationCount);

        // Second access reuses the guard
        $manager->guard('web');
        $this->assertEquals(1, $creationCount);
    }

    // =========================================================================
    // AuthManager with Multiple Providers
    // =========================================================================

    #[Test]
    public function auth_manager_registers_multiple_providers(): void
    {
        $manager = new AuthManager();

        $usersProvider = $this->createTestProvider();
        $adminsProvider = $this->createTestProvider();

        $manager->registerProvider('users', $usersProvider);
        $manager->registerProvider('admins', $adminsProvider);

        $this->assertSame($usersProvider, $manager->getProvider('users'));
        $this->assertSame($adminsProvider, $manager->getProvider('admins'));
    }

    #[Test]
    public function auth_manager_returns_null_for_unknown_provider(): void
    {
        $manager = new AuthManager();

        $this->assertNull($manager->getProvider('unknown'));
    }

    // =========================================================================
    // Full Service Assembly (Like AuthServiceProvider would do)
    // =========================================================================

    #[Test]
    public function full_auth_service_assembly(): void
    {
        // 1. Create AuthContext
        $context = new AuthContext();
        $context->setDefaultGuard('web');

        // 2. Create JWT components
        $jwtEncoder = new JwtEncoder('super-secret-key-for-jwt-tokens!');
        $jwtBlacklist = new JwtBlacklist();

        // 3. Create user providers
        $usersProvider = GenericUserProvider::create(
            byId: fn($id) => $id === 1 ? new ServiceTestUser(1, 'user@example.com') : null,
            byCredentials: fn($creds) => ($creds['email'] ?? '') === 'user@example.com'
                ? new ServiceTestUser(1, 'user@example.com')
                : null,
        );

        $adminsProvider = GenericUserProvider::create(
            byId: fn($id) => $id === 100 ? new ServiceTestUser(100, 'admin@example.com') : null,
            byCredentials: fn($creds) => ($creds['email'] ?? '') === 'admin@example.com'
                ? new ServiceTestUser(100, 'admin@example.com')
                : null,
        );

        // 4. Create session
        $session = new ServiceTestSession();

        // 5. Create AuthManager with guards
        $manager = new AuthManager();

        $manager->registerProvider('users', $usersProvider);
        $manager->registerProvider('admins', $adminsProvider);

        $manager->extend('web', fn() => new SessionGuard($session, $usersProvider));
        $manager->extend('api', fn() => new JwtGuard($jwtEncoder, $jwtBlacklist, $usersProvider, null, 3600));
        $manager->extend('admin', fn() => new SessionGuard($session, $adminsProvider));

        $manager->setDefaultGuard('web');

        // Verify the assembly
        $this->assertEquals('web', $context->getDefaultGuard());
        $this->assertEquals('web', $manager->getDefaultGuard());

        $this->assertTrue($manager->hasGuard('web'));
        $this->assertTrue($manager->hasGuard('api'));
        $this->assertTrue($manager->hasGuard('admin'));

        $this->assertNotNull($manager->getProvider('users'));
        $this->assertNotNull($manager->getProvider('admins'));
    }

    #[Test]
    public function assembled_services_work_together(): void
    {
        // Assembly
        $jwtEncoder = new JwtEncoder('super-secret-key-for-jwt-tokens!');
        $jwtBlacklist = new JwtBlacklist();
        $hashedPassword = $this->hasher->hash('password123');

        $usersProvider = GenericUserProvider::create(
            byId: fn($id) => (int)$id === 1 ? new ServiceTestUserWithPassword(1, 'user@example.com', $hashedPassword) : null,
            byCredentials: fn($creds) => ($creds['email'] ?? '') === 'user@example.com'
                ? new ServiceTestUserWithPassword(1, 'user@example.com', $hashedPassword)
                : null,
        );

        $session = new ServiceTestSession();
        $manager = new AuthManager();

        $manager->registerProvider('users', $usersProvider);
        $manager->extend('web', fn() => new SessionGuard($session, $usersProvider));
        $manager->extend('api', fn() => new JwtGuard($jwtEncoder, $jwtBlacklist, $usersProvider, null, 3600));
        $manager->setDefaultGuard('web');

        // Test session guard flow
        $webGuard = $manager->guard('web');
        $user = $webGuard->attempt(['email' => 'user@example.com', 'password' => 'password123']);

        $this->assertNotNull($user);
        $this->assertEquals(1, $user->getAuthIdentifier());
        $this->assertTrue($webGuard->check());

        // Test JWT guard flow - attemptWithTokens returns tokens
        /** @var JwtGuard $apiGuard */
        $apiGuard = $manager->guard('api');
        $tokens = $apiGuard->attemptWithTokens(['email' => 'user@example.com', 'password' => 'password123']);

        $this->assertIsArray($tokens);
        $this->assertArrayHasKey('access_token', $tokens);
        $this->assertNotEmpty($tokens['access_token']);

        // Authenticate with token
        $apiUser = $apiGuard->authenticateToken($tokens['access_token']);
        $this->assertNotNull($apiUser);
        $this->assertEquals(1, $apiUser->getAuthIdentifier());
    }

    // =========================================================================
    // Provider Configuration Scenarios
    // =========================================================================

    #[Test]
    public function generic_provider_with_all_callbacks(): void
    {
        $users = [
            1 => new ServiceTestUserWithPassword(1, 'user@example.com', $this->hasher->hash('pass')),
        ];
        $tokens = [1 => 'remember-token-abc'];
        $apiKeys = ['api-key-123' => 1];

        $provider = new GenericUserProvider($this->hasher);

        $provider
            ->setByIdCallback(fn($id) => $users[$id] ?? null)
            ->setByCredentialsCallback(function ($creds) use ($users) {
                foreach ($users as $user) {
                    if ($user->email === ($creds['email'] ?? '')) {
                        return $user;
                    }
                }
                return null;
            })
            ->setByTokenCallback(function ($id, $token) use ($users, $tokens) {
                if (($tokens[$id] ?? '') === $token) {
                    return $users[$id] ?? null;
                }
                return null;
            })
            ->setByApiKeyCallback(function ($key) use ($users, $apiKeys) {
                $userId = $apiKeys[$key] ?? null;
                return $userId ? ($users[$userId] ?? null) : null;
            });

        // Test all retrieval methods
        $this->assertNotNull($provider->retrieveById(1));
        $this->assertNull($provider->retrieveById(999));

        $this->assertNotNull($provider->retrieveByCredentials(['email' => 'user@example.com']));
        $this->assertNull($provider->retrieveByCredentials(['email' => 'unknown@example.com']));

        $this->assertNotNull($provider->retrieveByToken(1, 'remember-token-abc'));
        $this->assertNull($provider->retrieveByToken(1, 'wrong-token'));

        $this->assertNotNull($provider->retrieveByApiKey('api-key-123'));
        $this->assertNull($provider->retrieveByApiKey('invalid-key'));
    }

    #[Test]
    public function model_provider_validates_model_class(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("does not exist");

        new ModelUserProvider('NonExistentClass');
    }

    // =========================================================================
    // Guard Configuration Scenarios
    // =========================================================================

    #[Test]
    public function configured_guards_use_correct_providers(): void
    {
        $userProvider = GenericUserProvider::create(
            byId: fn($id) => new ServiceTestUser($id, "user{$id}@example.com"),
            byCredentials: fn($c) => null,
        );

        $adminProvider = GenericUserProvider::create(
            byId: fn($id) => new ServiceTestUser($id + 1000, "admin{$id}@example.com"),
            byCredentials: fn($c) => null,
        );

        $manager = new AuthManager();
        $session = new ServiceTestSession();

        $manager->registerProvider('users', $userProvider);
        $manager->registerProvider('admins', $adminProvider);

        $manager->extend('web', fn() => new SessionGuard($session, $userProvider));
        $manager->extend('admin', fn() => new SessionGuard(new ServiceTestSession(), $adminProvider));

        // Login user via web guard
        $webGuard = $manager->guard('web');
        $webGuard->login(new ServiceTestUser(1, 'user1@example.com'));

        // Login admin via admin guard
        $adminGuard = $manager->guard('admin');
        $adminGuard->login(new ServiceTestUser(1001, 'admin1@example.com'));

        // Each guard has its own authenticated user
        $this->assertEquals(1, $webGuard->user()->getAuthIdentifier());
        $this->assertEquals(1001, $adminGuard->user()->getAuthIdentifier());
    }

    #[Test]
    public function auth_manager_uses_default_guard(): void
    {
        $manager = new AuthManager();
        $session = new ServiceTestSession();
        $provider = $this->createTestProvider();

        $manager->extend('web', fn() => new SessionGuard($session, $provider));
        $manager->extend('api', fn() => new JwtGuard(
            new JwtEncoder('secret-key-minimum-32-characters!'),
            new JwtBlacklist(),
            $provider,
            null,
            3600
        ));

        $manager->setDefaultGuard('api');

        // guard() without argument uses default
        $defaultGuard = $manager->guard();

        $this->assertInstanceOf(JwtGuard::class, $defaultGuard);
    }

    // =========================================================================
    // Session Interface Integration
    // =========================================================================

    #[Test]
    public function session_guard_uses_session_interface(): void
    {
        $session = new ServiceTestSession();
        $provider = $this->createTestProvider();

        $guard = new SessionGuard($session, $provider);
        $user = new ServiceTestUser(1, 'test@example.com');

        $guard->login($user);

        // Session should store the user ID
        $this->assertEquals(1, $session->get('__auth_user'));
        $this->assertTrue($guard->check());

        // Logout clears session
        $guard->logout();

        $this->assertNull($session->get('__auth_user'));
        $this->assertFalse($guard->check());
    }

    // =========================================================================
    // Edge Cases
    // =========================================================================

    #[Test]
    public function manager_handles_missing_default_guard(): void
    {
        $manager = new AuthManager();
        $manager->setDefaultGuard('web'); // Set but not registered

        $this->expectException(\InvalidArgumentException::class);
        $manager->guard(); // Should throw
    }

    #[Test]
    public function manager_handles_driver_not_found(): void
    {
        $manager = new AuthManager();

        $this->expectException(\InvalidArgumentException::class);
        $manager->guard('nonexistent');
    }

    #[Test]
    public function context_clears_on_clear(): void
    {
        $context = new AuthContext();
        $user = new ServiceTestUser(1, 'test@example.com');

        $context->setUser($user);
        $this->assertTrue($context->check());

        $context->clear();
        $this->assertFalse($context->check());
        $this->assertNull($context->user());
    }

    // =========================================================================
    // Multi-Guard Authentication Scenario
    // =========================================================================

    #[Test]
    public function multi_guard_authentication_scenario(): void
    {
        // Setup - simulating what AuthServiceProvider would do
        $hashedPassword = $this->hasher->hash('password123');

        $userStore = [
            1 => new ServiceTestUserWithPassword(1, 'user@example.com', $hashedPassword),
            2 => new ServiceTestUserWithPassword(2, 'admin@example.com', $hashedPassword),
        ];

        $userProvider = GenericUserProvider::create(
            byId: fn($id) => $userStore[$id] ?? null,
            byCredentials: function ($creds) use ($userStore) {
                foreach ($userStore as $user) {
                    if ($user->email === ($creds['email'] ?? '')) {
                        return $user;
                    }
                }
                return null;
            }
        );

        $jwtEncoder = new JwtEncoder('super-secret-key-for-jwt-tokens!');
        $jwtBlacklist = new JwtBlacklist();

        $manager = new AuthManager();
        $manager->registerProvider('users', $userProvider);

        // Configure guards
        $manager->extend('web', fn() => new SessionGuard(new ServiceTestSession(), $userProvider));
        $manager->extend('api', fn() => new JwtGuard($jwtEncoder, $jwtBlacklist, $userProvider, null, 3600));
        $manager->extend('api_key', fn() => new ApiKeyGuard($userProvider));

        $manager->setDefaultGuard('web');

        // Test multi-guard scenario
        // 1. User logs in via web
        $webGuard = $manager->guard('web');
        $webUser = $webGuard->attempt(['email' => 'user@example.com', 'password' => 'password123']);

        $this->assertNotNull($webUser);
        $this->assertTrue($webGuard->check());

        // 2. Same user gets API token pair
        /** @var JwtGuard $apiGuard */
        $apiGuard = $manager->guard('api');
        $tokens = $apiGuard->attemptWithTokens(['email' => 'user@example.com', 'password' => 'password123']);

        $this->assertIsArray($tokens);
        $this->assertArrayHasKey('access_token', $tokens);

        // 3. Token can be used to authenticate
        $apiUser = $apiGuard->authenticateToken($tokens['access_token']);
        $this->assertEquals(1, $apiUser->getAuthIdentifier());

        // 4. Guards are independent
        $webGuard->logout();
        $this->assertFalse($webGuard->check());

        // API token still valid (different guard)
        $apiUser2 = $apiGuard->authenticateToken($tokens['access_token']);
        $this->assertEquals(1, $apiUser2->getAuthIdentifier());
    }

    // =========================================================================
    // AuthManager Utility Methods
    // =========================================================================

    #[Test]
    public function auth_manager_lists_guard_names(): void
    {
        $manager = new AuthManager();
        $provider = $this->createTestProvider();

        $manager->extend('web', fn() => new SessionGuard(new ServiceTestSession(), $provider));
        $manager->extend('api', fn() => new JwtGuard(
            new JwtEncoder('secret-key-32-chars-minimum!!!!!'),
            new JwtBlacklist(),
            $provider,
            null,
            3600
        ));

        $names = $manager->getGuardNames();

        $this->assertContains('web', $names);
        $this->assertContains('api', $names);
    }

    #[Test]
    public function auth_manager_forgets_guards(): void
    {
        $manager = new AuthManager();
        $provider = $this->createTestProvider();

        $manager->extend('web', fn() => new SessionGuard(new ServiceTestSession(), $provider));

        // Create the guard instance
        $manager->guard('web');

        // Forget it
        $manager->forgetGuard('web');

        // The driver is still registered, but instance is cleared
        // New instance will be created on next access
        $this->assertTrue($manager->hasGuard('web'));
    }

    #[Test]
    public function auth_manager_registers_guard_directly(): void
    {
        $manager = new AuthManager();
        $session = new ServiceTestSession();
        $provider = $this->createTestProvider();

        $guard = new SessionGuard($session, $provider);
        $manager->register('direct', $guard);

        $this->assertTrue($manager->hasGuard('direct'));
        $this->assertSame($guard, $manager->guard('direct'));
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    private function createTestProvider(): GenericUserProvider
    {
        $hashedPassword = $this->hasher->hash('password123');

        return GenericUserProvider::create(
            byId: fn($id) => $id === 1
                ? new ServiceTestUserWithPassword(1, 'test@example.com', $hashedPassword)
                : null,
            byCredentials: fn($creds) => ($creds['email'] ?? '') === 'test@example.com'
                ? new ServiceTestUserWithPassword(1, 'test@example.com', $hashedPassword)
                : null,
        );
    }
}

// =============================================================================
// Test Helper Classes
// =============================================================================

/**
 * Simple test user
 */
class ServiceTestUser implements AuthenticatableInterface
{
    public function __construct(
        public int $id,
        public string $email,
    ) {}

    public function getAuthIdentifierName(): string { return 'id'; }
    public function getAuthIdentifier(): mixed { return $this->id; }
    public function getAuthPassword(): string { return ''; }
    public function getRememberToken(): ?string { return null; }
    public function setRememberToken(?string $value): void {}
    public function getRememberTokenName(): string { return 'remember_token'; }
}

/**
 * Test user with password
 */
class ServiceTestUserWithPassword implements AuthenticatableInterface
{
    public function __construct(
        public int $id,
        public string $email,
        public string $password,
    ) {}

    public function getAuthIdentifierName(): string { return 'id'; }
    public function getAuthIdentifier(): mixed { return $this->id; }
    public function getAuthPassword(): string { return $this->password; }
    public function getRememberToken(): ?string { return null; }
    public function setRememberToken(?string $value): void {}
    public function getRememberTokenName(): string { return 'remember_token'; }
}

/**
 * Test session
 */
class ServiceTestSession implements SessionInterface
{
    private array $data = [];

    public function start(): void {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    public function regenerate(bool $deleteOldSession = true): void {}

    public function destroy(): void
    {
        $this->data = [];
    }
}
