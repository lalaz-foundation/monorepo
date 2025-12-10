<?php

declare(strict_types=1);

namespace Lalaz\Web\Http;

/**
 * Cookie management with secure defaults.
 *
 * @package lalaz/web
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
final class CookiePolicy
{
    /**
     * Set a cookie with secure defaults.
     *
     * @param string $name Cookie name
     * @param string $value Cookie value
     * @param int $expires Expiration timestamp
     * @param array<string, mixed> $options Additional options
     * @return bool
     */
    public static function setCookie(
        string $name,
        string $value,
        int $expires = 0,
        array $options = [],
    ): bool {
        $defaults = [
            'path' => '/',
            'domain' => '',
            'secure' => HttpEnvironment::isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];

        $settings = array_merge($defaults, $options);

        return setcookie($name, $value, [
            'expires' => $expires,
            'path' => $settings['path'],
            'domain' => $settings['domain'],
            'secure' => $settings['secure'],
            'httponly' => $settings['httponly'],
            'samesite' => $settings['samesite'],
        ]);
    }

    /**
     * Expire/delete a cookie.
     *
     * @param string $name Cookie name
     * @param array<string, mixed> $options Options to match the original cookie
     * @return bool
     */
    public static function expireCookie(string $name, array $options = []): bool
    {
        $defaults = [
            'path' => '/',
            'domain' => '',
            'secure' => HttpEnvironment::isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];

        $settings = array_merge($defaults, $options);

        return setcookie($name, '', [
            'expires' => time() - 3600,
            'path' => $settings['path'],
            'domain' => $settings['domain'],
            'secure' => $settings['secure'],
            'httponly' => $settings['httponly'],
            'samesite' => $settings['samesite'],
        ]);
    }

    /**
     * Get a cookie value.
     *
     * @param string $name Cookie name
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public static function get(string $name, mixed $default = null): mixed
    {
        return $_COOKIE[$name] ?? $default;
    }

    /**
     * Check if a cookie exists.
     *
     * @param string $name Cookie name
     * @return bool
     */
    public static function has(string $name): bool
    {
        return isset($_COOKIE[$name]);
    }
}
