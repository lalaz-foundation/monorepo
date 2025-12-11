<?php

declare(strict_types=1);

namespace Lalaz\Web\Http;

use Lalaz\Config\Config;
use Lalaz\Web\Security\Fingerprint;

/**
 * Session management with secure defaults.
 *
 * Provides a wrapper around PHP sessions with hardened security:
 * - Secure cookie settings
 * - SameSite protection
 * - Session fingerprinting
 * - Configurable lifetime
 *
 * Supports both static and instance-based usage for flexibility
 * with dependency injection patterns.
 *
 * @package lalaz/web
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
final class SessionManager
{
    /**
     * Default session lifetime in seconds (2 hours).
     */
    private const int DEFAULT_LIFETIME = 7200;

    /**
     * Session key for fingerprint storage.
     */
    private const string FINGERPRINT_KEY = '__fingerprint';

    /**
     * Session key for last activity timestamp.
     */
    private const string LAST_ACTIVITY_KEY = '__last_activity';

    /**
     * Instance configuration.
     *
     * @var array<string, mixed>
     */
    private array $config;

    /**
     * Create a new SessionManager instance.
     *
     * @param array<string, mixed> $config Optional configuration overrides.
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Start the session with secure settings.
     *
     * @return void
     */
    public function start(): void
    {
        static::doStart($this->config);
    }

    /**
     * Set a session value (instance method).
     *
     * @param string $key Session key
     * @param mixed $value Value to store
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        static::doSet($key, $value, $this->config);
    }

    /**
     * Get a session value (instance method).
     *
     * @param string $key Session key
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return static::doGet($key, $default, $this->config);
    }

    /**
     * Check if a session key exists (instance method).
     *
     * @param string $key Session key
     * @return bool
     */
    public function has(string $key): bool
    {
        return static::doHas($key, $this->config);
    }

    /**
     * Remove a session value (instance method).
     *
     * @param string $key Session key
     * @return void
     */
    public function remove(string $key): void
    {
        static::doUnset($key, $this->config);
    }

    /**
     * Destroy the entire session (instance method).
     *
     * @return void
     */
    public function destroy(): void
    {
        static::doDestroy($this->config);
    }

    /**
     * Regenerate the session ID (instance method).
     *
     * @param bool $deleteOldSession Delete the old session data
     * @return void
     */
    public function regenerate(bool $deleteOldSession = true): void
    {
        static::doRegenerate($deleteOldSession, $this->config);
    }

    /**
     * Check if the session is valid (instance method).
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return static::doIsValid($this->config);
    }

    /**
     * Get all session data (instance method).
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return static::doAll($this->config);
    }

    // ============================================
    // Static Methods (for backwards compatibility)
    // ============================================

    /**
     * Start the session with secure settings (static).
     *
     * @return void
     */
    public static function startSession(): void
    {
        static::doStart([]);
    }

    /**
     * Set a session value (static).
     *
     * @param string $key Session key
     * @param mixed $value Value to store
     * @return void
     */
    public static function setValue(string $key, mixed $value): void
    {
        static::doSet($key, $value, []);
    }

    /**
     * Get a session value (static).
     *
     * @param string $key Session key
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        return static::doGet($key, $default, []);
    }

    /**
     * Check if a session key exists (static).
     *
     * @param string $key Session key
     * @return bool
     */
    public static function hasValue(string $key): bool
    {
        return static::doHas($key, []);
    }

    /**
     * Remove a session value (static).
     *
     * @param string $key Session key
     * @return void
     */
    public static function unset(string $key): void
    {
        static::doUnset($key, []);
    }

    /**
     * Destroy the entire session (static).
     *
     * @return void
     */
    public static function destroySession(): void
    {
        static::doDestroy([]);
    }

    /**
     * Regenerate the session ID (static).
     *
     * @param bool $deleteOldSession Delete the old session data
     * @return void
     */
    public static function regenerateSession(bool $deleteOldSession = true): void
    {
        static::doRegenerate($deleteOldSession, []);
    }

    /**
     * Check if the session is valid (static).
     *
     * @return bool
     */
    public static function sessionIsValid(): bool
    {
        return static::doIsValid([]);
    }

    /**
     * Get all session data (static).
     *
     * @return array<string, mixed>
     */
    public static function getAllData(): array
    {
        return static::doAll([]);
    }

    // ============================================
    // Internal Implementation Methods
    // ============================================

    /**
     * Internal start implementation.
     *
     * @param array<string, mixed> $config Configuration
     * @return void
     */
    private static function doStart(array $config): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $cookieSecureSetting = $config['cookie_secure'] ?? Config::get('session.cookie_secure', 'auto');
            $cookieSecure = self::resolveCookieSecure($cookieSecureSetting);
            $sameSite = $config['cookie_samesite'] ?? Config::get('session.cookie_samesite', 'Strict');

            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_secure', $cookieSecure ? '1' : '0');

            if (!is_string($sameSite) || $sameSite === '') {
                $sameSite = 'Strict';
            } else {
                $sameSiteNormalized = ucfirst(strtolower($sameSite));
                if (!in_array($sameSiteNormalized, ['Lax', 'Strict', 'None'], true)) {
                    $sameSiteNormalized = 'Strict';
                }
                $sameSite = $sameSiteNormalized;
            }

            ini_set('session.cookie_samesite', $sameSite);
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');

            $lifetime = $config['lifetime'] ?? Config::getInt('session.lifetime', self::DEFAULT_LIFETIME);
            ini_set('session.gc_maxlifetime', (string) $lifetime);

            session_start();

            $fingerprintEnabled = $config['fingerprint_enabled'] ?? Config::getBool('session.fingerprint.enabled', true);

            if ($fingerprintEnabled) {
                if (!isset($_SESSION[self::FINGERPRINT_KEY])) {
                    static::initializeFingerprint();
                }

                static::validateFingerprint();
            }
        }
    }

    /**
     * Internal set implementation.
     *
     * @param string $key Session key
     * @param mixed $value Value to store
     * @param array<string, mixed> $config Configuration
     * @return void
     */
    private static function doSet(string $key, mixed $value, array $config): void
    {
        self::doStart($config);
        $_SESSION[$key] = $value;
    }

    /**
     * Internal get implementation.
     *
     * @param string $key Session key
     * @param mixed $default Default value
     * @param array<string, mixed> $config Configuration
     * @return mixed
     */
    private static function doGet(string $key, mixed $default, array $config): mixed
    {
        self::doStart($config);
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Internal has implementation.
     *
     * @param string $key Session key
     * @param array<string, mixed> $config Configuration
     * @return bool
     */
    private static function doHas(string $key, array $config): bool
    {
        self::doStart($config);
        return isset($_SESSION[$key]);
    }

    /**
     * Internal unset implementation.
     *
     * @param string $key Session key
     * @param array<string, mixed> $config Configuration
     * @return void
     */
    private static function doUnset(string $key, array $config): void
    {
        self::doStart($config);
        unset($_SESSION[$key]);
    }

    /**
     * Internal destroy implementation.
     *
     * @param array<string, mixed> $config Configuration
     * @return void
     */
    private static function doDestroy(array $config): void
    {
        self::doStart($config);
        session_unset();
        session_destroy();
    }

    /**
     * Internal regenerate implementation.
     *
     * @param bool $deleteOldSession Delete the old session data
     * @param array<string, mixed> $config Configuration
     * @return void
     */
    private static function doRegenerate(bool $deleteOldSession, array $config): void
    {
        self::doStart($config);
        session_regenerate_id($deleteOldSession);
        static::initializeFingerprint();
    }

    /**
     * Internal isValid implementation.
     *
     * @param array<string, mixed> $config Configuration
     * @return bool
     */
    private static function doIsValid(array $config): bool
    {
        self::doStart($config);

        if (!isset($_SESSION[self::FINGERPRINT_KEY])) {
            return false;
        }

        if (!isset($_SESSION[self::LAST_ACTIVITY_KEY])) {
            $_SESSION[self::LAST_ACTIVITY_KEY] = time();
            return true;
        }

        $sessionLifetime = $config['lifetime'] ?? Config::getInt('session.lifetime', self::DEFAULT_LIFETIME);

        if (time() - $_SESSION[self::LAST_ACTIVITY_KEY] > $sessionLifetime) {
            static::doDestroy($config);
            return false;
        }

        $_SESSION[self::LAST_ACTIVITY_KEY] = time();

        return true;
    }

    /**
     * Internal all implementation.
     *
     * @param array<string, mixed> $config Configuration
     * @return array<string, mixed>
     */
    private static function doAll(array $config): array
    {
        self::doStart($config);
        return $_SESSION ?? [];
    }

    /**
     * Resolve cookie secure setting.
     *
     * @param mixed $value Configuration value
     * @return bool
     */
    private static function resolveCookieSecure(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if ($normalized === 'auto' || $normalized === '') {
                return HttpEnvironment::isSecure() || Config::isProduction();
            }

            if (in_array($normalized, ['1', 'true', 'yes'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'no'], true)) {
                return false;
            }
        }

        return HttpEnvironment::isSecure() || Config::isProduction();
    }

    /**
     * Initialize the session fingerprint.
     *
     * @return void
     */
    private static function initializeFingerprint(): void
    {
        $server = $_SERVER ?? [];
        $_SESSION[self::FINGERPRINT_KEY] = Fingerprint::forSession($server);
    }

    /**
     * Validate the session fingerprint.
     *
     * @return void
     */
    private static function validateFingerprint(): void
    {
        if (!isset($_SESSION[self::FINGERPRINT_KEY])) {
            return;
        }

        $server = $_SERVER ?? [];
        $currentFingerprint = Fingerprint::forSession($server);

        if (!hash_equals($_SESSION[self::FINGERPRINT_KEY], $currentFingerprint)) {
            session_unset();
            session_destroy();
            session_start();
        }
    }
}
