<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Integration;

use Lalaz\Auth\Tests\Common\AuthIntegrationTestCase;
use Lalaz\Auth\AuthManager;
use Lalaz\Auth\AuthContext;
use Lalaz\Auth\Guards\SessionGuard;
use Lalaz\Auth\NativePasswordHasher;
use Lalaz\Auth\Tests\Common\FakeSession;
use Lalaz\Auth\Tests\Common\FakeUserProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Integration tests for Session-based Authentication Flow.
 *
 * Tests the complete session authentication lifecycle including:
 * - Login with credentials
 * - Session persistence
 * - Remember me tokens (via attemptWithRemember)
 * - Session regeneration
 * - Logout and session cleanup
 *
 * @package lalaz/auth
 */
final class SessionAuthFlowIntegrationTest extends AuthIntegrationTestCase
{
    private NativePasswordHasher $hasher;

    private const string SESSION_KEY = '__auth_user';

    protected function setUp(): void
    {
        parent::setUp();
        $this->hasher = new NativePasswordHasher();
    }

    // =========================================================================
    // Basic Login Flow Tests
    // =========================================================================

    #[Test]
    public function it_performs_complete_login_flow_with_session_guard(): void
    {
        // Setup
        $password = 'secret123';
        $hashedPassword = $this->hasher->hash($password);
        $user = $this->fakeUser(id: 'user@test.com', password: $hashedPassword);
        $provider = new FakeUserProvider([$user->getAuthIdentifier() => $user]);
        $session = $this->fakeSession();

        $guard = new SessionGuard($session, $provider);

        // Attempt login
        $result = $guard->attempt([
            'email' => 'user@test.com',
            'password' => $password
        ]);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals('user@test.com', $result->getAuthIdentifier());
        $this->assertTrue($guard->check());
        $this->assertEquals('user@test.com', $guard->id());
        $this->assertSame($result, $guard->user());
    }

    #[Test]
    public function it_rejects_login_with_wrong_password(): void
    {
        $password = 'secret123';
        $hashedPassword = $this->hasher->hash($password);
        $user = $this->fakeUser(id: 'user@test.com', password: $hashedPassword);
        $provider = new FakeUserProvider([$user->getAuthIdentifier() => $user]);
        $session = $this->fakeSession();

        $guard = new SessionGuard($session, $provider);

        // Attempt with wrong password
        $result = $guard->attempt([
            'email' => 'user@test.com',
            'password' => 'wrong-password'
        ]);

        $this->assertNull($result);
        $this->assertFalse($guard->check());
        $this->assertNull($guard->user());
    }

    #[Test]
    public function it_rejects_login_with_nonexistent_user(): void
    {
        $user = $this->fakeUser(id: 'existing@test.com', password: $this->hasher->hash('pass'));
        $provider = new FakeUserProvider([$user->getAuthIdentifier() => $user]);
        $session = $this->fakeSession();

        $guard = new SessionGuard($session, $provider);

        $result = $guard->attempt([
            'email' => 'nonexistent@test.com',
            'password' => 'pass'
        ]);

        $this->assertNull($result);
        $this->assertFalse($guard->check());
    }

    // =========================================================================
    // Session Persistence Tests
    // =========================================================================

    #[Test]
    public function it_persists_user_id_to_session(): void
    {
        $password = 'secret';
        $user = $this->fakeUser(id: 'persist@test.com', password: $this->hasher->hash($password));
        $provider = new FakeUserProvider([$user->getAuthIdentifier() => $user]);
        $session = $this->fakeSession();

        $guard = new SessionGuard($session, $provider);
        $guard->attempt(['email' => 'persist@test.com', 'password' => $password]);

        // Check session has user ID stored (using correct key)
        $this->assertEquals('persist@test.com', $session->get(self::SESSION_KEY));
    }

