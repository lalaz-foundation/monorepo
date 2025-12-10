<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Unit\Guards;

use Lalaz\Auth\Tests\Common\AuthUnitTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use Lalaz\Auth\Guards\SessionGuard;
use Lalaz\Auth\Contracts\SessionInterface;
use Lalaz\Auth\Providers\GenericUserProvider;

/**
 * Additional coverage tests for SessionGuard.
 */
#[CoversClass(SessionGuard::class)]
final class SessionGuardCoverageTest extends AuthUnitTestCase
{
    private const SESSION_KEY = '__auth_user';

    #[Test]
    public function session_guard_returns_name(): void
    {
        $session = $this->createSession();
        $guard = new SessionGuard($session);

        $this->assertEquals('session', $guard->getName());
    }

    #[Test]
    public function session_guard_login_stores_user_in_session(): void
    {
        $session = $this->createSession();
        $user = $this->fakeUser(id: 'session-user');
        $provider = $this->createProviderForUser($user);

        $guard = new SessionGuard($session, $provider);
        $guard->login($user);

        $this->assertSame($user, $guard->user());
        $this->assertEquals('session-user', $session->get(self::SESSION_KEY));
    }

    #[Test]
    public function session_guard_login_regenerates_session(): void
    {
        $session = $this->createSession();
        $user = $this->fakeUser(id: 'regen-user');

        $guard = new SessionGuard($session);
        $guard->login($user);

        $this->assertTrue($session->regenerated);
    }

    #[Test]
    public function session_guard_logout_clears_user(): void
    {
        $session = $this->createSession();
        $user = $this->fakeUser(id: 'logout-user');
        $provider = $this->createProviderForUser($user);

        $guard = new SessionGuard($session, $provider);
        $guard->login($user);
        $guard->logout();

        $this->assertNull($guard->user());
    }

    #[Test]
    public function session_guard_attempt_with_valid_credentials(): void
    {
        $session = $this->createSession();
        $user = $this->fakeUser(id: 'attempt-user');
        $provider = (new GenericUserProvider())
            ->setByCredentialsCallback(fn($c) => $user)
            ->setValidateCallback(fn($u, $c) => true);

        $guard = new SessionGuard($session, $provider);
        $result = $guard->attempt(['email' => 'test@test.com', 'password' => 'secret']);

        $this->assertSame($user, $result);
        $this->assertSame($user, $guard->user());
    }

    #[Test]
    public function session_guard_attempt_fails_without_provider(): void
    {
        $session = $this->createSession();
        $guard = new SessionGuard($session);

        $result = $guard->attempt(['email' => 'test@test.com']);

        $this->assertNull($result);
    }

    #[Test]
    public function session_guard_attempt_fails_with_invalid_credentials(): void
    {
        $session = $this->createSession();
        $provider = (new GenericUserProvider())
            ->setByCredentialsCallback(fn($c) => null);

        $guard = new SessionGuard($session, $provider);
        $result = $guard->attempt(['email' => 'wrong@test.com']);

        $this->assertNull($result);
    }

    #[Test]
    public function session_guard_attempt_fails_with_wrong_password(): void
    {
        $session = $this->createSession();
        $user = $this->fakeUser(id: 'wrong-pass');
        $provider = (new GenericUserProvider())
            ->setByCredentialsCallback(fn($c) => $user)
            ->setValidateCallback(fn($u, $c) => false);

        $guard = new SessionGuard($session, $provider);
        $result = $guard->attempt(['email' => 'test@test.com', 'password' => 'wrong']);

        $this->assertNull($result);
    }

    #[Test]
    public function session_guard_attempt_with_remember(): void
    {
        $session = $this->createSession();
        $user = $this->fakeUser(id: 'remember-user');
        $provider = (new GenericUserProvider())
            ->setByCredentialsCallback(fn($c) => $user)
            ->setValidateCallback(fn($u, $c) => true);

        $guard = new SessionGuard($session, $provider);
        $result = $guard->attemptWithRemember(
            ['email' => 'test@test.com', 'password' => 'secret'],
            remember: true
        );

        $this->assertSame($user, $result);
    }

