<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Unit\Guards;

use Lalaz\Auth\Tests\Common\AuthUnitTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use Lalaz\Auth\Guards\ApiKeyGuard;
use Lalaz\Auth\Guards\JwtGuard;
use Lalaz\Auth\Jwt\JwtEncoder;
use Lalaz\Auth\Jwt\JwtBlacklist;
use Lalaz\Auth\Providers\GenericUserProvider;

/**
 * Additional coverage tests for ApiKeyGuard and JwtGuard.
 */
#[CoversClass(ApiKeyGuard::class)]
#[CoversClass(JwtGuard::class)]
final class GuardsCoverageTest extends AuthUnitTestCase
{
    private const JWT_SECRET = 'test-secret-key-for-jwt-encoding-32-bytes';

    // =========================================================================
    // ApiKeyGuard Coverage
    // =========================================================================

    #[Test]
    public function api_key_guard_returns_name(): void
    {
        $provider = $this->createApiKeyProvider();
        $guard = new ApiKeyGuard($provider);

        $this->assertEquals('api_key', $guard->getName());
    }

    #[Test]
    public function api_key_guard_attempt_with_valid_credentials(): void
    {
        $user = $this->fakeUser(id: 'api-user');
        $provider = $this->createApiKeyProvider(['api-key-123' => $user]);

        $guard = new ApiKeyGuard($provider);
        $result = $guard->attempt(['api_key' => 'api-key-123']);

        $this->assertSame($user, $result);
        $this->assertSame($user, $guard->user());
    }

    #[Test]
    public function api_key_guard_attempt_fails_without_api_key(): void
    {
        $guard = new ApiKeyGuard($this->createApiKeyProvider());

        $result = $guard->attempt(['email' => 'test@test.com']);

        $this->assertNull($result);
    }

    #[Test]
    public function api_key_guard_attempt_fails_with_invalid_key(): void
    {
        $guard = new ApiKeyGuard($this->createApiKeyProvider());

        $result = $guard->attempt(['api_key' => 'invalid-key']);

        $this->assertNull($result);
    }

    #[Test]
    public function api_key_guard_login_sets_user(): void
    {
        $user = $this->fakeUser(id: 'login-user');
        $guard = new ApiKeyGuard($this->createApiKeyProvider());

        $guard->login($user);

        $this->assertSame($user, $guard->user());
    }

    #[Test]
    public function api_key_guard_logout_clears_user(): void
    {
        $user = $this->fakeUser(id: 'logout-user');
        $guard = new ApiKeyGuard($this->createApiKeyProvider());
        $guard->login($user);

        $guard->logout();

        $this->assertNull($guard->user());
    }

    #[Test]
    public function api_key_guard_generates_api_key(): void
    {
        $guard = new ApiKeyGuard($this->createApiKeyProvider());

        $result = $guard->generateApiKey('test');

        $this->assertArrayHasKey('key', $result);
        $this->assertArrayHasKey('hash', $result);
        $this->assertStringStartsWith('test_', $result['key']);
        $this->assertNotEquals($result['key'], $result['hash']);
    }

    #[Test]
    public function api_key_guard_hashes_api_key(): void
    {
        $guard = new ApiKeyGuard($this->createApiKeyProvider());

        $hash = $guard->hashApiKey('my-api-key');

        $this->assertEquals(hash('sha256', 'my-api-key'), $hash);
    }

    #[Test]
    public function api_key_guard_authenticates_token(): void
    {
        $user = $this->fakeUser(id: 'token-user');
        $provider = $this->createApiKeyProvider(['secret-key' => $user]);

        $guard = new ApiKeyGuard($provider);
        $result = $guard->authenticateToken('secret-key');

        $this->assertSame($user, $result);
    }

    #[Test]
    public function api_key_guard_create_token_generates_key(): void
    {
        $guard = new ApiKeyGuard($this->createApiKeyProvider());
        $user = $this->fakeUser();

        $token = $guard->createToken($user);

        // ApiKeyGuard createToken generates a new API key
        $this->assertNotEmpty($token);
        $this->assertStringStartsWith('lz_', $token);
    }

    #[Test]
    public function api_key_guard_revoke_token_returns_true(): void
    {
        $guard = new ApiKeyGuard($this->createApiKeyProvider());

        // Always returns true (no-op for stateless)
        $this->assertTrue($guard->revokeToken('any-token'));
    }

    #[Test]
    public function api_key_guard_refresh_token_returns_null(): void
    {
        $guard = new ApiKeyGuard($this->createApiKeyProvider());

        $this->assertNull($guard->refreshToken('any-token'));
    }

    #[Test]
    public function api_key_guard_returns_null_without_request(): void
    {
        $guard = new ApiKeyGuard($this->createApiKeyProvider());

        $this->assertNull($guard->getTokenFromRequest());
        $this->assertNull($guard->getApiKey());
    }

    #[Test]
    public function api_key_guard_set_header_name(): void
    {
        $guard = new ApiKeyGuard($this->createApiKeyProvider());
        $guard->setHeaderName('X-Custom-Header');

        // Verify we can chain this method
        $this->assertNull($guard->getApiKey());
    }

    #[Test]
    public function api_key_guard_set_query_param(): void
    {
        $guard = new ApiKeyGuard($this->createApiKeyProvider());
        $guard->setQueryParam('custom_key');

        // Verify we can chain this method
        $this->assertNull($guard->getApiKey());
    }

    // =========================================================================
    // JwtGuard Coverage
    // =========================================================================

    #[Test]
    public function jwt_guard_returns_name(): void
    {
        $guard = $this->createJwtGuard();

        $this->assertEquals('jwt', $guard->getName());
    }

