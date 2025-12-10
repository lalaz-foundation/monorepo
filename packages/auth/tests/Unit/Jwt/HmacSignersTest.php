<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Unit\Jwt;

use PHPUnit\Framework\Attributes\CoversClass;

use Lalaz\Auth\Jwt\Signers\HmacSha256Signer;
use Lalaz\Auth\Jwt\Signers\HmacSha384Signer;
use Lalaz\Auth\Jwt\Signers\HmacSha512Signer;
use Lalaz\Auth\Tests\Common\AuthUnitTestCase;

/**
 * @covers \Lalaz\Auth\Jwt\Signers\HmacSha256Signer
 * @covers \Lalaz\Auth\Jwt\Signers\HmacSha384Signer
 * @covers \Lalaz\Auth\Jwt\Signers\HmacSha512Signer
 */
#[CoversClass(\Lalaz\Auth\Jwt\Signers\HmacSha256Signer::class)]
final class HmacSignersTest extends AuthUnitTestCase
{
    private string $secret = 'secret-key-32-chars-minimum!!!!!';

    // =========================================================================
    // HMAC SHA256 Signer
    // =========================================================================

    public function testHmacSha256SignerReturnsCorrectAlgorithm(): void
    {
        $signer = new HmacSha256Signer($this->secret);

        $this->assertSame('HS256', $signer->getAlgorithm());
    }

    public function testHmacSha256SignerSignsData(): void
    {
        $signer = new HmacSha256Signer($this->secret);
        $data = 'header.payload';

        $signature = $signer->sign($data);

        $this->assertNotEmpty($signature);
        $this->assertIsString($signature);
    }

    public function testHmacSha256SignerVerifiesValidSignature(): void
    {
        $signer = new HmacSha256Signer($this->secret);
        $data = 'header.payload';

        $signature = $signer->sign($data);

        $this->assertTrue($signer->verify($data, $signature));
    }

    public function testHmacSha256SignerRejectsInvalidSignature(): void
    {
        $signer = new HmacSha256Signer($this->secret);
        $data = 'header.payload';

        $this->assertFalse($signer->verify($data, 'invalid-signature'));
    }

    public function testHmacSha256SignerProducesConsistentSignatures(): void
    {
        $signer = new HmacSha256Signer($this->secret);
        $data = 'header.payload';

        $signature1 = $signer->sign($data);
        $signature2 = $signer->sign($data);

        $this->assertSame($signature1, $signature2);
    }

    // =========================================================================
    // HMAC SHA384 Signer
    // =========================================================================

    public function testHmacSha384SignerReturnsCorrectAlgorithm(): void
    {
        $signer = new HmacSha384Signer($this->secret);

        $this->assertSame('HS384', $signer->getAlgorithm());
    }

    public function testHmacSha384SignerSignsData(): void
    {
        $signer = new HmacSha384Signer($this->secret);
        $data = 'header.payload';

        $signature = $signer->sign($data);

        $this->assertNotEmpty($signature);
        $this->assertIsString($signature);
    }

    public function testHmacSha384SignerVerifiesValidSignature(): void
    {
        $signer = new HmacSha384Signer($this->secret);
        $data = 'header.payload';

        $signature = $signer->sign($data);

        $this->assertTrue($signer->verify($data, $signature));
    }

    public function testHmacSha384SignerRejectsInvalidSignature(): void
    {
        $signer = new HmacSha384Signer($this->secret);
        $data = 'header.payload';

        $this->assertFalse($signer->verify($data, 'invalid-signature'));
    }

    // =========================================================================
    // HMAC SHA512 Signer
    // =========================================================================

    public function testHmacSha512SignerReturnsCorrectAlgorithm(): void
    {
        $signer = new HmacSha512Signer($this->secret);

        $this->assertSame('HS512', $signer->getAlgorithm());
    }

    public function testHmacSha512SignerSignsData(): void
    {
        $signer = new HmacSha512Signer($this->secret);
        $data = 'header.payload';

        $signature = $signer->sign($data);

        $this->assertNotEmpty($signature);
        $this->assertIsString($signature);
    }

    public function testHmacSha512SignerVerifiesValidSignature(): void
    {
        $signer = new HmacSha512Signer($this->secret);
        $data = 'header.payload';

        $signature = $signer->sign($data);

        $this->assertTrue($signer->verify($data, $signature));
    }

    public function testHmacSha512SignerRejectsInvalidSignature(): void
    {
        $signer = new HmacSha512Signer($this->secret);
        $data = 'header.payload';

        $this->assertFalse($signer->verify($data, 'invalid-signature'));
    }

    // =========================================================================
    // Cross-Algorithm Tests
    // =========================================================================

    public function testDifferentAlgorithmsProduceDifferentSignatures(): void
    {
        $data = 'header.payload';

        $signer256 = new HmacSha256Signer($this->secret);
        $signer384 = new HmacSha384Signer($this->secret);
        $signer512 = new HmacSha512Signer($this->secret);

        $sig256 = $signer256->sign($data);
        $sig384 = $signer384->sign($data);
        $sig512 = $signer512->sign($data);

        $this->assertNotSame($sig256, $sig384);
        $this->assertNotSame($sig256, $sig512);
        $this->assertNotSame($sig384, $sig512);
    }

    public function testSignatureFromOneAlgorithmFailsVerificationOnAnother(): void
    {
        $data = 'header.payload';

        $signer256 = new HmacSha256Signer($this->secret);
        $signer512 = new HmacSha512Signer($this->secret);

        $signature256 = $signer256->sign($data);

        $this->assertFalse($signer512->verify($data, $signature256));
    }
}
