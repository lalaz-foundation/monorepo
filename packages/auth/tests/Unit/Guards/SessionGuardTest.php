<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Unit\Guards;

use PHPUnit\Framework\Attributes\CoversClass;

use Lalaz\Auth\Guards\SessionGuard;
use Lalaz\Auth\Tests\Common\AuthUnitTestCase;
use Lalaz\Auth\Tests\Common\FakeSession;
use Lalaz\Auth\Tests\Common\FakeUserProvider;

#[CoversClass(\Lalaz\Auth\Guards\SessionGuard::class)]
final class SessionGuardTest extends AuthUnitTestCase
{
    private FakeSession $session;
    private FakeUserProvider $provider;
    private SessionGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->session = $this->fakeSession();
        $this->provider = $this->fakeUserProvider();
        $this->guard = new SessionGuard($this->session, $this->provider);
    }

    // =========================================================================
    // Initial State
    // =========================================================================

    public function testStartsWithNoUser(): void
    {
        $this->assertNull($this->guard->user());
    }

    public function testStartsAsGuest(): void
    {
        $this->assertTrue($this->guard->guest());
    }

    public function testStartsNotChecked(): void
    {
        $this->assertFalse($this->guard->check());
    }

    public function testStartsWithNullId(): void
    {
        $this->assertNull($this->guard->id());
    }

    // =========================================================================
    // Login
    // =========================================================================

    public function testLoginSetsUser(): void
    {
        $user = $this->fakeUser(1);

        $this->guard->login($user);

        $this->assertSame($user, $this->guard->user());
    }

    public function testLoginStoresIdInSession(): void
    {
        $user = $this->fakeUser(123);

        $this->guard->login($user);

        $this->assertSame(123, $this->session->get('__auth_user'));
    }

    public function testLoginChangesCheckToTrue(): void
    {
        $user = $this->fakeUser(1);

        $this->guard->login($user);

        $this->assertTrue($this->guard->check());
    }

    public function testLoginChangesGuestToFalse(): void
    {
        $user = $this->fakeUser(1);

        $this->guard->login($user);

        $this->assertFalse($this->guard->guest());
    }

    // =========================================================================
    // Logout
    // =========================================================================

    public function testLogoutClearsUser(): void
    {
        $user = $this->fakeUser(1);
        $this->guard->login($user);

        $this->guard->logout();

        $this->assertNull($this->guard->user());
    }

    public function testLogoutClearsSession(): void
    {
        $user = $this->fakeUser(1);
        $this->guard->login($user);

        $this->guard->logout();

        $this->assertNull($this->session->get('__auth_user'));
    }

    public function testLogoutChangesCheckToFalse(): void
    {
        $user = $this->fakeUser(1);
        $this->guard->login($user);

        $this->guard->logout();

        $this->assertFalse($this->guard->check());
    }

    public function testLogoutChangesGuestToTrue(): void
    {
        $user = $this->fakeUser(1);
        $this->guard->login($user);

        $this->guard->logout();

        $this->assertTrue($this->guard->guest());
    }

    // =========================================================================
    // Session Restoration
    // =========================================================================

    public function testRestoresUserFromSession(): void
    {
        $user = $this->fakeUser(123);
        $this->provider->addUser($user);

        // Simulate existing session
        $this->session->set('__auth_user', 123);

        // Create new guard instance with existing session
        $guard = new SessionGuard($this->session, $this->provider);

        $this->assertSame($user, $guard->user());
        $this->assertTrue($guard->check());
    }

    public function testReturnsNullIfSessionUserNotFound(): void
    {
        // Session has user_id but provider doesn't have the user
        $this->session->set('__auth_user', 999);

        $guard = new SessionGuard($this->session, $this->provider);

        $this->assertNull($guard->user());
    }

    // =========================================================================
    // ID Method
    // =========================================================================

    public function testIdReturnsUserIdWhenLoggedIn(): void
    {
        $user = $this->fakeUser(42);
        $this->guard->login($user);

        $this->assertSame(42, $this->guard->id());
    }

    public function testIdReturnsNullWhenNotLoggedIn(): void
    {
        $this->assertNull($this->guard->id());
    }

    // =========================================================================
    // Validate
    // =========================================================================

    public function testValidateReturnsTrueForCorrectCredentials(): void
    {
        $hashedPassword = password_hash('secret', PASSWORD_DEFAULT);
        $user = $this->fakeUser(1, $hashedPassword);
        $this->provider->addUser($user);

        // Add a way to identify the user via credentials
        $this->provider->addUser($user);

        // The provider's retrieveByCredentials needs to find the user
        // For FakeUserProvider, it matches by email or username to id
        $result = $this->guard->validate(['email' => 1, 'password' => 'secret']);

        $this->assertTrue($result);
    }

    public function testValidateReturnsFalseForIncorrectPassword(): void
    {
        $hashedPassword = password_hash('secret', PASSWORD_DEFAULT);
        $user = $this->fakeUser(1, $hashedPassword);
        $this->provider->addUser($user);

        $result = $this->guard->validate(['email' => 1, 'password' => 'wrong']);

        $this->assertFalse($result);
    }

    public function testValidateReturnsFalseForNonexistentUser(): void
    {
        $result = $this->guard->validate(['email' => 'nonexistent', 'password' => 'secret']);

        $this->assertFalse($result);
    }

    // =========================================================================
    // Attempt
    // =========================================================================

    public function testAttemptReturnUserOnSuccess(): void
    {
        $hashedPassword = password_hash('secret', PASSWORD_DEFAULT);
        $user = $this->fakeUser(1, $hashedPassword);
        $this->provider->addUser($user);

        $result = $this->guard->attempt(['email' => 1, 'password' => 'secret']);

        $this->assertNotNull($result);
        $this->assertSame($user, $result);
        $this->assertTrue($this->guard->check());
    }

    public function testAttemptReturnsNullOnFailure(): void
    {
        $result = $this->guard->attempt(['email' => 'nonexistent', 'password' => 'secret']);

        $this->assertNull($result);
        $this->assertFalse($this->guard->check());
    }
}
