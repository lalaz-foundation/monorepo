<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Unit\Guards;

use PHPUnit\Framework\Attributes\CoversClass;

use Lalaz\Auth\Guards\JwtGuard;
use Lalaz\Auth\Jwt\JwtEncoder;
use Lalaz\Auth\Jwt\JwtBlacklist;
use Lalaz\Auth\Tests\Common\AuthUnitTestCase;
use Lalaz\Auth\Tests\Common\FakeUserProvider;

#[CoversClass(\Lalaz\Auth\Guards\JwtGuard::class)]
final class JwtGuardTest extends AuthUnitTestCase
{
    private JwtEncoder $encoder;
    private JwtBlacklist $blacklist;
    private FakeUserProvider $provider;
    private JwtGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->encoder = new JwtEncoder('secret-key-32-chars-minimum!!!!!');
        $this->blacklist = new JwtBlacklist();
        $this->provider = $this->fakeUserProvider();
        $this->guard = new JwtGuard($this->encoder, $this->blacklist, $this->provider);
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
    // Token Authentication
    // =========================================================================

    public function testAuthenticatesWithValidToken(): void
    {
        $user = $this->fakeUser(123);
        $this->provider->addUser($user);

        // Generate a token for the user
        $token = $this->guard->createToken($user);

        // Authenticate with the token
        $foundUser = $this->guard->authenticateToken($token);

        $this->assertSame($user, $foundUser);
    }

    public function testRejectsBlacklistedToken(): void
    {
        $user = $this->fakeUser(123);
        $this->provider->addUser($user);

        $token = $this->guard->createToken($user);
        $this->blacklist->add($token, time() + 3600);

        $foundUser = $this->guard->authenticateToken($token);

        $this->assertNull($foundUser);
    }

    public function testRejectsInvalidToken(): void
    {
        $foundUser = $this->guard->authenticateToken('invalid.token.here');

        $this->assertNull($foundUser);
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

    // =========================================================================
    // Attempt
    // =========================================================================

    public function testAttemptReturnsUserOnSuccess(): void
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

    // =========================================================================
    // Token Creation
    // =========================================================================

    public function testCreateTokenReturnsValidJwt(): void
    {
        $user = $this->fakeUser(1);
        $token = $this->guard->createToken($user);

        $this->assertIsString($token);
        $this->assertStringContainsString('.', $token);
    }

    public function testCreateTokenPairReturnsAccessAndRefreshTokens(): void
    {
        $user = $this->fakeUser(1);
        $tokens = $this->guard->createTokenPair($user);

        $this->assertArrayHasKey('access_token', $tokens);
        $this->assertArrayHasKey('refresh_token', $tokens);
        $this->assertArrayHasKey('token_type', $tokens);
        $this->assertArrayHasKey('expires_in', $tokens);
        $this->assertSame('Bearer', $tokens['token_type']);
    }
}
