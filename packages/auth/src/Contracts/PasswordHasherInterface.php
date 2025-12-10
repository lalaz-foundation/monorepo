<?php

declare(strict_types=1);

namespace Lalaz\Auth\Contracts;

/**
 * Password Hasher Interface
 *
 * Contract for password hashing implementations.
 * Allows the auth package to work with any hasher implementation.
 *
 * @package Lalaz\Auth\Contracts
 */
interface PasswordHasherInterface
{
    /**
     * Hash a plain text password.
     *
     * @param string $plainText The password to hash.
     * @return string The hashed password.
     */
    public function hash(string $plainText): string;

    /**
     * Verify a plain text password against a hash.
     *
     * @param string $plainText The password to verify.
     * @param string $hash The hash to verify against.
     * @return bool True if the password matches.
     */
    public function verify(string $plainText, string $hash): bool;

    /**
     * Check if a hash needs to be rehashed.
     *
     * @param string $hash The hash to check.
     * @return bool True if rehashing is recommended.
     */
    public function needsRehash(string $hash): bool;
}
