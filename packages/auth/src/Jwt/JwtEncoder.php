<?php

declare(strict_types=1);

namespace Lalaz\Auth\Jwt;

use Lalaz\Auth\Contracts\JwtSignerInterface;
use Lalaz\Auth\Jwt\Signers\HmacSha256Signer;

/**
 * JWT Encoder
 *
 * Encodes and decodes JWT tokens using configurable signing algorithms.
 * Supports HMAC (HS256, HS384, HS512) and RSA (RS256) algorithms.
 *
 * @package Lalaz\Auth\Jwt
 */
class JwtEncoder
{
    /**
     * Default token expiration (1 hour).
     */
    private const int DEFAULT_EXPIRATION = 3600;

    /**
     * Default refresh token expiration (7 days).
     */
    private const int DEFAULT_REFRESH_EXPIRATION = 604800;

    /**
     * The signer instance for signing/verifying tokens.
     *
     * @var JwtSignerInterface
     */
    private JwtSignerInterface $signer;

    /**
     * Token expiration time in seconds.
     *
     * @var int
     */
    private int $expiration;

    /**
     * Refresh token expiration time in seconds.
     *
     * @var int
     */
    private int $refreshExpiration;

    /**
     * The issuer claim.
     *
     * @var string
     */
    private string $issuer;

    /**
     * Create a new JwtEncoder instance.
     *
     * Supports two construction modes for backward compatibility:
     *
     * 1. New mode (recommended): Pass a JwtSignerInterface instance
     *    ```php
     *    $signer = new HmacSha256Signer($secret);
     *    $encoder = new JwtEncoder($signer);
     *    ```
     *
     * 2. Legacy mode: Pass a secret string (creates HmacSha256Signer internally)
     *    ```php
     *    $encoder = new JwtEncoder($secret);
     *    ```
     *
     * @param JwtSignerInterface|string $signerOrSecret The signer instance or secret key.
     * @param int $expiration Token expiration in seconds.
     * @param int $refreshExpiration Refresh token expiration in seconds.
     * @param string $issuer The issuer claim.
     */
    public function __construct(
        JwtSignerInterface|string $signerOrSecret,
        int $expiration = self::DEFAULT_EXPIRATION,
        int $refreshExpiration = self::DEFAULT_REFRESH_EXPIRATION,
        string $issuer = 'lalaz',
    ) {
        // Backward compatibility: if string is passed, create HmacSha256Signer
        if (is_string($signerOrSecret)) {
            $this->signer = new HmacSha256Signer($signerOrSecret);
        } else {
            $this->signer = $signerOrSecret;
        }

        $this->expiration = $expiration;
        $this->refreshExpiration = $refreshExpiration;
        $this->issuer = $issuer;
    }

    /**
     * Get the current signer instance.
     *
     * @return JwtSignerInterface
     */
    public function getSigner(): JwtSignerInterface
    {
        return $this->signer;
    }

    /**
     * Get the algorithm name from the current signer.
     *
     * @return string
     */
    public function getAlgorithm(): string
    {
        return $this->signer->getAlgorithm();
    }

