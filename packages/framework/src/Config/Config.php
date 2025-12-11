<?php

declare(strict_types=1);

namespace Lalaz\Config;

use Lalaz\Config\Contracts\ConfigRepositoryInterface;

/**
 * Static facade for the configuration repository.
 *
 * This class provides backward-compatible static access to configuration values.
 * It delegates all operations to an underlying ConfigRepository instance,
 * allowing for dependency injection in new code while maintaining compatibility
 * with existing static calls.
 *
 * For new code, prefer injecting ConfigRepositoryInterface directly.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
class Config
{
    /**
     * The underlying repository instance.
     */
    private static ?ConfigRepository $instance = null;

    /**
     * Returns the singleton ConfigRepository instance.
     */
    public static function getInstance(): ConfigRepository
    {
        if (self::$instance === null) {
            self::$instance = new ConfigRepository();
        }

        return self::$instance;
    }

    /**
     * Replaces the singleton instance (useful for testing).
     */
    public static function setInstance(?ConfigRepositoryInterface $repository): void
    {
        self::$instance = $repository;
    }

    /**
     * Loads configuration from an environment file (e.g., .env).
     *
     * @param string $envFile Path to the environment file.
     * @param string $delimiter The delimiter used to separate key and value.
     * @param bool $forceReload If true, bypasses cache and re-parses the file.
     */
    public static function load(
        string $envFile,
        string $delimiter = '=',
        bool $forceReload = false,
    ): void {
        self::getInstance()->load($envFile, $delimiter, $forceReload);
    }

    /**
     * Retrieves a configuration or environment value.
     *
     * @param string $key Dot-notation supported.
     * @param mixed $default
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return self::getInstance()->get($key, $default);
    }

    /**
     * Overrides an environment variable for the current process.
     *
     * @return mixed The value that was set.
     */
    public static function set(string $key, mixed $value): mixed
    {
        return self::getInstance()->set($key, $value);
    }

    /**
     * Injects a configuration array under the given namespace.
     *
     * @param array<string, mixed> $data
     */
    public static function setConfig(string $namespace, array $data): void
    {
        self::getInstance()->setConfig($namespace, $data);
    }

    /**
     * Loads all .php config files in a directory.
     */
    public static function loadConfigFiles(string $configDir): void
    {
        self::getInstance()->loadConfigFiles($configDir);
    }

    /**
     * Retrieves a configuration value with runtime type coercion.
     *
     * @param string $key Dot-notation config key.
     * @param mixed $default Fallback when the value is missing.
     * @param string $type One of "string", "int", "bool", "float".
     */
    public static function getTyped(
        string $key,
        mixed $default = null,
        string $type = 'string',
    ): mixed {
        return self::getInstance()->getTyped($key, $default, $type);
    }

    /**
     * Retrieves a configuration value as an integer.
     */
    public static function getInt(string $key, ?int $default = null): ?int
    {
        return self::getInstance()->getInt($key, $default);
    }

    /**
     * Retrieves a configuration value as a boolean.
     */
    public static function getBool(string $key, ?bool $default = null): ?bool
    {
        return self::getInstance()->getBool($key, $default);
    }

    /**
     * Retrieves a configuration value as a float.
     */
    public static function getFloat(string $key, ?float $default = null): ?float
    {
        return self::getInstance()->getFloat($key, $default);
    }

    /**
     * Retrieves a configuration value as a string.
     */
    public static function getString(
        string $key,
        ?string $default = null,
    ): ?string {
        return self::getInstance()->getString($key, $default);
    }

    /**
     * Retrieves a configuration value as an array.
     *
     * @param array<mixed>|null $default
     * @return array<mixed>|null
     */
    public static function getArray(string $key, ?array $default = null): ?array
    {
        return self::getInstance()->getArray($key, $default);
    }

    /**
     * Validates a configuration value using a custom callback.
     *
     * @param callable(mixed):bool $validator
     * @return bool True when the validator returns truthy.
     */
    public static function validate(string $key, callable $validator): bool
    {
        return self::getInstance()->validate($key, $validator);
    }

    /**
     * Flushes all cached env/config state.
     */
    public static function clearCache(): void
    {
        self::getInstance()->clearCache();
    }

    /**
     * Returns every tracked config + env value.
     *
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return self::getInstance()->all();
    }

    /**
     * Returns the cached environment variables.
     *
     * @return array<string, mixed>
     */
    public static function allEnv(): array
    {
        return self::getInstance()->allEnv();
    }

    /**
     * Returns the loaded configuration arrays.
     *
     * @return array<string, mixed>
     */
    public static function allConfig(): array
    {
        return self::getInstance()->allConfig();
    }

    /**
     * Checks if the current environment matches the given name.
     */
    public static function isEnv(string $env): bool
    {
        return self::getInstance()->isEnv($env);
    }

    /**
     * Determines if debug mode is enabled.
     */
    public static function isDebug(): bool
    {
        return self::getInstance()->isDebug();
    }

    /**
     * Returns true when `app.env` equals `development`.
     */
    public static function isDevelopment(): bool
    {
        return self::getInstance()->isDevelopment();
    }

    /**
     * Returns true when the current environment is `production`.
     */
    public static function isProduction(): bool
    {
        return self::getInstance()->isProduction();
    }

    /**
     * Sets the path to the compiled configuration cache file.
     */
    public static function setCacheFile(string $path): void
    {
        self::getInstance()->setCacheFile($path);
    }

    /**
     * Indicates whether config caching is enabled via env/config.
     */
    public static function isCacheEnabled(): bool
    {
        return self::getInstance()->isCacheEnabled();
    }

    /**
     * Attempts to hydrate configuration state from the cache file.
     *
     * @return bool True when cache was loaded successfully.
     */
    public static function loadFromCache(string $envFile): bool
    {
        return self::getInstance()->loadFromCache($envFile);
    }

    /**
     * Persists env/config state into the configured cache file.
     *
     * @param string $envFile Path to the original .env file (used for hashing).
     * @return bool True on success, false when caching is disabled.
     */
    public static function saveToCache(string $envFile): bool
    {
        return self::getInstance()->saveToCache($envFile);
    }

    /**
     * Clears the configuration cache file.
     */
    public static function clearConfigCache(): bool
    {
        return self::getInstance()->clearConfigCache();
    }
}
