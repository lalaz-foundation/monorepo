<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Integration;

use Lalaz\Auth\Tests\Common\AuthIntegrationTestCase;
use Lalaz\Auth\Jwt\JwtEncoder;
use Lalaz\Auth\Jwt\JwtBlacklist;
use Lalaz\Auth\Jwt\Signers\HmacSha256Signer;
use Lalaz\Auth\Jwt\Signers\HmacSha384Signer;
use Lalaz\Auth\Jwt\Signers\HmacSha512Signer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Integration tests for JWT encoding, decoding, and blacklisting.
 *
 * These tests verify the complete JWT flow including:
 * - Token creation with different signers
 * - Token validation and decoding
 * - Access and refresh token workflows
 * - Token blacklisting integration
 * - Token refresh scenarios
 *
 * @package lalaz/auth
 */
final class JwtFlowIntegrationTest extends AuthIntegrationTestCase
{
    private JwtEncoder $encoder;
    private JwtBlacklist $blacklist;

    protected function setUp(): void
    {
        parent::setUp();

        $this->encoder = new JwtEncoder(
            signerOrSecret: self::JWT_SECRET,
            expiration: self::JWT_TTL,
            refreshExpiration: 86400, // 24 hours
            issuer: 'lalaz-test'
        );

        $this->blacklist = new JwtBlacklist();
        $this->blacklist->clear(); // Clean state for each test
    }

    // =========================================================================
    // Basic Token Flow Tests
    // =========================================================================

    #[Test]
    public function it_creates_and_validates_access_token(): void
    {
        $userId = 'user-123';

        // Create token
        $token = $this->encoder->createAccessToken($userId);

        // Validate structure
        $this->assertValidJwt($token);

        // Validate token
        $this->assertTrue($this->encoder->validate($token));

        // Decode and verify claims
        $payload = $this->encoder->decode($token);

        $this->assertNotNull($payload);
        $this->assertEquals($userId, $payload['sub']);
        $this->assertEquals('access', $payload['type']);
        $this->assertEquals('lalaz-test', $payload['iss']);
        $this->assertArrayHasKey('jti', $payload);
        $this->assertArrayHasKey('iat', $payload);
        $this->assertArrayHasKey('exp', $payload);
    }

    #[Test]
    public function it_creates_and_validates_refresh_token(): void
    {
        $userId = 'user-456';

        // Create refresh token
        $token = $this->encoder->createRefreshToken($userId);

        // Validate
        $this->assertValidJwt($token);
        $this->assertTrue($this->encoder->validate($token));
        $this->assertTrue($this->encoder->isRefreshToken($token));

        // Verify longer expiration
        $payload = $this->encoder->decode($token);
        $this->assertNotNull($payload);
        $this->assertEquals('refresh', $payload['type']);

        // Refresh tokens should have longer TTL
        $expectedExp = time() + 86400;
        $this->assertGreaterThan(time() + 3600, $payload['exp']);
        $this->assertLessThanOrEqual($expectedExp + 5, $payload['exp']); // Allow 5s tolerance
    }

    #[Test]
    public function it_extracts_subject_from_token(): void
    {
        $userId = 'user-789';
        $token = $this->encoder->createAccessToken($userId);

        $subject = $this->encoder->getSubject($token);

        $this->assertEquals($userId, $subject);
    }

    #[Test]
    public function it_extracts_jti_from_token(): void
    {
        $token = $this->encoder->createAccessToken('user-1');

        $jti = $this->encoder->getJti($token);

        $this->assertNotNull($jti);
        $this->assertIsString($jti);
        $this->assertEquals(32, strlen($jti)); // 16 bytes = 32 hex chars
    }

    // =========================================================================
    // Token with Custom Claims Tests
    // =========================================================================

    #[Test]
    public function it_creates_token_with_custom_claims(): void
    {
        $userId = 'user-123';
        $customClaims = [
            'roles' => ['admin', 'editor'],
            'permissions' => ['read', 'write', 'delete'],
            'org_id' => 'org-456',
        ];

        $token = $this->encoder->createAccessToken($userId, $customClaims);
        $payload = $this->encoder->decode($token);

        $this->assertNotNull($payload);
        $this->assertEquals(['admin', 'editor'], $payload['roles']);
        $this->assertEquals(['read', 'write', 'delete'], $payload['permissions']);
        $this->assertEquals('org-456', $payload['org_id']);
    }

    #[Test]
    public function custom_claims_can_override_standard_claims(): void
    {
        $userId = 'user-123';

        // Custom claims can override standard claims (by design for flexibility)
        $token = $this->encoder->createAccessToken($userId, [
            'iss' => 'custom-issuer',
        ]);

        $payload = $this->encoder->decode($token);

        // Decode returns null because issuer doesn't match encoder's issuer
        // This is expected - tokens with wrong issuer are rejected
        $this->assertNull($payload);

        // But we can still get claims without validation
        $claims = $this->encoder->getClaims($token);
        $this->assertEquals('custom-issuer', $claims['iss']);
    }

