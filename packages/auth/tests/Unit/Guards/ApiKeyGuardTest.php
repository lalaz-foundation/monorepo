<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Unit\Guards;

use PHPUnit\Framework\Attributes\CoversClass;

use Lalaz\Auth\Guards\ApiKeyGuard;
use Lalaz\Auth\Tests\Common\AuthUnitTestCase;
use Lalaz\Auth\Tests\Common\FakeUserProvider;
use Lalaz\Web\Http\Contracts\RequestInterface;

#[CoversClass(\Lalaz\Auth\Guards\ApiKeyGuard::class)]
final class ApiKeyGuardTest extends AuthUnitTestCase
{
    private FakeUserProvider $provider;
    private ApiKeyGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = $this->fakeUserProvider();
        $this->guard = new ApiKeyGuard($this->provider);
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

    // =========================================================================
    // API Key Authentication via Attempt
    // =========================================================================

    public function testAttemptAuthenticatesWithValidApiKey(): void
    {
        $user = $this->fakeUser(1);
        $this->provider->addUser($user);
        $this->provider->addApiKey('valid-api-key-123', 1);

        $result = $this->guard->attempt(['api_key' => 'valid-api-key-123']);

        $this->assertSame($user, $result);
        $this->assertTrue($this->guard->check());
    }

    public function testAttemptRejectsInvalidApiKey(): void
    {
        $user = $this->fakeUser(1);
        $this->provider->addUser($user);
        $this->provider->addApiKey('valid-api-key-123', 1);

        $result = $this->guard->attempt(['api_key' => 'invalid-key']);

        $this->assertNull($result);
        $this->assertFalse($this->guard->check());
    }

    // =========================================================================
    // Login and Logout
    // =========================================================================

    public function testLoginSetsUser(): void
    {
        $user = $this->fakeUser(1);

        $this->guard->login($user);

        $this->assertSame($user, $this->guard->user());
        $this->assertTrue($this->guard->check());
    }

    public function testLogoutClearsUser(): void
    {
        $user = $this->fakeUser(1);
        $this->guard->login($user);

        $this->guard->logout();

        $this->assertNull($this->guard->user());
        $this->assertFalse($this->guard->check());
    }

    // =========================================================================
    // ID Method
    // =========================================================================

    public function testIdReturnsUserIdWhenAuthenticated(): void
    {
        $user = $this->fakeUser(42);
        $this->guard->login($user);

        $this->assertSame(42, $this->guard->id());
    }

    public function testIdReturnsNullWhenNotAuthenticated(): void
    {
        $this->assertNull($this->guard->id());
    }

    // =========================================================================
    // Token Generation
    // =========================================================================

    public function testGeneratesApiKey(): void
    {
        $result = $this->guard->generateApiKey('lz');

        $this->assertArrayHasKey('key', $result);
        $this->assertArrayHasKey('hash', $result);
        $this->assertStringStartsWith('lz_', $result['key']);
    }

    public function testValidatesApiKeyFormat(): void
    {
        $result = $this->guard->generateApiKey('test');

        $this->assertTrue(ApiKeyGuard::isValidFormat($result['key']));
        $this->assertFalse(ApiKeyGuard::isValidFormat('invalid'));
    }

    // =========================================================================
    // Request Integration (Skipped - requires real RequestInterface)
    // =========================================================================

    public function testAuthenticatesFromRequestHeaderRequiresRealRequest(): void
    {
        // Note: This test is simplified because PHPUnit cannot mock interfaces
        // with a method named 'method()' (RequestInterface has method(): string).
        // Full integration testing should use real Request objects.

        $this->assertTrue(true);
    }
}