    #[Test]
    public function jwt_guard_login_and_logout(): void
    {
        $user = $this->fakeUser(id: 'jwt-user');
        $guard = $this->createJwtGuard();

        $guard->login($user);
        $this->assertSame($user, $guard->user());

        $guard->logout();
        $this->assertNull($guard->user());
    }

    #[Test]
    public function jwt_guard_creates_token_pair(): void
    {
        $user = $this->fakeUser(id: 'pair-user');
        $guard = $this->createJwtGuard();

        $pair = $guard->createTokenPair($user, ['custom' => 'claim']);

        $this->assertArrayHasKey('access_token', $pair);
        $this->assertArrayHasKey('refresh_token', $pair);
        $this->assertNotEquals($pair['access_token'], $pair['refresh_token']);
    }

    #[Test]
    public function jwt_guard_refresh_token_creates_new_access(): void
    {
        $user = $this->fakeUser(id: 'refresh-user');
        $guard = $this->createJwtGuard($user);

        $refreshToken = $guard->getEncoder()->createRefreshToken('refresh-user');
        $newAccess = $guard->refreshToken($refreshToken);

        $this->assertNotNull($newAccess);
        $this->assertTrue($guard->getEncoder()->validate($newAccess));
    }

    #[Test]
    public function jwt_guard_refresh_token_pair(): void
    {
        $user = $this->fakeUser(id: 'pair-refresh');
        $guard = $this->createJwtGuard($user);

        $refreshToken = $guard->getEncoder()->createRefreshToken('pair-refresh');
        $pair = $guard->refreshTokenPair($refreshToken);

        $this->assertNotNull($pair);
        $this->assertArrayHasKey('access_token', $pair);
        $this->assertArrayHasKey('refresh_token', $pair);
    }

    #[Test]
    public function jwt_guard_refresh_token_pair_fails_for_invalid(): void
    {
        $guard = $this->createJwtGuard();

        $pair = $guard->refreshTokenPair('invalid-token');

        $this->assertNull($pair);
    }

    #[Test]
    public function jwt_guard_gets_encoder(): void
    {
        $encoder = new JwtEncoder(self::JWT_SECRET);
        $guard = new JwtGuard($encoder, new JwtBlacklist());

        $this->assertSame($encoder, $guard->getEncoder());
    }

    #[Test]
    public function jwt_guard_revokes_token(): void
    {
        $guard = $this->createJwtGuard();
        $token = $guard->getEncoder()->createAccessToken('user-123');

        $result = $guard->revokeToken($token);

        $this->assertTrue($result);
    }

    #[Test]
    public function jwt_guard_revoke_returns_false_for_invalid(): void
    {
        $guard = $this->createJwtGuard();

        $result = $guard->revokeToken('invalid-token');

        $this->assertFalse($result);
    }

    #[Test]
    public function jwt_guard_returns_null_without_request(): void
    {
        $guard = $this->createJwtGuard();

        $this->assertNull($guard->getTokenFromRequest());
        $this->assertNull($guard->getToken());
    }

    #[Test]
    public function jwt_guard_set_header_name(): void
    {
        $guard = $this->createJwtGuard();
        $guard->setHeaderName('X-JWT-Token');

        // Verify we can set header name
        $this->assertNull($guard->getToken());
    }

    #[Test]
    public function jwt_guard_set_token_prefix(): void
    {
        $guard = $this->createJwtGuard();
        $guard->setTokenPrefix('Token');

        // Verify we can set token prefix
        $this->assertNull($guard->getToken());
    }

    #[Test]
    public function jwt_guard_attempt_with_tokens_returns_array(): void
    {
        $user = $this->fakeUser(id: 'attempt-user');
        $provider = (new GenericUserProvider())
            ->setByCredentialsCallback(fn($c) => $user)
            ->setValidateCallback(fn($u, $c) => true);

        $encoder = new JwtEncoder(self::JWT_SECRET, issuer: 'test');
        $guard = new JwtGuard($encoder, new JwtBlacklist(), $provider);

        $result = $guard->attemptWithTokens(['email' => 'test@test.com', 'password' => 'secret']);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('access_token', $result);
        $this->assertArrayHasKey('refresh_token', $result);
    }

    #[Test]
    public function jwt_guard_attempt_with_tokens_returns_null_on_failure(): void
    {
        $provider = (new GenericUserProvider())
            ->setByCredentialsCallback(fn($c) => null);

        $encoder = new JwtEncoder(self::JWT_SECRET, issuer: 'test');
        $guard = new JwtGuard($encoder, new JwtBlacklist(), $provider);

        $result = $guard->attemptWithTokens(['email' => 'wrong@test.com', 'password' => 'bad']);

        $this->assertNull($result);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function createJwtGuard($userForProvider = null): JwtGuard
    {
        $encoder = new JwtEncoder(self::JWT_SECRET, issuer: 'test');
        $blacklist = new JwtBlacklist();

        $provider = (new GenericUserProvider())
            ->setByIdCallback(function ($id) use ($userForProvider) {
                if ($userForProvider && $userForProvider->getAuthIdentifier() === $id) {
                    return $userForProvider;
                }
                return $this->fakeUser(id: $id);
            });

        return new JwtGuard($encoder, $blacklist, $provider);
    }

    private function createApiKeyProvider(array $keyToUser = []): GenericUserProvider
    {
        return (new GenericUserProvider())
            ->setByApiKeyCallback(function ($key) use ($keyToUser) {
                return $keyToUser[$key] ?? null;
            });
    }
}
