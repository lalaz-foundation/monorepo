<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Integration;

use Lalaz\Auth\Tests\Common\AuthUnitTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Lalaz\Auth\Jwt\JwtEncoder;
use Lalaz\Auth\Jwt\JwtBlacklist;
use Lalaz\Auth\Jwt\Signers\RsaSha256Signer;
use Lalaz\Auth\Guards\JwtGuard;
use Lalaz\Auth\Providers\GenericUserProvider;
use Lalaz\Auth\NativePasswordHasher;
use InvalidArgumentException;
use RuntimeException;

/**
 * Integration tests for RSA-based JWT authentication.
 *
 * Tests the complete RSA signing/verification workflow including:
 * - Key pair generation
 * - Asymmetric signing (private key signs, public key verifies)
 * - Token creation and validation with RSA
 * - Public-key-only verification scenarios
 * - Guard integration with RSA encoder
 * - Key rotation scenarios
 *
 * @package Lalaz\Auth\Tests\Integration
 */
#[CoversClass(RsaSha256Signer::class)]
#[CoversClass(JwtEncoder::class)]
#[CoversClass(JwtGuard::class)]
#[Group('integration')]
#[Group('rsa')]
final class RsaJwtIntegrationTest extends AuthUnitTestCase
{
    private string $privateKey;
    private string $publicKey;
    private RsaSha256Signer $signer;
    private JwtEncoder $encoder;

    protected function setUp(): void
    {
        parent::setUp();

        // Generate RSA key pair for testing
        $keys = RsaSha256Signer::generateKeyPair(2048);
        $this->privateKey = $keys['privateKey'];
        $this->publicKey = $keys['publicKey'];

        $this->signer = new RsaSha256Signer($this->privateKey, $this->publicKey);
        $this->encoder = new JwtEncoder($this->signer, issuer: 'lalaz-test', expiration: 3600);
    }

    // =========================================================================
    // Key Generation Tests
    // =========================================================================

    #[Test]
    public function it_generates_valid_key_pair(): void
    {
        $keys = RsaSha256Signer::generateKeyPair();

        $this->assertArrayHasKey('privateKey', $keys);
        $this->assertArrayHasKey('publicKey', $keys);
        $this->assertStringContainsString('-----BEGIN PRIVATE KEY-----', $keys['privateKey']);
        $this->assertStringContainsString('-----BEGIN PUBLIC KEY-----', $keys['publicKey']);
    }

    #[Test]
    public function it_generates_keys_with_specified_size(): void
    {
        $keys = RsaSha256Signer::generateKeyPair(4096);

        // Verify key is usable
        $signer = new RsaSha256Signer($keys['privateKey'], $keys['publicKey']);
        $signature = $signer->sign('test-data');

        $this->assertTrue($signer->verify('test-data', $signature));
    }

    #[Test]
    public function generated_keys_can_sign_and_verify(): void
    {
        $keys = RsaSha256Signer::generateKeyPair();
        $signer = new RsaSha256Signer($keys['privateKey'], $keys['publicKey']);

        $data = 'header.payload';
        $signature = $signer->sign($data);

        $this->assertTrue($signer->verify($data, $signature));
        $this->assertFalse($signer->verify('tampered.data', $signature));
    }

    // =========================================================================
    // Signer Initialization Tests
    // =========================================================================

    #[Test]
    public function it_requires_at_least_one_key(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one of private key or public key must be provided');

        new RsaSha256Signer(null, null);
    }

    #[Test]
    public function it_can_be_created_with_private_key_only(): void
    {
        $signer = new RsaSha256Signer($this->privateKey, null);

        // Can sign
        $signature = $signer->sign('test-data');
        $this->assertNotEmpty($signature);

        // Can verify using extracted public key
        $this->assertTrue($signer->verify('test-data', $signature));
    }

    #[Test]
    public function it_can_be_created_with_public_key_only(): void
    {
        // Create signature with full signer
        $signature = $this->signer->sign('test-data');

        // Verify with public-key-only signer
        $verifyOnlySigner = new RsaSha256Signer(null, $this->publicKey);
        $this->assertTrue($verifyOnlySigner->verify('test-data', $signature));
    }

    #[Test]
    public function it_throws_when_signing_without_private_key(): void
    {
        $signer = new RsaSha256Signer(null, $this->publicKey);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot sign: no private key configured');

        $signer->sign('test-data');
    }

    #[Test]
    public function it_rejects_invalid_private_key(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid private key');

        new RsaSha256Signer('not-a-valid-key', null);
    }

    #[Test]
    public function it_rejects_invalid_public_key(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid public key');

        new RsaSha256Signer(null, 'not-a-valid-key');
    }

    // =========================================================================
    // Token Creation and Validation
    // =========================================================================

    #[Test]
    public function it_creates_tokens_with_rs256_algorithm(): void
    {
        $token = $this->encoder->createAccessToken('user-123');

        $parts = explode('.', $token);
        $header = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);