    // =========================================================================
    // Token Validation Tests
    // =========================================================================

    #[Test]
    public function it_rejects_tampered_token(): void
    {
        $token = $this->encoder->createAccessToken('user-123');

        // Tamper with payload
        $parts = explode('.', $token);
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        $payload['sub'] = 'hacked-user';
        $tamperedPayload = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $tamperedToken = $parts[0] . '.' . $tamperedPayload . '.' . $parts[2];

        // Should fail validation
        $this->assertFalse($this->encoder->validate($tamperedToken));
        $this->assertNull($this->encoder->decode($tamperedToken));
    }

    #[Test]
    public function it_rejects_expired_token(): void
    {
        // Create encoder with very short expiration
        $encoder = new JwtEncoder(
            signerOrSecret: self::JWT_SECRET,
            expiration: 1, // 1 second
            issuer: 'lalaz-test'
        );

        $token = $encoder->createAccessToken('user-123');

        // Wait for expiration
        sleep(2);

        // Should fail validation
        $this->assertFalse($encoder->validate($token));
        $this->assertNull($encoder->decode($token));
    }

    #[Test]
    public function it_rejects_invalid_issuer(): void
    {
        // Create token with different issuer
        $otherEncoder = new JwtEncoder(
            signerOrSecret: self::JWT_SECRET,
            issuer: 'other-issuer'
        );

        $token = $otherEncoder->createAccessToken('user-123');

        // Our encoder should reject it (different issuer)
        $this->assertNull($this->encoder->decode($token));
    }

    #[Test]
    public function it_rejects_malformed_token(): void
    {
        $this->assertFalse($this->encoder->validate('not-a-jwt'));
        $this->assertFalse($this->encoder->validate('only.two'));
        $this->assertFalse($this->encoder->validate(''));
        $this->assertNull($this->encoder->decode('invalid'));
    }

    // =========================================================================
    // Blacklist Integration Tests
    // =========================================================================

    #[Test]
    public function it_blacklists_token_by_jti(): void
    {
        $token = $this->encoder->createAccessToken('user-123');
        $jti = $this->encoder->getJti($token);
        $expiration = $this->encoder->getExpiration($token);

        $this->assertFalse($this->blacklist->isBlacklisted($jti));

        // Blacklist the token
        $this->blacklist->add($jti, $expiration);

        $this->assertTrue($this->blacklist->isBlacklisted($jti));
    }

    #[Test]
    public function blacklisted_token_can_be_removed(): void
    {
        $token = $this->encoder->createAccessToken('user-123');
        $jti = $this->encoder->getJti($token);
        $expiration = $this->encoder->getExpiration($token);

        $this->blacklist->add($jti, $expiration);
        $this->assertTrue($this->blacklist->isBlacklisted($jti));

        $this->blacklist->remove($jti);
        $this->assertFalse($this->blacklist->isBlacklisted($jti));
    }

    #[Test]
    public function it_validates_token_against_blacklist(): void
    {
        $token = $this->encoder->createAccessToken('user-123');
        $jti = $this->encoder->getJti($token);
        $expiration = $this->encoder->getExpiration($token);

        // Token valid before blacklist
        $this->assertTrue($this->encoder->validate($token));

        // Blacklist it
        $this->blacklist->add($jti, $expiration);

        // Token structure is still valid
        $this->assertTrue($this->encoder->validate($token));

        // But should be rejected when checking blacklist
        $this->assertTrue($this->blacklist->isBlacklisted($jti));
    }

    // =========================================================================
    // Token Refresh Flow Tests
    // =========================================================================

    #[Test]
    public function it_refreshes_access_token_using_refresh_token(): void
    {
        $userId = 'user-123';

        // Create initial tokens
        $accessToken = $this->encoder->createAccessToken($userId);
        $refreshToken = $this->encoder->createRefreshToken($userId);

        // Verify refresh token
        $this->assertTrue($this->encoder->isRefreshToken($refreshToken));
        $this->assertFalse($this->encoder->isRefreshToken($accessToken));

        // Simulate refresh: validate refresh token and create new access token
        $refreshPayload = $this->encoder->decode($refreshToken);
        $this->assertNotNull($refreshPayload);
        $this->assertEquals($userId, $refreshPayload['sub']);

        // Create new access token
        $newAccessToken = $this->encoder->createAccessToken($refreshPayload['sub']);

        // New token should be valid
        $this->assertTrue($this->encoder->validate($newAccessToken));
        $this->assertEquals($userId, $this->encoder->getSubject($newAccessToken));

        // New token should have different JTI
        $this->assertNotEquals(
            $this->encoder->getJti($accessToken),
            $this->encoder->getJti($newAccessToken)
        );
    }

