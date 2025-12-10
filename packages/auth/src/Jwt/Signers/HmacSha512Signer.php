<?php

declare(strict_types=1);

namespace Lalaz\Auth\Jwt\Signers;

use Lalaz\Auth\Contracts\JwtSignerInterface;

/**
 * HMAC SHA-512 Signer
 *
 * Signs JWT tokens using HMAC with SHA-512 hash algorithm.
 * Provides the strongest HMAC-based security with the largest signatures.
 *
 * @package Lalaz\Auth\Jwt\Signers
 */
class HmacSha512Signer implements JwtSignerInterface
{
    /**
     * Create a new HMAC SHA-512 signer.
     *
     * @param string $secret The secret key for signing.
     */
    public function __construct(
        private readonly string $secret
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function sign(string $data): string
    {
        return hash_hmac('sha512', $data, $this->secret, true);
    }

    /**
     * {@inheritdoc}
     */
    public function verify(string $data, string $signature): bool
    {
        return hash_equals($this->sign($data), $signature);
    }

    /**
     * {@inheritdoc}
     */
    public function getAlgorithm(): string
    {
        return 'HS512';
    }
}
