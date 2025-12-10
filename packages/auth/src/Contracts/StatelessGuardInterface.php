<?php

declare(strict_types=1);

namespace Lalaz\Auth\Contracts;

/**
 * Stateless Guard Interface
 *
 * Extension for guards that don't maintain state between requests
 * (JWT, API Key). These guards return tokens instead of using sessions.
 *
 * @package Lalaz\Auth\Contracts
 */
interface StatelessGuardInterface extends GuardInterface
{
    /**
     * Create a token for the given user.
     *
     * @param mixed $user The user to create token for.
     * @param array<string, mixed> $claims Additional claims/data for the token.
     * @return string The generated token.
     */
    public function createToken(mixed $user, array $claims = []): string;

    /**
     * Parse and validate a token, returning the user if valid.
     *
     * @param string $token The token to validate.
     * @return mixed The user or null if invalid.
     */
    public function authenticateToken(string $token): mixed;

    /**
     * Revoke a token (invalidate it).
     *
     * @param string $token The token to revoke.
     * @return bool True if revoked successfully.
     */
    public function revokeToken(string $token): bool;

    /**
     * Refresh a token, returning a new one.
     *
     * @param string $token The token to refresh.
     * @return string|null The new token or null if refresh failed.
     */
    public function refreshToken(string $token): ?string;

    /**
     * Get the token from the current request.
     *
     * @return string|null The token or null if not present.
     */
    public function getTokenFromRequest(): ?string;
}