        $this->assertEquals('RS256', $header['alg']);
        $this->assertEquals('JWT', $header['typ']);
    }

    #[Test]
    public function it_validates_rsa_signed_tokens(): void
    {
        $token = $this->encoder->createAccessToken('user-123', [
            'email' => 'user@example.com',
            'roles' => ['admin', 'user'],
        ]);

        $this->assertTrue($this->encoder->validate($token));

        $payload = $this->encoder->decode($token);
        $this->assertEquals('user-123', $payload['sub']);
        $this->assertEquals('user@example.com', $payload['email']);
        $this->assertEquals(['admin', 'user'], $payload['roles']);
    }

    #[Test]
    public function it_detects_tampered_tokens(): void
    {
        $token = $this->encoder->createAccessToken('user-123');

        // Tamper with payload
        $parts = explode('.', $token);
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        $payload['sub'] = 'hacker-456';
        $parts[1] = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $tamperedToken = implode('.', $parts);

        $this->assertFalse($this->encoder->validate($tamperedToken));
    }

    #[Test]
    public function it_rejects_tokens_signed_with_different_key(): void
    {
        // Create token with different key pair
        $otherKeys = RsaSha256Signer::generateKeyPair();
        $otherSigner = new RsaSha256Signer($otherKeys['privateKey'], $otherKeys['publicKey']);
        $otherEncoder = new JwtEncoder($otherSigner, issuer: 'lalaz-test');

        $token = $otherEncoder->createAccessToken('user-123');

        // Should not validate with our encoder
        $this->assertFalse($this->encoder->validate($token));
    }

    // =========================================================================
    // Public Key Verification Scenario
    // =========================================================================

    #[Test]
    public function it_supports_asymmetric_verification_workflow(): void
    {
        // Scenario: Backend signs tokens, frontend/gateway verifies with public key

        // Backend: has private key, creates tokens
        $backendSigner = new RsaSha256Signer($this->privateKey, $this->publicKey);
        $backendEncoder = new JwtEncoder($backendSigner, issuer: 'backend');

        $token = $backendEncoder->createAccessToken('user-123', [
            'permissions' => ['read', 'write'],
        ]);

        // Gateway: only has public key, verifies tokens
        $gatewaySigner = new RsaSha256Signer(null, $this->publicKey);
        $gatewayEncoder = new JwtEncoder($gatewaySigner, issuer: 'backend');

        $this->assertTrue($gatewayEncoder->validate($token));

        $payload = $gatewayEncoder->decode($token);
        $this->assertEquals('user-123', $payload['sub']);
        $this->assertEquals(['read', 'write'], $payload['permissions']);
    }

    #[Test]
    public function verification_only_encoder_cannot_create_tokens(): void
    {
        $verifyOnlySigner = new RsaSha256Signer(null, $this->publicKey);
        $verifyOnlyEncoder = new JwtEncoder($verifyOnlySigner, issuer: 'test');

        $this->expectException(RuntimeException::class);
        $verifyOnlyEncoder->createAccessToken('user-123');
    }

    // =========================================================================
    // Access and Refresh Tokens
    // =========================================================================

    #[Test]
    public function it_creates_separate_access_and_refresh_tokens(): void
    {
        $accessToken = $this->encoder->createAccessToken('user-123');
        $refreshToken = $this->encoder->createRefreshToken('user-123');

        // Both are valid
        $this->assertTrue($this->encoder->validate($accessToken));
        $this->assertTrue($this->encoder->validate($refreshToken));

        // Different tokens
        $this->assertNotEquals($accessToken, $refreshToken);

        // Access token has 'access' type
        $accessPayload = $this->encoder->decode($accessToken);
        $this->assertEquals('access', $accessPayload['type']);

        // Refresh token has 'refresh' type
        $refreshPayload = $this->encoder->decode($refreshToken);
        $this->assertEquals('refresh', $refreshPayload['type']);
    }

    #[Test]
    public function it_validates_token_type_in_payload(): void
    {
        $accessToken = $this->encoder->createAccessToken('user-123');
        $refreshToken = $this->encoder->createRefreshToken('user-123');

        // Both tokens are valid
        $this->assertTrue($this->encoder->validate($accessToken));
        $this->assertTrue($this->encoder->validate($refreshToken));

        // Access token has 'access' type in payload
        $accessPayload = $this->encoder->decode($accessToken);
        $this->assertEquals('access', $accessPayload['type']);

        // Refresh token has 'refresh' type in payload
        $refreshPayload = $this->encoder->decode($refreshToken);
        $this->assertEquals('refresh', $refreshPayload['type']);

        // Can manually check type
        $this->assertNotEquals($accessPayload['type'], $refreshPayload['type']);
    }

    // =========================================================================
    // Guard Integration
    // =========================================================================

    #[Test]
    public function it_integrates_with_jwt_guard(): void
    {
        $hasher = new NativePasswordHasher();
        $user = $this->fakeUser(
            id: 'user@test.com',
            password: $hasher->hash('secret123')
        );

        $provider = (new GenericUserProvider())
            ->setByCredentialsCallback(fn($creds) => ($creds['email'] ?? '') === 'user@test.com' ? $user : null)
            ->setByIdCallback(fn($id) => $id === 'user@test.com' ? $user : null);

        $guard = new JwtGuard($this->encoder, new JwtBlacklist(), $provider);

        // Attempt login
        $result = $guard->attemptWithTokens([
            'email' => 'user@test.com',
            'password' => 'secret123',
        ]);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('access_token', $result);
        $this->assertArrayHasKey('refresh_token', $result);

        // Verify tokens are RS256
        $header = $this->getJwtHeader($result['access_token']);
        $this->assertEquals('RS256', $header['alg']);
    }

    #[Test]
    public function it_authenticates_request_with_rsa_token(): void
    {
        $user = $this->fakeUser(id: 'user-123');

        $provider = (new GenericUserProvider())
            ->setByIdCallback(fn($id) => $id === 'user-123' ? $user : null);

        $guard = new JwtGuard($this->encoder, new JwtBlacklist(), $provider);

        // Create token
        $token = $this->encoder->createAccessToken('user-123');

        // Authenticate
        $authenticated = $guard->authenticateToken($token);

        $this->assertNotNull($authenticated);
        $this->assertEquals('user-123', $authenticated->getAuthIdentifier());
    }

    // =========================================================================
    // Key Rotation Scenario
    // =========================================================================

    #[Test]
    public function it_supports_key_rotation_verification(): void
    {
        // Old keys (being rotated out)
        $oldKeys = RsaSha256Signer::generateKeyPair();
        $oldSigner = new RsaSha256Signer($oldKeys['privateKey'], $oldKeys['publicKey']);
        $oldEncoder = new JwtEncoder($oldSigner, issuer: 'lalaz');

        // New keys (being rotated in)
        $newKeys = RsaSha256Signer::generateKeyPair();
        $newSigner = new RsaSha256Signer($newKeys['privateKey'], $newKeys['publicKey']);
        $newEncoder = new JwtEncoder($newSigner, issuer: 'lalaz');

        // Token created with old key
        $oldToken = $oldEncoder->createAccessToken('user-123');

        // Token created with new key
        $newToken = $newEncoder->createAccessToken('user-123');

        // New encoder can't verify old tokens
        $this->assertFalse($newEncoder->validate($oldToken));

        // Old encoder can't verify new tokens
        $this->assertFalse($oldEncoder->validate($newToken));

        // Each encoder validates its own tokens
        $this->assertTrue($oldEncoder->validate($oldToken));
        $this->assertTrue($newEncoder->validate($newToken));
    }

    // =========================================================================
    // Blacklist Integration
    // =========================================================================

    #[Test]
    public function it_blacklists_rsa_tokens(): void
    {
        $blacklist = new JwtBlacklist();

        $token = $this->encoder->createAccessToken('user-123');
        $payload = $this->encoder->decode($token);
        $jti = $payload['jti'];
        $exp = $payload['exp'];

        // Not blacklisted initially
        $this->assertFalse($blacklist->has($jti));

        // Blacklist it using JTI and expiration
        $blacklist->add($jti, $exp);

        // Now blacklisted
        $this->assertTrue($blacklist->has($jti));

        // Validate that isBlacklisted also works
        $this->assertTrue($blacklist->isBlacklisted($jti));
    }

    // =========================================================================
    // Signature Determinism
    // =========================================================================

    #[Test]
    public function rsa_signatures_are_deterministic(): void
    {
        $data = 'same-data-multiple-times';

        $sig1 = $this->signer->sign($data);
        $sig2 = $this->signer->sign($data);

        // RSA-PKCS#1 v1.5 signatures are deterministic
        $this->assertEquals($sig1, $sig2);
    }

    // =========================================================================
    // Edge Cases
    // =========================================================================

    #[Test]
    public function it_handles_large_payloads(): void
    {
        $largeClaims = [
            'permissions' => array_fill(0, 100, 'permission-' . uniqid()),
            'metadata' => str_repeat('x', 1000),
        ];

        $token = $this->encoder->createAccessToken('user-123', $largeClaims);

        $this->assertTrue($this->encoder->validate($token));

        $payload = $this->encoder->decode($token);
        $this->assertCount(100, $payload['permissions']);
        $this->assertEquals(1000, strlen($payload['metadata']));
    }

    #[Test]
    public function it_handles_special_characters_in_claims(): void
    {
        $specialClaims = [
            'unicode' => '日本語 🔐 مرحبا',
            'special' => '<script>alert("xss")</script>',
            'newlines' => "line1\nline2\rline3",
        ];

        $token = $this->encoder->createAccessToken('user-123', $specialClaims);

        $this->assertTrue($this->encoder->validate($token));

        $payload = $this->encoder->decode($token);
        $this->assertEquals($specialClaims['unicode'], $payload['unicode']);
        $this->assertEquals($specialClaims['special'], $payload['special']);
        $this->assertEquals($specialClaims['newlines'], $payload['newlines']);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function getJwtHeader(string $token): array
    {
        $parts = explode('.', $token);
        return json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);
    }
}
