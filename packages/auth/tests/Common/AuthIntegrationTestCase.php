<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Common;

use Lalaz\Testing\Integration\IntegrationTestCase;
use Lalaz\Auth\Tests\Common\FakeUser;
use Lalaz\Auth\Tests\Common\FakeSession;
use Lalaz\Auth\Tests\Common\FakeUserProvider;

/**
 * Base test case for Auth package integration tests.
 *
 * Extends IntegrationTestCase from lalaz/testing to provide
 * container bootstrapping plus auth-specific helpers.
 *
 * @package lalaz/auth
 */
abstract class AuthIntegrationTestCase extends IntegrationTestCase
{
    /**
     * Default JWT secret for testing.
     */
    protected const JWT_SECRET = 'test-secret-key-for-integration-tests-minimum-32-chars';

    /**
     * Default JWT TTL in seconds.
     */
    protected const JWT_TTL = 3600;

    // =========================================================================
    // Factory Methods
    // =========================================================================

    /**
     * Create a fake authenticatable user for testing.
     */
    protected function fakeUser(
        mixed $id = 1,
        string $password = 'hashed_password',
        array $roles = [],
        array $permissions = [],
    ): FakeUser {
        return new FakeUser($id, $password, $roles, $permissions);
    }

    /**
     * Create a fake session for testing.
     */
    protected function fakeSession(): FakeSession
    {
        return new FakeSession();
    }

    /**
     * Create a fake user provider with optional users.
     */
    protected function fakeUserProvider(array $users = []): FakeUserProvider
    {
        return new FakeUserProvider($users);
    }

    /**
     * Create a fake user provider with a default user.
     */
    protected function fakeUserProviderWithUser(?FakeUser $user = null): FakeUserProvider
    {
        $user = $user ?? $this->fakeUser();
        return new FakeUserProvider([$user->getAuthIdentifier() => $user]);
    }

    // =========================================================================
    // JWT Helpers
    // =========================================================================

    /**
     * Get default JWT configuration for tests.
     *
     * @return array<string, mixed>
     */
    protected function getJwtConfig(): array
    {
        return [
            'secret' => static::JWT_SECRET,
            'ttl' => static::JWT_TTL,
            'algorithm' => 'HS256',
            'issuer' => 'lalaz-auth-test',
            'audience' => 'lalaz-test',
        ];
    }

    // =========================================================================
    // JWT Assertions
    // =========================================================================

    /**
     * Assert that a string is a valid JWT format.
     */
    protected function assertValidJwt(string $token, string $message = ''): void
    {
        $this->assertIsString($token, $message);
        $this->assertSame(2, substr_count($token, '.'), $message ?: 'JWT should have exactly 3 parts separated by dots');
    }

    /**
     * Assert that a JWT contains a specific claim.
     */
    protected function assertJwtHasClaim(string $token, string $claim, string $message = ''): void
    {
        $claims = $this->getJwtClaims($token);
        $this->assertNotNull($claims, 'Failed to decode JWT');
        $this->assertArrayHasKey($claim, $claims, $message ?: "JWT should have claim '{$claim}'");
    }

    /**
     * Assert that a JWT claim has a specific value.
     */
    protected function assertJwtClaimEquals(string $token, string $claim, mixed $expected, string $message = ''): void
    {
        $claims = $this->getJwtClaims($token);
        $this->assertNotNull($claims, 'Failed to decode JWT');
        $this->assertArrayHasKey($claim, $claims, "JWT should have claim '{$claim}'");
        $this->assertEquals($expected, $claims[$claim], $message ?: "JWT claim '{$claim}' should equal expected value");
    }

    /**
     * Get JWT claims from a token (without verification).
     */
    protected function getJwtClaims(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        return json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
    }

    /**
     * Get JWT header from a token.
     */
    protected function getJwtHeader(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        return json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);
    }
}