    #[Test]
    public function it_restores_user_from_session_on_subsequent_requests(): void
    {
        $password = 'secret';
        $user = $this->fakeUser(id: 'restore@test.com', password: $this->hasher->hash($password));
        $provider = new FakeUserProvider([$user->getAuthIdentifier() => $user]);

        // First request - login
        $session = $this->fakeSession();
        $guard1 = new SessionGuard($session, $provider);
        $guard1->attempt(['email' => 'restore@test.com', 'password' => $password]);

        // Simulate new request with same session data
        $newSession = $this->fakeSession();
        $newSession->set(self::SESSION_KEY, 'restore@test.com');

        $guard2 = new SessionGuard($newSession, $provider);

        // Should automatically restore user from session
        $this->assertTrue($guard2->check());
        $this->assertEquals('restore@test.com', $guard2->id());
    }

    #[Test]
    public function it_returns_guest_when_session_has_no_user(): void
    {
        $provider = new FakeUserProvider([]);
        $session = $this->fakeSession();

        $guard = new SessionGuard($session, $provider);

        $this->assertTrue($guard->guest());
        $this->assertFalse($guard->check());
        $this->assertNull($guard->user());
    }

    // =========================================================================
    // Remember Me Token Tests (using attemptWithRemember)
    // =========================================================================

    #[Test]
    public function it_creates_remember_token_when_remember_is_true(): void
    {
        $password = 'secret';
        $user = $this->fakeUser(id: 'remember@test.com', password: $this->hasher->hash($password));

        // Need a provider that supports remember tokens
        $provider = new RememberTokenFakeProvider([$user->getAuthIdentifier() => $user]);
        $session = $this->fakeSession();

        $guard = new SessionGuard($session, $provider);
        $guard->attemptWithRemember(['email' => 'remember@test.com', 'password' => $password], remember: true);

        // Check remember token was set on user
        $this->assertNotNull($user->getRememberToken());
        $this->assertNotEmpty($user->getRememberToken());
    }

    #[Test]
    public function it_does_not_create_remember_token_when_remember_is_false(): void
    {
        $password = 'secret';
        $user = $this->fakeUser(id: 'noremember@test.com', password: $this->hasher->hash($password));

        $provider = new RememberTokenFakeProvider([$user->getAuthIdentifier() => $user]);
        $session = $this->fakeSession();

        $guard = new SessionGuard($session, $provider);
        $guard->attemptWithRemember(['email' => 'noremember@test.com', 'password' => $password], remember: false);

        // Remember token should remain null
        $this->assertNull($user->getRememberToken());
    }

    // =========================================================================
    // Logout Flow Tests
    // =========================================================================

    #[Test]
    public function it_performs_complete_logout_flow(): void
    {
        $password = 'secret';
        $user = $this->fakeUser(id: 'logout@test.com', password: $this->hasher->hash($password));
        $provider = new FakeUserProvider([$user->getAuthIdentifier() => $user]);
        $session = $this->fakeSession();

        $guard = new SessionGuard($session, $provider);
        $guard->attempt(['email' => 'logout@test.com', 'password' => $password]);

        $this->assertTrue($guard->check());

        // Logout
        $guard->logout();

        // Assert
        $this->assertFalse($guard->check());
        $this->assertNull($guard->user());
    }

    #[Test]
    public function logout_clears_session_data(): void
    {
        $password = 'secret';
        $user = $this->fakeUser(id: 'clearsession@test.com', password: $this->hasher->hash($password));
        $provider = new FakeUserProvider([$user->getAuthIdentifier() => $user]);
        $session = $this->fakeSession();

        $guard = new SessionGuard($session, $provider);
        $guard->attempt(['email' => 'clearsession@test.com', 'password' => $password]);

        $this->assertNotNull($session->get(self::SESSION_KEY));

        $guard->logout();

        // Session should be destroyed, so get returns null
        $this->assertNull($session->get(self::SESSION_KEY));
    }

    // =========================================================================
    // Direct Login Tests
    // =========================================================================

    #[Test]
    public function it_allows_direct_login_without_credentials(): void
    {
        $user = $this->fakeUser(id: 'directlogin@test.com');
        $provider = new FakeUserProvider([$user->getAuthIdentifier() => $user]);
        $session = $this->fakeSession();

        $guard = new SessionGuard($session, $provider);

        // Login directly without password check
        $guard->login($user);

        $this->assertTrue($guard->check());
        $this->assertEquals('directlogin@test.com', $guard->id());
        $this->assertSame($user, $guard->user());
    }