    #[Test]
    public function it_blacklists_old_token_on_refresh(): void
    {
        $userId = 'user-123';

        $oldAccessToken = $this->encoder->createAccessToken($userId);
        $oldJti = $this->encoder->getJti($oldAccessToken);
        $oldExp = $this->encoder->getExpiration($oldAccessToken);

        // Blacklist old token
        $this->blacklist->add($oldJti, $oldExp);

        // Create new token
        $newAccessToken = $this->encoder->createAccessToken($userId);
        $newJti = $this->encoder->getJti($newAccessToken);

        // Old token blacklisted, new token not
        $this->assertTrue($this->blacklist->isBlacklisted($oldJti));
        $this->assertFalse($this->blacklist->isBlacklisted($newJti));
    }

    // =========================================================================
    // Multiple Signers Tests
    // =========================================================================

    #[Test]
    #[DataProvider('signerProvider')]
    public function it_works_with_different_signers(string $signerClass, string $expectedAlg): void
    {
        $signer = new $signerClass(self::JWT_SECRET);
        $encoder = new JwtEncoder($signer, issuer: 'lalaz-test');

        $token = $encoder->createAccessToken('user-123');

        // Verify algorithm in header
        $header = $this->getJwtHeader($token);
        $this->assertEquals($expectedAlg, $header['alg']);

        // Token should be valid
        $this->assertTrue($encoder->validate($token));
    }

    public static function signerProvider(): array
    {
        return [
            'HS256' => [HmacSha256Signer::class, 'HS256'],
            'HS384' => [HmacSha384Signer::class, 'HS384'],
            'HS512' => [HmacSha512Signer::class, 'HS512'],
        ];
    }

    #[Test]
    public function tokens_from_different_signers_are_incompatible(): void
    {
        $hs256Encoder = new JwtEncoder(new HmacSha256Signer(self::JWT_SECRET), issuer: 'lalaz-test');
        $hs384Encoder = new JwtEncoder(new HmacSha384Signer(self::JWT_SECRET), issuer: 'lalaz-test');

        $token = $hs256Encoder->createAccessToken('user-123');

        // Same secret, different algorithm = invalid
        $this->assertFalse($hs384Encoder->validate($token));
    }

    // =========================================================================
    // Edge Cases Tests
    // =========================================================================

    #[Test]
    public function it_handles_unicode_in_claims(): void
    {
        $token = $this->encoder->createAccessToken('user-123', [
            'name' => '日本語テスト',
            'emoji' => '🔐🚀',
        ]);

        $payload = $this->encoder->decode($token);

        $this->assertEquals('日本語テスト', $payload['name']);
        $this->assertEquals('🔐🚀', $payload['emoji']);
    }

    #[Test]
    public function it_handles_nested_arrays_in_claims(): void
    {
        $complexClaims = [
            'metadata' => [
                'level1' => [
                    'level2' => [
                        'value' => 'deep',
                    ],
                ],
            ],
        ];

        $token = $this->encoder->createAccessToken('user-123', $complexClaims);
        $payload = $this->encoder->decode($token);

        $this->assertEquals('deep', $payload['metadata']['level1']['level2']['value']);
    }

    #[Test]
    public function blacklist_cleanup_removes_expired_entries(): void
    {
        // Add entry that's already expired
        $this->blacklist->add('expired-jti', time() - 100);

        // Add valid entry
        $this->blacklist->add('valid-jti', time() + 3600);

        // Cleanup
        $cleaned = $this->blacklist->cleanup();

        $this->assertEquals(1, $cleaned);
        $this->assertFalse($this->blacklist->isBlacklisted('expired-jti'));
        $this->assertTrue($this->blacklist->isBlacklisted('valid-jti'));
    }

    #[Test]
    public function get_claims_works_on_expired_token(): void
    {
        $encoder = new JwtEncoder(
            signerOrSecret: self::JWT_SECRET,
            expiration: 1,
            issuer: 'lalaz-test'
        );

        $token = $encoder->createAccessToken('user-123', ['important' => 'data']);

        sleep(2);

        // decode() returns null for expired
        $this->assertNull($encoder->decode($token));

        // getClaims() still works (for debugging/logging)
        $claims = $encoder->getClaims($token);
        $this->assertNotNull($claims);
        $this->assertEquals('user-123', $claims['sub']);
        $this->assertEquals('data', $claims['important']);
    }

    #[Test]
    public function verify_only_checks_signature_not_expiration(): void
    {
        $encoder = new JwtEncoder(
            signerOrSecret: self::JWT_SECRET,
            expiration: 1,
            issuer: 'lalaz-test'
        );

        $token = $encoder->createAccessToken('user-123');

        sleep(2);

        // Token is expired
        $this->assertFalse($encoder->validate($token));

        // But signature is still valid
        $this->assertTrue($encoder->verify($token));
    }
}
