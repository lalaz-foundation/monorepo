<?php

declare(strict_types=1);

namespace Lalaz\Web\Security;

/**
 * Session fingerprinting for hijacking prevention.
 *
 * Generates a hash based on client characteristics to detect
 * potential session hijacking attempts.
 *
 * @package lalaz/web
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
final class Fingerprint
{
    /**
     * Generate a session fingerprint from server variables.
     *
     * @param array<string, mixed> $server Server variables ($_SERVER)
     * @return string The fingerprint hash
     */
    public static function forSession(array $server): string
    {
        $components = [
            $server['HTTP_USER_AGENT'] ?? '',
            $server['HTTP_ACCEPT_LANGUAGE'] ?? '',
            $server['HTTP_ACCEPT_ENCODING'] ?? '',
        ];

        return hash('sha256', implode('|', $components));
    }

    /**
     * Generate a device fingerprint (more comprehensive).
     *
     * @param array<string, mixed> $server Server variables ($_SERVER)
     * @return string The fingerprint hash
     */
    public static function forDevice(array $server): string
    {
        $components = [
            $server['HTTP_USER_AGENT'] ?? '',
            $server['HTTP_ACCEPT_LANGUAGE'] ?? '',
            $server['HTTP_ACCEPT_ENCODING'] ?? '',
            $server['HTTP_ACCEPT'] ?? '',
            $server['HTTP_CONNECTION'] ?? '',
        ];

        return hash('sha256', implode('|', $components));
    }

    /**
     * Validate a fingerprint against current request.
     *
     * @param string $fingerprint The stored fingerprint
     * @param array<string, mixed> $server Server variables ($_SERVER)
     * @return bool
     */
    public static function validate(string $fingerprint, array $server): bool
    {
        $current = self::forSession($server);
        return hash_equals($fingerprint, $current);
    }
}
