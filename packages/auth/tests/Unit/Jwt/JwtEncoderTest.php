<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Unit\Jwt;

use PHPUnit\Framework\Attributes\CoversClass;

use Lalaz\Auth\Jwt\JwtEncoder;
use Lalaz\Auth\Tests\Common\AuthUnitTestCase;

#[CoversClass(\Lalaz\Auth\Jwt\JwtEncoder::class)]
final class JwtEncoderTest extends AuthUnitTestCase
{
    private string $secret = 'secret-key-32-chars-minimum!!!!!';
    private JwtEncoder $encoder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->encoder = new JwtEncoder($this->secret);
    }

    // =========================================================================
    // Encoding
    // =========================================================================

    public function testEncodesPayloadToToken(): void
    {
        $payload = ['sub' => 1, 'name' => 'John'];

        $token = $this->encoder->encode($payload);

        $this->assertIsString($token);
        $this->assertStringContainsString('.', $token);
    }

    public function testTokenHasThreeParts(): void
    {
        $payload = ['sub' => 1];

        $token = $this->encoder->encode($payload);
        $parts = explode('.', $token);

        $this->assertCount(3, $parts);
    }

    public function testEncodesWithDefaultExpiration(): void
    {
        $payload = ['sub' => 1];

        $token = $this->encoder->encode($payload);
        $decoded = $this->encoder->decode($token);

        $this->assertArrayHasKey('exp', $decoded);
        $this->assertGreaterThan(time(), $decoded['exp']);
    }

    public function testEncodesWithCustomExpiration(): void
    {
        $customExp = time() + 7200; // 2 hours
        $payload = ['sub' => 1, 'exp' => $customExp];

        $token = $this->encoder->encode($payload);
        $decoded = $this->encoder->decode($token);

        $this->assertSame($customExp, $decoded['exp']);
    }

    public function testAddsIssuedAtTimestamp(): void
    {
        $payload = ['sub' => 1];

        $token = $this->encoder->encode($payload);
        $decoded = $this->encoder->decode($token);

        $this->assertArrayHasKey('iat', $decoded);
        $this->assertLessThanOrEqual(time(), $decoded['iat']);
    }

    // =========================================================================
    // Decoding
    // =========================================================================

    public function testDecodesValidToken(): void
    {
        $payload = ['sub' => 123, 'name' => 'John', 'role' => 'admin'];

        $token = $this->encoder->encode($payload);
        $decoded = $this->encoder->decode($token);

        $this->assertSame(123, $decoded['sub']);
        $this->assertSame('John', $decoded['name']);
        $this->assertSame('admin', $decoded['role']);
    }

    public function testReturnsNullForInvalidToken(): void
    {
        $result = $this->encoder->decode('invalid.token.here');

        $this->assertNull($result);
    }

    public function testReturnsNullForMalformedToken(): void
    {
        $result = $this->encoder->decode('malformed');

        $this->assertNull($result);
    }

    public function testReturnsNullForTamperedToken(): void
    {
        $payload = ['sub' => 1];
        $token = $this->encoder->encode($payload);

        // Tamper with the token
        $parts = explode('.', $token);
        $parts[1] = base64_encode(json_encode(['sub' => 2]));
        $tamperedToken = implode('.', $parts);

        $result = $this->encoder->decode($tamperedToken);

        $this->assertNull($result);
    }

    public function testReturnsNullForExpiredToken(): void
    {
        $payload = ['sub' => 1, 'exp' => time() - 3600];
        $token = $this->encoder->encode($payload);

        $result = $this->encoder->decode($token);

        $this->assertNull($result);
    }

    // =========================================================================
    // Different Secret Keys
    // =========================================================================

    public function testReturnsNullWhenDecodingWithDifferentSecret(): void
    {
        $encoder1 = new JwtEncoder('secret-key-32-chars-minimum!!!!!');
        $encoder2 = new JwtEncoder('different-secret-32-chars!!!!!!');

        $token = $encoder1->encode(['sub' => 1]);

        $result = $encoder2->decode($token);

        $this->assertNull($result);
    }

    // =========================================================================
    // Payload Preservation
    // =========================================================================

    public function testPreservesNestedArraysInPayload(): void
    {
        $payload = [
            'sub' => 1,
            'data' => [
                'nested' => [
                    'value' => 'deep'
                ]
            ]
        ];

        $token = $this->encoder->encode($payload);
        $decoded = $this->encoder->decode($token);

        $this->assertSame('deep', $decoded['data']['nested']['value']);
    }

    public function testPreservesBooleanValues(): void
    {
        $payload = ['sub' => 1, 'active' => true, 'disabled' => false];

        $token = $this->encoder->encode($payload);
        $decoded = $this->encoder->decode($token);

        $this->assertTrue($decoded['active']);
        $this->assertFalse($decoded['disabled']);
    }

    public function testPreservesNullValues(): void
    {
        $payload = ['sub' => 1, 'nullable' => null];

        $token = $this->encoder->encode($payload);
        $decoded = $this->encoder->decode($token);

        $this->assertNull($decoded['nullable']);
    }

    public function testPreservesNumericValues(): void
    {
        $payload = ['sub' => 1, 'integer' => 42, 'float' => 3.14];

        $token = $this->encoder->encode($payload);
        $decoded = $this->encoder->decode($token);

        $this->assertSame(42, $decoded['integer']);
        $this->assertEqualsWithDelta(3.14, $decoded['float'], 0.001);
    }

    // =========================================================================
    // Validation
    // =========================================================================

    public function testValidateReturnsTrueForValidToken(): void
    {
        $token = $this->encoder->encode(['sub' => 1]);

        $this->assertTrue($this->encoder->validate($token));
    }

    public function testValidateReturnsFalseForInvalidToken(): void
    {
        $this->assertFalse($this->encoder->validate('invalid.token.here'));
    }

    // =========================================================================
    // Subject and Claims Extraction
    // =========================================================================

    public function testGetSubjectReturnsSubjectClaim(): void
    {
        $token = $this->encoder->encode(['sub' => 'user-123']);

        $this->assertSame('user-123', $this->encoder->getSubject($token));
    }

    public function testGetSubjectReturnsNullForInvalidToken(): void
    {
        $this->assertNull($this->encoder->getSubject('invalid.token'));
    }

    public function testGetClaimsReturnsPayloadWithoutValidation(): void
    {
        // Even an expired token should return claims
        $payload = ['sub' => 1, 'exp' => time() - 3600];
        $token = $this->encoder->encode($payload);

        $claims = $this->encoder->getClaims($token);

        $this->assertSame(1, $claims['sub']);
    }

    // =========================================================================
    // Access and Refresh Tokens
    // =========================================================================

    public function testCreateAccessToken(): void
    {
        $token = $this->encoder->createAccessToken(123);
        $decoded = $this->encoder->decode($token);

        $this->assertSame('123', $decoded['sub']);
        $this->assertSame('access', $decoded['type']);
    }

    public function testCreateRefreshToken(): void
    {
        $token = $this->encoder->createRefreshToken(123);
        $decoded = $this->encoder->decode($token);

        $this->assertSame('123', $decoded['sub']);
        $this->assertSame('refresh', $decoded['type']);
    }

    public function testIsRefreshToken(): void
    {
        $accessToken = $this->encoder->createAccessToken(123);
        $refreshToken = $this->encoder->createRefreshToken(123);

        $this->assertFalse($this->encoder->isRefreshToken($accessToken));
        $this->assertTrue($this->encoder->isRefreshToken($refreshToken));
    }

    // =========================================================================
    // Algorithm
    // =========================================================================

    public function testGetAlgorithmReturnsSignerAlgorithm(): void
    {
        $this->assertSame('HS256', $this->encoder->getAlgorithm());
    }
}
