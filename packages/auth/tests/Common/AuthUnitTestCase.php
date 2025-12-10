<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Common;

use Lalaz\Testing\Unit\UnitTestCase;
use Lalaz\Auth\Tests\Common\FakeUser;
use Lalaz\Auth\Tests\Common\FakeSession;
use Lalaz\Auth\Tests\Common\FakeUserProvider;

/**
 * Base test case for Auth package unit tests.
 *
 * Extends UnitTestCase from lalaz/testing to provide
 * common utilities plus auth-specific helpers like
 * JWT assertions and fake user/session factories.
 *
 * @package lalaz/auth
 */
abstract class AuthUnitTestCase extends UnitTestCase
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        foreach ($this->getSetUpMethods() as $method) {
            if (method_exists($this, $method)) {
                $this->{$method}();
            }
        }
    }

    /**
     * Clean up the test environment.
     */
    protected function tearDown(): void
    {
        foreach (array_reverse($this->getTearDownMethods()) as $method) {
            if (method_exists($this, $method)) {
                $this->{$method}();
            }
        }

        parent::tearDown();
    }

    /**
     * Get the list of setup methods to call.
     *
     * @return array<int, string>
     */
    protected function getSetUpMethods(): array
    {
        return [
            'setUpAuth',
            'setUpSession',
        ];
    }

    /**
     * Get the list of teardown methods to call.
     *
     * @return array<int, string>
     */
    protected function getTearDownMethods(): array
    {
        return [
            'tearDownAuth',
            'tearDownSession',
        ];
    }

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
     * Create a fake user provider for testing.
     */
    protected function fakeUserProvider(array $users = []): FakeUserProvider
    {
        return new FakeUserProvider($users);
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
        $parts = explode('.', $token);
        $this->assertCount(3, $parts, 'Invalid JWT format');

        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        $this->assertArrayHasKey($claim, $payload, $message ?: "JWT should have claim '{$claim}'");
    }

    /**
     * Get JWT claims from a token.
     */
    protected function getJwtClaims(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        return json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
    }
}