    /**
     * Encode a payload into a JWT token.
     *
     * @param array<string, mixed> $payload The claims to encode.
     * @param int|null $expiration Custom expiration time in seconds.
     * @return string The JWT token.
     */
    public function encode(array $payload, ?int $expiration = null): string
    {
        $header = [
            'typ' => 'JWT',
            'alg' => $this->signer->getAlgorithm(),
        ];

        $now = time();

        // Standard claims that get merged (but won't override existing)
        $standardClaims = [
            'iss' => $this->issuer,
            'iat' => $now,
            'jti' => bin2hex(random_bytes(16)),
        ];

        // Only add exp if not already in payload
        if (!isset($payload['exp'])) {
            $exp = $expiration ?? $this->expiration;
            $standardClaims['exp'] = $now + $exp;
        }

        // Merge: payload values take precedence over standard claims
        $payload = array_merge($standardClaims, $payload);

        $headerEncoded = $this->base64UrlEncode(json_encode($header));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));

        $signature = $this->sign($headerEncoded . '.' . $payloadEncoded);

        return $headerEncoded . '.' . $payloadEncoded . '.' . $signature;
    }

    /**
     * Create an access token for a user.
     *
     * @param mixed $userId The user ID.
     * @param array<string, mixed> $claims Additional claims.
     * @return string The JWT token.
     */
    public function createAccessToken(mixed $userId, array $claims = []): string
    {
        return $this->encode(array_merge([
            'sub' => (string) $userId,
            'type' => 'access',
        ], $claims), $this->expiration);
    }

    /**
     * Create a refresh token for a user.
     *
     * @param mixed $userId The user ID.
     * @return string The refresh token.
     */
    public function createRefreshToken(mixed $userId): string
    {
        return $this->encode([
            'sub' => (string) $userId,
            'type' => 'refresh',
        ], $this->refreshExpiration);
    }

    /**
     * Decode and validate a JWT token.
     *
     * @param string $token The JWT token.
     * @return array<string, mixed>|null The decoded payload or null if invalid.
     */
    public function decode(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

        // Decode signature
        $signature = $this->base64UrlDecode($signatureEncoded);

        // Verify signature using signer
        if (!$this->signer->verify($headerEncoded . '.' . $payloadEncoded, $signature)) {
            return null;
        }

        // Decode payload
        $payload = json_decode($this->base64UrlDecode($payloadEncoded), true);

        if (!is_array($payload)) {
            return null;
        }

        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }

        // Check not before
        if (isset($payload['nbf']) && $payload['nbf'] > time()) {
            return null;
        }

        // Check issuer
        if (isset($payload['iss']) && $payload['iss'] !== $this->issuer) {
            return null;
        }

        return $payload;
    }

    /**
     * Validate a token without returning the payload.
     *
     * @param string $token The token to validate.
     * @return bool
     */
    public function validate(string $token): bool
    {
        return $this->decode($token) !== null;
    }

    /**
     * Get the user ID (subject) from a token.
     *
     * @param string $token The token.
     * @return string|null The user ID or null.
     */
    public function getSubject(string $token): ?string
    {
        $payload = $this->decode($token);
        return $payload['sub'] ?? null;
    }

    /**
     * Get the token type from a token.
     *
     * @param string $token The token.
     * @return string|null The token type or null.
     */
    public function getTokenType(string $token): ?string
    {
        $payload = $this->decode($token);
        return $payload['type'] ?? null;
    }

    /**
     * Get the JTI (unique identifier) from a token.
     *
     * @param string $token The token.
     * @return string|null The JTI or null.
     */
    public function getJti(string $token): ?string
    {
        $payload = $this->decode($token);
        return $payload['jti'] ?? null;
    }

    /**
     * Get the expiration timestamp from a token.
     *
     * @param string $token The token.
     * @return int|null The expiration timestamp or null.
     */
    public function getExpiration(string $token): ?int
    {
        $payload = $this->decode($token);
        return $payload['exp'] ?? null;
    }

    /**
     * Check if a token is a refresh token.
     *
     * @param string $token The token.
     * @return bool
     */
    public function isRefreshToken(string $token): bool
    {
        return $this->getTokenType($token) === 'refresh';
    }

    /**
     * Verify a token's signature without validating claims.
     *
     * @param string $token The token to verify.
     * @return bool
     */
    public function verify(string $token): bool
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return false;
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

        $signature = $this->base64UrlDecode($signatureEncoded);

        return $this->signer->verify($headerEncoded . '.' . $payloadEncoded, $signature);
    }

    /**
     * Get claims from a token without validation.
     *
     * Useful for debugging or extracting data from expired tokens.
     *
     * @param string $token The token.
     * @return array<string, mixed>|null The claims or null if invalid format.
     */
    public function getClaims(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($parts[1]), true);

        if (!is_array($payload)) {
            return null;
        }

        return $payload;
    }

    /**
     * Sign data with the signer and return base64url-encoded signature.
     *
     * @param string $data The data to sign.
     * @return string The base64url-encoded signature.
     */
    private function sign(string $data): string
    {
        return $this->base64UrlEncode($this->signer->sign($data));
    }

    /**
     * Base64 URL encode.
     *
     * @param string $data The data to encode.
     * @return string
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL decode.
     *
     * @param string $data The data to decode.
     * @return string
     */
    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