    #[Test]
    public function it_allows_login_by_id(): void
    {
        $user = $this->fakeUser(id: 'byid@test.com');
        $provider = new FakeUserProvider([$user->getAuthIdentifier() => $user]);
        $session = $this->fakeSession();

        $guard = new SessionGuard($session, $provider);

        // Use loginById which is the actual method name
        $result = $guard->loginById('byid@test.com');

        $this->assertNotNull($result);
        $this->assertTrue($guard->check());
        $this->assertEquals('byid@test.com', $guard->id());
    }

    #[Test]
    public function login_by_id_returns_null_for_nonexistent_user(): void
    {
        $provider = new FakeUserProvider([]);
        $session = $this->fakeSession();

        $guard = new SessionGuard($session, $provider);

        $result = $guard->loginById('nonexistent@test.com');

        $this->assertNull($result);
        $this->assertFalse($guard->check());
    }

    // =========================================================================
    // Session Regeneration Tests
    // =========================================================================

    #[Test]
    public function it_regenerates_session_on_login(): void
    {
        $password = 'secret';
        $user = $this->fakeUser(id: 'regen@test.com', password: $this->hasher->hash($password));
        $provider = new FakeUserProvider([$user->getAuthIdentifier() => $user]);
        $session = new TrackingFakeSession();

        $guard = new SessionGuard($session, $provider);
        $guard->attempt(['email' => 'regen@test.com', 'password' => $password]);

        // Check that regenerate was called
        $this->assertTrue($session->wasRegenerated());
    }

    #[Test]
    public function it_regenerates_session_on_direct_login(): void
    {
        $user = $this->fakeUser(id: 'regendirect@test.com');
        $provider = new FakeUserProvider([$user->getAuthIdentifier() => $user]);
        $session = new TrackingFakeSession();

        $guard = new SessionGuard($session, $provider);
        $guard->login($user);

        $this->assertTrue($session->wasRegenerated());
    }

    // =========================================================================
    // AuthManager Integration Tests
    // =========================================================================

    #[Test]
    public function it_works_through_auth_manager(): void
    {
        $password = 'secret';
        $user = $this->fakeUser(id: 'manager@test.com', password: $this->hasher->hash($password));
        $provider = new FakeUserProvider([$user->getAuthIdentifier() => $user]);
        $session = $this->fakeSession();

        $guard = new SessionGuard($session, $provider);

        $manager = new AuthManager();
        $manager->register('session', $guard);
        $manager->setDefaultGuard('session');

        // Use through manager
        $result = $manager->attempt(['email' => 'manager@test.com', 'password' => $password]);

        $this->assertNotNull($result);
        $this->assertTrue($manager->check());
        $this->assertEquals('manager@test.com', $manager->id());
    }

    #[Test]
    public function it_maintains_state_with_auth_context(): void
    {
        $password = 'secret';
        $user = $this->fakeUser(id: 'context@test.com', password: $this->hasher->hash($password));
        $provider = new FakeUserProvider([$user->getAuthIdentifier() => $user]);
        $session = $this->fakeSession();

        $guard = new SessionGuard($session, $provider);
        $context = new AuthContext();

        $guard->attempt(['email' => 'context@test.com', 'password' => $password]);

        // Populate context
        $context->setUser($guard->user(), 'session');
        $context->setCurrentGuard('session');

        // Verify context state
        $this->assertTrue($context->check('session'));
        $this->assertEquals('context@test.com', $context->id('session'));
        $this->assertSame($guard->user(), $context->user('session'));
    }

    // =========================================================================
    // Credential Validation Tests
    // =========================================================================

