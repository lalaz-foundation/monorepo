<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Unit\Jwt;

use PHPUnit\Framework\Attributes\CoversClass;

use Lalaz\Auth\Jwt\JwtEncoder;
use Lalaz\Auth\Jwt\Signers\HmacSha256Signer;
use Lalaz\Auth\Jwt\Signers\HmacSha384Signer;
use Lalaz\Auth\Jwt\Signers\HmacSha512Signer;
use Lalaz\Auth\Jwt\Signers\RsaSha256Signer;
use Lalaz\Auth\Tests\Common\AuthUnitTestCase;

#[CoversClass(\Lalaz\Auth\Jwt\JwtEncoder::class)]
final class JwtEncoderSignersTest extends AuthUnitTestCase
{
    private ?string $rsaPrivateKey = null;
    private ?string $rsaPublicKey = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Generate RSA key pair for testing
        $config = [
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $resource = openssl_pkey_new($config);
        openssl_pkey_export($resource, $this->rsaPrivateKey);
        $details = openssl_pkey_get_details($resource);
        $this->rsaPublicKey = $details['key'];
    }

    // =========================================================================
    // HMAC SHA256 with JwtEncoder
    // =========================================================================

    public function testEncodesAndDecodesWithHmacSha256(): void
    {
        $signer = new HmacSha256Signer('secret-key-32-chars-minimum!!!!!');
        $encoder = new JwtEncoder($signer);

        $payload = ['sub' => 1, 'name' => 'John'];
        $token = $encoder->encode($payload);
        $decoded = $encoder->decode($token);

        $this->assertSame(1, $decoded['sub']);
        $this->assertSame('John', $decoded['name']);
    }

    // =========================================================================
    // HMAC SHA384 with JwtEncoder
    // =========================================================================

    public function testEncodesAndDecodesWithHmacSha384(): void
    {
        $signer = new HmacSha384Signer('secret-key-32-chars-minimum!!!!!');
        $encoder = new JwtEncoder($signer);

        $payload = ['sub' => 2, 'role' => 'admin'];
        $token = $encoder->encode($payload);
        $decoded = $encoder->decode($token);

        $this->assertSame(2, $decoded['sub']);
        $this->assertSame('admin', $decoded['role']);
    }

    // =========================================================================
    // HMAC SHA512 with JwtEncoder
    // =========================================================================

    public function testEncodesAndDecodesWithHmacSha512(): void
    {
        $signer = new HmacSha512Signer('secret-key-32-chars-minimum!!!!!');
        $encoder = new JwtEncoder($signer);

        $payload = ['sub' => 3, 'permissions' => ['read', 'write']];
        $token = $encoder->encode($payload);
        $decoded = $encoder->decode($token);

        $this->assertSame(3, $decoded['sub']);
        $this->assertContains('read', $decoded['permissions']);
        $this->assertContains('write', $decoded['permissions']);
    }

    // =========================================================================
    // RSA SHA256 with JwtEncoder
    // =========================================================================

    public function testEncodesAndDecodesWithRsaSha256(): void
    {
        $signer = new RsaSha256Signer($this->rsaPrivateKey, $this->rsaPublicKey);
        $encoder = new JwtEncoder($signer);

        $payload = ['sub' => 4, 'aud' => 'my-audience'];
        $token = $encoder->encode($payload);
        $decoded = $encoder->decode($token);

        $this->assertSame(4, $decoded['sub']);
        $this->assertSame('my-audience', $decoded['aud']);
    }

    // =========================================================================
    // Cross-Signer Verification
    // =========================================================================

    public function testTokenFromOnSignerCannotBeDecodedByAnother(): void
    {
        $signer1 = new HmacSha256Signer('secret-key-32-chars-minimum!!!!!');
        $signer2 = new HmacSha256Signer('different-secret-32-chars!!!!!!!!');

        $encoder1 = new JwtEncoder($signer1);
        $encoder2 = new JwtEncoder($signer2);

        $token = $encoder1->encode(['sub' => 1]);

        $result = $encoder2->decode($token);

        $this->assertNull($result);
    }

    public function testTokenWithDifferentAlgorithmCannotBeDecoded(): void
    {
        $signer256 = new HmacSha256Signer('secret-key-32-chars-minimum!!!!!');
        $signer512 = new HmacSha512Signer('secret-key-32-chars-minimum!!!!!');

        $encoder256 = new JwtEncoder($signer256);
        $encoder512 = new JwtEncoder($signer512);

        $token = $encoder256->encode(['sub' => 1]);

        // Try to decode HS256 token with HS512 encoder
        $result = $encoder512->decode($token);

        $this->assertNull($result);
    }

    // =========================================================================
    // RSA Public Key Only Verification
    // =========================================================================

    public function testDecodesWithPublicKeyOnly(): void
    {
        $fullSigner = new RsaSha256Signer($this->rsaPrivateKey, $this->rsaPublicKey);
        $verifyOnlySigner = new RsaSha256Signer(null, $this->rsaPublicKey);

        $encoder = new JwtEncoder($fullSigner);
        $verifyOnlyEncoder = new JwtEncoder($verifyOnlySigner);

        $payload = ['sub' => 5, 'aud' => 'my-app'];
        $token = $encoder->encode($payload);
        $decoded = $verifyOnlyEncoder->decode($token);

        $this->assertSame(5, $decoded['sub']);
        $this->assertSame('my-app', $decoded['aud']);
    }

    // =========================================================================
    // Header Algorithm
    // =========================================================================

    public function testTokenContainsCorrectAlgorithmInHeader(): void
    {
        $signer = new HmacSha384Signer('secret-key-32-chars-minimum!!!!!');
        $encoder = new JwtEncoder($signer);

        $token = $encoder->encode(['sub' => 1]);
        $parts = explode('.', $token);
        $header = json_decode(base64_decode($parts[0]), true);

        $this->assertSame('HS384', $header['alg']);
    }

    public function testRsaTokenContainsCorrectAlgorithmInHeader(): void
    {
        $signer = new RsaSha256Signer($this->rsaPrivateKey, $this->rsaPublicKey);
        $encoder = new JwtEncoder($signer);

        $token = $encoder->encode(['sub' => 1]);
        $parts = explode('.', $token);
        $header = json_decode(base64_decode($parts[0]), true);

        $this->assertSame('RS256', $header['alg']);
    }
}
