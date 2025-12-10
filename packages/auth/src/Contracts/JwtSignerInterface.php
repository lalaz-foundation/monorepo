<?php

declare(strict_types=1);

namespace Lalaz\Auth\Contracts;

/**
 * JWT Signer Interface
 *
 * Contract for JWT signing algorithms. Implementations provide
 * specific cryptographic algorithms for signing and verifying JWT tokens.
 *
 * @package Lalaz\Auth\Contracts
 */
interface JwtSignerInterface
{
    /**
     * Sign the given data and return the raw signature.
     *
     * @param string $data The data to sign (header.payload).
     * @return string The raw signature bytes.
     */
    public function sign(string $data): string;

    /**
     * Verify that the signature is valid for the given data.
     *
     * @param string $data The data that was signed.
     * @param string $signature The raw signature bytes to verify.
     * @return bool True if the signature is valid.
     */
    public function verify(string $data, string $signature): bool;

    /**
     * Get the algorithm name for the JWT header.
     *
     * @return string The algorithm name (e.g., 'HS256', 'RS256').
     */
    public function getAlgorithm(): string;
}
