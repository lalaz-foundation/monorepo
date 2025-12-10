<?php

declare(strict_types=1);

namespace Lalaz\Auth\Jwt\Signers;

use InvalidArgumentException;
use Lalaz\Auth\Contracts\JwtSignerInterface;
use RuntimeException;

/**
 * RSA SHA-256 Signer
 *
 * Signs JWT tokens using RSA with SHA-256 hash algorithm.
 * Asymmetric signing - uses private key to sign and public key to verify.
 * Recommended for production environments where token verification
 * needs to happen without exposing the signing key.
 *
 * @package Lalaz\Auth\Jwt\Signers
 */
class RsaSha256Signer implements JwtSignerInterface
{
    /**
     * The private key resource for signing.
     *
     * @var \OpenSSLAsymmetricKey|null
     */
    private ?\OpenSSLAsymmetricKey $privateKey = null;

    /**
     * The public key resource for verification.
     *
     * @var \OpenSSLAsymmetricKey|null
     */
    private ?\OpenSSLAsymmetricKey $publicKey = null;

    /**
     * Create a new RSA SHA-256 signer.
     *
     * At least one of private key or public key must be provided.
     * - Private key is required for signing (encode)
     * - Public key is required for verification (decode)
     *
     * Keys can be provided as:
     * - PEM-formatted string
     * - Path to a key file (prefixed with 'file://')
     *
     * @param string|null $privateKey The private key (PEM string or file path).
     * @param string|null $publicKey The public key (PEM string or file path).
     * @param string|null $passphrase Optional passphrase for encrypted private key.
     *
     * @throws InvalidArgumentException If neither key is provided or keys are invalid.
     */
    public function __construct(
        ?string $privateKey = null,
        ?string $publicKey = null,
        ?string $passphrase = null
    ) {
        if ($privateKey === null && $publicKey === null) {
            throw new InvalidArgumentException(
                'At least one of private key or public key must be provided.'
            );
        }

        if ($privateKey !== null) {
            $this->privateKey = $this->loadPrivateKey($privateKey, $passphrase);
        }

        if ($publicKey !== null) {
            $this->publicKey = $this->loadPublicKey($publicKey);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @throws RuntimeException If no private key is configured.
     */
    public function sign(string $data): string
    {
        if ($this->privateKey === null) {
            throw new RuntimeException(
                'Cannot sign: no private key configured. ' .
                'Provide a private key to enable signing.'
            );
        }

        $signature = '';
        $success = openssl_sign($data, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);

        if (!$success) {
            throw new RuntimeException(
                'Failed to sign data: ' . openssl_error_string()
            );
        }

        return $signature;
    }

    /**
     * {@inheritdoc}
     *
     * @throws RuntimeException If no public key is configured.
     */
    public function verify(string $data, string $signature): bool
    {
        // If we have a public key, use it for verification
        if ($this->publicKey !== null) {
            $result = openssl_verify($data, $signature, $this->publicKey, OPENSSL_ALGO_SHA256);
            return $result === 1;
        }

        // Fallback: extract public key from private key if available
        if ($this->privateKey !== null) {
            $keyDetails = openssl_pkey_get_details($this->privateKey);
            if ($keyDetails === false || !isset($keyDetails['key'])) {
                throw new RuntimeException(
                    'Cannot verify: unable to extract public key from private key.'
                );
            }

            $publicKey = openssl_pkey_get_public($keyDetails['key']);
            if ($publicKey === false) {
                throw new RuntimeException(
                    'Cannot verify: failed to load extracted public key.'
                );
            }

            $result = openssl_verify($data, $signature, $publicKey, OPENSSL_ALGO_SHA256);
            return $result === 1;
        }

        throw new RuntimeException(
            'Cannot verify: no public key or private key configured.'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getAlgorithm(): string
    {
        return 'RS256';
    }

    /**
     * Load a private key from string or file.
     *
     * @param string $key The key content or file path.
     * @param string|null $passphrase Optional passphrase.
     * @return \OpenSSLAsymmetricKey
     *
     * @throws InvalidArgumentException If the key is invalid.
     */
    private function loadPrivateKey(string $key, ?string $passphrase): \OpenSSLAsymmetricKey
    {
        $privateKey = openssl_pkey_get_private($key, $passphrase ?? '');

        if ($privateKey === false) {
            throw new InvalidArgumentException(
                'Invalid private key: ' . openssl_error_string()
            );
        }

        return $privateKey;
    }

    /**
     * Load a public key from string or file.
     *
     * @param string $key The key content or file path.
     * @return \OpenSSLAsymmetricKey
     *
     * @throws InvalidArgumentException If the key is invalid.
     */
    private function loadPublicKey(string $key): \OpenSSLAsymmetricKey
    {
        $publicKey = openssl_pkey_get_public($key);

        if ($publicKey === false) {
            throw new InvalidArgumentException(
                'Invalid public key: ' . openssl_error_string()
            );
        }

        return $publicKey;
    }

    /**
     * Generate a new RSA key pair.
     *
     * Utility method to generate keys for testing or initial setup.
     *
     * @param int $bits Key size in bits (default: 2048).
     * @return array{privateKey: string, publicKey: string}
     *
     * @throws RuntimeException If key generation fails.
     */
    public static function generateKeyPair(int $bits = 2048): array
    {
        $config = [
            'private_key_bits' => $bits,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $resource = openssl_pkey_new($config);

        if ($resource === false) {
            throw new RuntimeException(
                'Failed to generate RSA key pair: ' . openssl_error_string()
            );
        }

        $privateKey = '';
        openssl_pkey_export($resource, $privateKey);

        $details = openssl_pkey_get_details($resource);
        if ($details === false) {
            throw new RuntimeException(
                'Failed to extract public key: ' . openssl_error_string()
            );
        }

        return [
            'privateKey' => $privateKey,
            'publicKey' => $details['key'],
        ];
    }
}
