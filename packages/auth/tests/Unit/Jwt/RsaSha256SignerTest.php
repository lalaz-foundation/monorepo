<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Unit\Jwt;

use PHPUnit\Framework\Attributes\CoversClass;

use Lalaz\Auth\Jwt\Signers\RsaSha256Signer;
use Lalaz\Auth\Tests\Common\AuthUnitTestCase;

#[CoversClass(\Lalaz\Auth\Jwt\Signers\RsaSha256Signer::class)]
final class RsaSha256SignerTest extends AuthUnitTestCase
{
    private ?string $privateKey = null;
    private ?string $publicKey = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Generate a key pair for testing
        $config = [
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $resource = openssl_pkey_new($config);
        openssl_pkey_export($resource, $this->privateKey);
        $details = openssl_pkey_get_details($resource);
        $this->publicKey = $details['key'];
    }

    // =========================================================================
    // Algorithm
    // =========================================================================

    public function testReturnsCorrectAlgorithm(): void
    {
        $signer = new RsaSha256Signer($this->privateKey, $this->publicKey);

        $this->assertSame('RS256', $signer->getAlgorithm());
    }

    // =========================================================================
    // Signing
    // =========================================================================

    public function testSignsData(): void
    {
        $signer = new RsaSha256Signer($this->privateKey, $this->publicKey);
        $data = 'header.payload';

        $signature = $signer->sign($data);

        $this->assertNotEmpty($signature);
        $this->assertIsString($signature);
    }

    public function testProducesConsistentSignatures(): void
    {
        $signer = new RsaSha256Signer($this->privateKey, $this->publicKey);
        $data = 'header.payload';

        // RSA signatures are deterministic with the same data and key
        $signature1 = $signer->sign($data);
        $signature2 = $signer->sign($data);

        // Note: RSA-PKCS#1 v1.5 signatures are deterministic
        $this->assertSame($signature1, $signature2);
    }

    // =========================================================================
    // Verification
    // =========================================================================

    public function testVerifiesValidSignature(): void
    {
        $signer = new RsaSha256Signer($this->privateKey, $this->publicKey);
        $data = 'header.payload';

        $signature = $signer->sign($data);

        $this->assertTrue($signer->verify($data, $signature));
    }

    public function testRejectsInvalidSignature(): void
    {
        $signer = new RsaSha256Signer($this->privateKey, $this->publicKey);
        $data = 'header.payload';

        $this->assertFalse($signer->verify($data, 'invalid-signature'));
    }

    public function testRejectsTamperedData(): void
    {
        $signer = new RsaSha256Signer($this->privateKey, $this->publicKey);
        $data = 'header.payload';

        $signature = $signer->sign($data);

        // Verify with different data
        $this->assertFalse($signer->verify('header.tampered', $signature));
    }

    // =========================================================================
    // Different Key Pairs
    // =========================================================================

    public function testSignatureFromOneKeyFailsVerificationWithAnother(): void
    {
        // Generate another key pair
        $config = [
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $resource = openssl_pkey_new($config);
        openssl_pkey_export($resource, $otherPrivateKey);
        $details = openssl_pkey_get_details($resource);
        $otherPublicKey = $details['key'];

        $signer1 = new RsaSha256Signer($this->privateKey, $this->publicKey);
        $signer2 = new RsaSha256Signer($otherPrivateKey, $otherPublicKey);

        $data = 'header.payload';
        $signature = $signer1->sign($data);

        // Signature from signer1 should not verify with signer2's public key
        $this->assertFalse($signer2->verify($data, $signature));
    }

    // =========================================================================
    // Public Key Only Verification
    // =========================================================================

    public function testVerifiesWithPublicKeyOnly(): void
    {
        $signingSigner = new RsaSha256Signer($this->privateKey, $this->publicKey);
        $verifyOnlySigner = new RsaSha256Signer(null, $this->publicKey);

        $data = 'header.payload';
        $signature = $signingSigner->sign($data);

        $this->assertTrue($verifyOnlySigner->verify($data, $signature));
    }
}
