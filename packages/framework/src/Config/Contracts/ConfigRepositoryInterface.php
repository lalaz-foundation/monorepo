<?php

declare(strict_types=1);

namespace Lalaz\Config\Contracts;

/**
 * Contract for configuration repositories.
 *
 * Implementations manage application configuration from environment files
 * and PHP config files, supporting dot-notation for nested values.
 *
 * @package lalaz/framework
 */
interface ConfigRepositoryInterface
{
    /**
     * Loads configuration from an environment file (e.g., .env).
     *
     * @param string $envFile Path to the environment file.
     * @param string $delimiter The delimiter used to separate key and value.
     * @param bool $forceReload If true, bypasses cache and re-parses the file.
     */
    public function load(
        string $envFile,
        string $delimiter = '=',
        bool $forceReload = false,
    ): void;

    /**
     * Retrieves a configuration or environment value.
     *
     * @param string $key Dot-notation supported.
     * @param mixed $default Fallback value when key is not found.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Overrides an environment variable for the current process.
     *
     * @return mixed The value that was set.
     */
    public function set(string $key, mixed $value): mixed;

    /**
     * Injects a configuration array under the given namespace.
     *
     * @param array<string, mixed> $data
     */
    public function setConfig(string $namespace, array $data): void;

    /**
     * Loads all .php config files in a directory.
     */
    public function loadConfigFiles(string $configDir): void;

    /**
     * Retrieves a configuration value with runtime type coercion.
     *
     * @param string $key Dot-notation config key.
     * @param mixed $default Fallback when the value is missing.
     * @param string $type One of "string", "int", "bool", "float".
     */
    public function getTyped(
        string $key,
        mixed $default = null,
        string $type = 'string',
    ): mixed;

    /**
     * Retrieves a configuration value as an integer.
     */
    public function getInt(string $key, ?int $default = null): ?int;

    /**
     * Retrieves a configuration value as a boolean.
     */
    public function getBool(string $key, ?bool $default = null): ?bool;

    /**
     * Retrieves a configuration value as a float.
     */
    public function getFloat(string $key, ?float $default = null): ?float;

    /**
     * Retrieves a configuration value as a string.
     */
    public function getString(string $key, ?string $default = null): ?string;

    /**
     * Retrieves a configuration value as an array.
     *
     * @param array<mixed>|null $default
     * @return array<mixed>|null
     */
    public function getArray(string $key, ?array $default = null): ?array;

    /**
     * Validates a configuration value using a custom callback.
     *
     * @param callable(mixed):bool $validator
     * @return bool True when the validator returns truthy.
     */
    public function validate(string $key, callable $validator): bool;

    /**
     * Flushes all cached env/config state.
     */
    public function clearCache(): void;

    /**
     * Returns every tracked config + env value.
     *
     * @return array<string, mixed>
     */
    public function all(): array;

    /**
     * Returns the cached environment variables.
     *
     * @return array<string, mixed>
     */
    public function allEnv(): array;

    /**
     * Returns the loaded configuration arrays.
     *
     * @return array<string, mixed>
     */
    public function allConfig(): array;

    /**
     * Checks if the current environment matches the given name.
     */
    public function isEnv(string $env): bool;

    /**
     * Determines if debug mode is enabled.
     */
    public function isDebug(): bool;

    /**
     * Returns true when `app.env` equals `development`.
     */
    public function isDevelopment(): bool;

    /**
     * Returns true when the current environment is `production`.
     */
    public function isProduction(): bool;

    /**
     * Sets the path to the compiled configuration cache file.
     */
    public function setCacheFile(string $path): void;

    /**
     * Indicates whether config caching is enabled via env/config.
     */
    public function isCacheEnabled(): bool;

    /**
     * Attempts to hydrate configuration state from the cache file.
     *
     * @return bool True when cache was loaded successfully.
     */
    public function loadFromCache(string $envFile): bool;

    /**
     * Persists env/config state into the configured cache file.
     *
     * @param string $envFile Path to the original .env file (used for hashing).
     * @return bool True on success, false when caching is disabled.
     */
    public function saveToCache(string $envFile): bool;

    /**
     * Clears the configuration cache file.
     */
    public function clearConfigCache(): bool;
}