    #[Test]
    public function it_validates_credentials_without_logging_in(): void
    {
        $password = 'secret';
        $user = $this->fakeUser(id: 'validate@test.com', password: $this->hasher->hash($password));
        $provider = new FakeUserProvider([$user->getAuthIdentifier() => $user]);
        $session = $this->fakeSession();

        $guard = new SessionGuard($session, $provider);

        // Validate only (uses BaseGuard::validate)
        $valid = $guard->validate(['email' => 'validate@test.com', 'password' => $password]);

        $this->assertTrue($valid);
        $this->assertFalse($guard->check()); // Should NOT be logged in
    }

    #[Test]
    public function it_returns_false_for_invalid_credentials_validation(): void
    {
        $password = 'secret';
        $user = $this->fakeUser(id: 'invalidvalidate@test.com', password: $this->hasher->hash($password));
        $provider = new FakeUserProvider([$user->getAuthIdentifier() => $user]);
        $session = $this->fakeSession();

        $guard = new SessionGuard($session, $provider);

        $valid = $guard->validate(['email' => 'invalidvalidate@test.com', 'password' => 'wrong']);

        $this->assertFalse($valid);
    }

    // =========================================================================
    // Multiple Users Tests
    // =========================================================================

    #[Test]
    public function it_switches_between_users(): void
    {
        $password = 'secret';
        $user1 = $this->fakeUser(id: 'user1@test.com', password: $this->hasher->hash($password));
        $user2 = $this->fakeUser(id: 'user2@test.com', password: $this->hasher->hash($password));
        $provider = new FakeUserProvider([
            $user1->getAuthIdentifier() => $user1,
            $user2->getAuthIdentifier() => $user2,
        ]);
        $session = $this->fakeSession();

        $guard = new SessionGuard($session, $provider);

        // Login as user1
        $guard->attempt(['email' => 'user1@test.com', 'password' => $password]);
        $this->assertEquals('user1@test.com', $guard->id());

        // Logout and login as user2
        $guard->logout();

        // Need fresh session after destroy
        $session2 = $this->fakeSession();
        $guard2 = new SessionGuard($session2, $provider);
        $guard2->attempt(['email' => 'user2@test.com', 'password' => $password]);

        $this->assertEquals('user2@test.com', $guard2->id());
        $this->assertEquals('user2@test.com', $session2->get(self::SESSION_KEY));
    }

    // =========================================================================
    // Edge Cases
    // =========================================================================

    #[Test]
    public function it_handles_null_session(): void
    {
        $user = $this->fakeUser(id: 'nosession@test.com');
        $provider = new FakeUserProvider([$user->getAuthIdentifier() => $user]);

        // Guard without session
        $guard = new SessionGuard(null, $provider);

        // Login should still work (in-memory)
        $guard->login($user);

        $this->assertTrue($guard->check());
        $this->assertEquals('nosession@test.com', $guard->id());
    }

    #[Test]
    public function it_handles_null_provider(): void
    {
        $session = $this->fakeSession();

        // Guard without provider
        $guard = new SessionGuard($session, null);

        $result = $guard->attempt(['email' => 'test@test.com', 'password' => 'pass']);

        $this->assertNull($result);
        $this->assertFalse($guard->check());
    }
}

// =========================================================================
// Test Helpers
// =========================================================================

/**
 * FakeSession that tracks regeneration calls.
 */
class TrackingFakeSession extends FakeSession
{
    private bool $regenerated = false;

    public function regenerate(bool $deleteOldSession = true): void
    {
        $this->regenerated = true;
        parent::regenerate($deleteOldSession);
    }

    public function wasRegenerated(): bool
    {
        return $this->regenerated;
    }
}

/**
 * FakeUserProvider that supports remember tokens.
 */
class RememberTokenFakeProvider extends FakeUserProvider implements \Lalaz\Auth\Contracts\RememberTokenProviderInterface
{
    public function retrieveByToken(mixed $identifier, string $token): mixed
    {
        $user = $this->retrieveById($identifier);

        if ($user !== null && $user->getRememberToken() === $token) {
            return $user;
        }

        return null;
    }

    public function updateRememberToken(mixed $user, string $token): void
    {
        if ($user instanceof \Lalaz\Auth\Contracts\AuthenticatableInterface) {
            $user->setRememberToken($token);
        }
    }
}
