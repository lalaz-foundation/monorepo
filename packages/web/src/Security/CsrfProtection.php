<?php

declare(strict_types=1);

namespace Lalaz\Web\Security;

use Lalaz\Web\Http\CookiePolicy;

/**
 * Stateless CSRF protection.
 *
 * Manages CSRF token generation, storage in secure cookies,
 * and validation against request payload or headers.
 *
 * @package lalaz/web
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
final class CsrfProtection
{
    /**
     * Cookie name for CSRF token storage.
     */
    private const COOKIE_NAME = '__csrf_token';

    /**
     * Form field name for CSRF token.
     */
    private const TOKEN_FIELD = 'csrfToken';

    /**
     * Header name for CSRF token (AJAX requests).
     */
    private const HEADER_NAME = 'X-CSRF-Token';

    /**
     * Cookie lifetime in seconds (24 hours).
     */
    private const COOKIE_LIFETIME = 86400;

    /**
     * Generate a new CSRF token and store it in a cookie.
     *
     * @return string The generated token
     */
    public static function generateToken(): string
    {
        $token = bin2hex(random_bytes(32));
        static::setCookie($token);
        return $token;
    }

    /**
     * Get the current CSRF token or generate a new one.
     *
     * @return string The CSRF token
     */
    public static function getToken(): string
    {
        if (isset($_COOKIE[self::COOKIE_NAME])) {
            return $_COOKIE[self::COOKIE_NAME];
        }

        return static::generateToken();
    }

    /**
     * Validate a CSRF token from request.
     *
     * Checks for the token in:
     * 1. Request body (form field)
     * 2. Request headers (for AJAX)
     *
     * @param array<string, mixed>|object $body Request body
     * @param array<string, string> $headers Request headers
     * @return bool
     */
    public static function validateToken(array|object $body, array $headers = []): bool
    {
        $cookieToken = $_COOKIE[self::COOKIE_NAME] ?? null;

        if (!$cookieToken) {
            return false;
        }

        $requestToken = null;

        // Check body for token
        if (is_array($body) && isset($body[self::TOKEN_FIELD])) {
            $requestToken = $body[self::TOKEN_FIELD];
        } elseif (is_object($body) && isset($body->{self::TOKEN_FIELD})) {
            $requestToken = $body->{self::TOKEN_FIELD};
        }

        // Check headers if not found in body
        if (!$requestToken) {
            foreach ($headers as $key => $value) {
                if (strcasecmp($key, self::HEADER_NAME) === 0) {
                    $requestToken = $value;
                    break;
                }
            }
        }

        if (!$requestToken) {
            return false;
        }

        return hash_equals($cookieToken, $requestToken);
    }

    /**
     * Rotate the CSRF token (generate a new one).
     *
     * @return string The new token
     */
    public static function rotateToken(): string
    {
        return static::generateToken();
    }

    /**
     * Delete the CSRF token cookie.
     *
     * @return void
     */
    public static function deleteToken(): void
    {
        if (isset($_COOKIE[self::COOKIE_NAME])) {
            CookiePolicy::expireCookie(self::COOKIE_NAME, [
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
            unset($_COOKIE[self::COOKIE_NAME]);
        }
    }

    /**
     * Get the form field name for the CSRF token.
     *
     * @return string
     */
    public static function getTokenFieldName(): string
    {
        return self::TOKEN_FIELD;
    }

    /**
     * Get the header name for the CSRF token.
     *
     * @return string
     */
    public static function getTokenHeaderName(): string
    {
        return self::HEADER_NAME;
    }

    /**
     * Set the CSRF token cookie.
     *
     * @param string $token The token value
     * @return void
     */
    private static function setCookie(string $token): void
    {
        CookiePolicy::setCookie(
            self::COOKIE_NAME,
            $token,
            time() + self::COOKIE_LIFETIME,
            [
                'httponly' => true,
                'samesite' => 'Strict',
            ],
        );

        $_COOKIE[self::COOKIE_NAME] = $token;
    }
}