    #[Test]
    public function session_guard_login_by_id(): void
    {
        $session = $this->createSession();
        $user = $this->fakeUser(id: 'id-login-user');
        $provider = (new GenericUserProvider())
            ->setByIdCallback(fn($id) => $id === 'id-login-user' ? $user : null);

        $guard = new SessionGuard($session, $provider);
        $result = $guard->loginById('id-login-user');

        $this->assertSame($user, $result);
        $this->assertSame($user, $guard->user());
    }

    #[Test]
    public function session_guard_login_by_id_fails_without_provider(): void
    {
        $session = $this->createSession();
        $guard = new SessionGuard($session);

        $result = $guard->loginById('some-id');

        $this->assertNull($result);
    }

    #[Test]
    public function session_guard_login_by_id_fails_for_unknown_id(): void
    {
        $session = $this->createSession();
        $provider = (new GenericUserProvider())
            ->setByIdCallback(fn($id) => null);

        $guard = new SessionGuard($session, $provider);
        $result = $guard->loginById('unknown-id');

        $this->assertNull($result);
    }

    #[Test]
    public function session_guard_user_returns_cached_user(): void
    {
        $session = $this->createSession();
        $user = $this->fakeUser(id: 'cached-user');

        $guard = new SessionGuard($session);
        $guard->login($user);

        // Call user() multiple times - should return cached user
        $this->assertSame($user, $guard->user());
        $this->assertSame($user, $guard->user());
    }

    #[Test]
    public function session_guard_user_retrieves_from_session(): void
    {
        $user = $this->fakeUser(id: 'from-session');
        $session = $this->createSession();
        $session->set(self::SESSION_KEY, 'from-session');

        $provider = (new GenericUserProvider())
            ->setByIdCallback(fn($id) => $id === 'from-session' ? $user : null);

        $guard = new SessionGuard($session, $provider);
        $result = $guard->user();

        $this->assertSame($user, $result);
    }

    #[Test]
    public function session_guard_user_returns_null_when_no_session_data(): void
    {
        $session = $this->createSession();
        $guard = new SessionGuard($session);

        $this->assertNull($guard->user());
    }

    #[Test]
    public function session_guard_set_session(): void
    {
        $session1 = $this->createSession();
        $session2 = $this->createSession();

        $guard = new SessionGuard($session1);
        $guard->setSession($session2);

        $user = $this->fakeUser(id: 'new-session');
        $guard->login($user);

        // Should store in session2, not session1
        $this->assertEquals('new-session', $session2->get(self::SESSION_KEY));
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function createSession(): FakeSessionGuardSession
    {
        return new FakeSessionGuardSession();
    }

    private function createProviderForUser($user): GenericUserProvider
    {
        return (new GenericUserProvider())
            ->setByIdCallback(fn($id) => $id === $user->getAuthIdentifier() ? $user : null);
    }
}

/**
 * Fake session for SessionGuard tests.
 */
class FakeSessionGuardSession implements SessionInterface
{
    private array $data = [];
    private string $id;
    public bool $regenerated = false;
    public bool $invalidated = false;

    public function __construct()
    {
        $this->id = bin2hex(random_bytes(16));
    }

    public function start(): void {}

    public function regenerate(bool $deleteOldSession = true): void
    {
        $this->regenerated = true;
        $this->id = bin2hex(random_bytes(16));
    }

    public function destroy(): void
    {
        $this->data = [];
    }

    public function invalidate(): void
    {
        $this->invalidated = true;
        $this->data = [];
        $this->id = bin2hex(random_bytes(16));
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    public function all(): array
    {
        return $this->data;
    }

    public function clear(): void
    {
        $this->data = [];
    }

    public function flash(string $key, mixed $value): void
    {
        $this->data['_flash'][$key] = $value;
    }

    public function getFlash(string $key, mixed $default = null): mixed
    {
        return $this->data['_flash'][$key] ?? $default;
    }
}
