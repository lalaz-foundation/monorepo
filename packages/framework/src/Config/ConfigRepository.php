<?php

declare(strict_types=1);

namespace Lalaz\Config;

use ArrayAccess;
use Lalaz\Config\Contracts\ConfigRepositoryInterface;
use Lalaz\Exceptions\FrameworkException;
use Lalaz\Support\Cache\PhpArrayCache;
use Lalaz\Support\Errors;
use Throwable;

/**
 * Injectable configuration repository.
 *
 * This class manages application configuration from environment files and PHP config files.
 * It supports dot-notation for nested values, provides typed getters, and can cache
 * the merged configuration into a single PHP file.
 *
 * Unlike the static Config facade, this class can be injected via dependency injection,
 * making it easier to test and allowing multiple configuration instances.
 *
 * @package lalaz/framework
 * @implements ArrayAccess<string, mixed>
 */
class ConfigRepository implements ConfigRepositoryInterface, ArrayAccess
{
    /**
     * Default sentinel value used when a configuration key is not found.
     */
    private const DEFAULT_SENTINEL = '__LALAZ_CONFIG_DEFAULT__';

    /**
     * @var array<string, mixed>|null Stores parsed environment variables.
     */
    private ?array $env = null;

    /**
     * @var array<string, mixed> Stores configuration loaded from PHP files.
     */
    private array $config = [];

    /**
     * @var int|null The last modified timestamp of the .env file.
     */
    private ?int $lastModifiedTime = null;

    /**
     * @var string|null The full path to the configuration cache file.
     */
    private ?string $cacheFile = null;

    /**
     * Loads configuration from an environment file (e.g., .env).
     *
     * @param string $envFile Path to the environment file.
     * @param string $delimiter The delimiter used to separate key and value.
     * @param bool $forceReload If true, bypasses cache and re-parses the file.
     * @throws FrameworkException When the env file cannot be read or is invalid.
     */
    public function load(
        string $envFile,
        string $delimiter = '=',
        bool $forceReload = false,
    ): void {
        if (
            !$forceReload &&
            $this->isCacheEnabled() &&
            $this->loadFromCache($envFile)
        ) {
            return;
        }

        if (!file_exists($envFile)) {
            $this->env = $this->mergeSuperglobals();
            return;
        }

        if (!$forceReload && !$this->shouldReload($envFile)) {
            return;
        }

        if (!is_file($envFile)) {
            Errors::throwConfigurationError('Env path is not a regular file', [
                'env_file' => $envFile,
            ]);
        }

        $parsed = $this->parseEnvFile($envFile, $delimiter);
        $_ENV = array_merge($_ENV, $parsed);
        $this->env = $_ENV;
        $this->lastModifiedTime = filemtime($envFile);
    }

    /**
     * Determines whether the cached environment needs to be refreshed.
     */
    private function shouldReload(string $envFile): bool
    {
        $currentModifiedTime = filemtime($envFile);
        return $this->env === null ||
            $this->lastModifiedTime !== $currentModifiedTime;
    }

    /**
     * Builds an array combining $_ENV, $_SERVER, and getenv().
     *
     * @return array<string, mixed>
     */
    private function mergeSuperglobals(): array
    {
        $env = array_merge($_ENV, $_SERVER);

        foreach (getenv() as $key => $value) {
            $env[$key] = $value;
        }

        return $env;
    }

    /**
     * Parses a .env file into an associative array.
     *
     * @return array<string, mixed>
     * @throws FrameworkException When the file cannot be opened.
     */
    private function parseEnvFile(
        string $envFile,
        string $delimiter = '=',
    ): array {
        $env = $this->mergeSuperglobals();
        $handle = fopen($envFile, 'r');

        if ($handle === false) {
            Errors::throwConfigurationError('Unable to read env file', [
                'env_file' => $envFile,
            ]);
        }

        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line, "\r\n");

            $trimmed = ltrim($line);
            if (
                $trimmed === '' ||
                str_starts_with($trimmed, '#') ||
                str_starts_with($trimmed, ';')
            ) {
                continue;
            }

            $pos = strpos($line, $delimiter);
            if ($pos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $pos));
            $rawValue = ltrim(substr($line, $pos + strlen($delimiter)));

            if ($key === '') {
                continue;
            }

            $value = $this->parseEnvValue($rawValue);

            $value = preg_replace_callback(
                '/\${([A-Z0-9_]+)}/i',
                function ($matches) use ($env) {
                    return $env[$matches[1]] ?? $matches[0];
                },
                $value,
            );

            $env[$key] = $value;
        }

        fclose($handle);

        return $env;
    }

    /**
     * Normalizes raw env values, handling quotes and inline comments.
     */
    private function parseEnvValue(string $raw): string
    {
        $raw = ltrim($raw);

        if ($raw === '') {
            return '';
        }

        $firstChar = $raw[0];

        if ($firstChar === '"') {
            $value = substr($raw, 1);
            $endPos = strrpos($value, '"');

            if ($endPos !== false) {
                $value = substr($value, 0, $endPos);
            }

            $value = stripcslashes($value);
            return $value;
        }

        if ($firstChar === "'") {
            $value = substr($raw, 1);
            $endPos = strrpos($value, "'");

            if ($endPos !== false) {
                $value = substr($value, 0, $endPos);
            }

            return $value;
        }

        $hashPos = strpos($raw, '#');
        if ($hashPos !== false) {
            $raw = rtrim(substr($raw, 0, $hashPos));
        }

        return trim($raw);
    }

    /**
     * Retrieves a configuration or environment value.
     *
     * @param string $key Dot-notation supported.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (strpos($key, '.') !== false) {
            $value = $this->getNestedConfig($key);
            if ($value !== null) {
                return $value;
            }
        }

        if (isset($this->config[$key])) {
            return $this->config[$key];
        }

        return $this->env[$key] ?? ($_ENV[$key] ?? $default);
    }

    /**
     * Resolves config/env without applying defaults.
     */
    private function getRaw(string $key): mixed
    {
        if (strpos($key, '.') !== false) {
            $value = $this->getNestedConfig($key);
            if ($value !== null || array_key_exists($key, $this->config)) {
                return $value;
            }
        }

        if (array_key_exists($key, $this->config)) {
            return $this->config[$key];
        }

        if (array_key_exists($key, $this->env ?? [])) {
            return $this->env[$key];
        }

        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        return self::DEFAULT_SENTINEL;
    }

    /**
     * Traverses the configuration array to resolve dot-notation keys.
     */
    private function getNestedConfig(string $key): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $segment) {
            if (!isset($value[$segment])) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Overrides an environment variable for the current process.
     *
     * @return mixed The value that was set.
     */
    public function set(string $key, mixed $value): mixed
    {
        if ($this->env === null) {
            $this->env = [];
        }

        $_ENV[$key] = $value;
        $this->env[$key] = $value;

        return $value;
    }

    /**
     * Injects a configuration array under the given namespace.
     *
     * @param array<string, mixed> $data
     */
    public function setConfig(string $namespace, array $data): void
    {
        $this->config[$namespace] = $data;
    }

    /**
     * Loads all .php config files in a directory.
     *
     * When loading `app.php`, array values at the first level are automatically
     * promoted to their own namespace. This allows consolidating all configuration
     * in a single file:
     *
     * ```php
     * // config/app.php
     * return [
     *     'app' => ['name' => 'MyApp', 'debug' => true],
     *     'router' => ['files' => [__DIR__ . '/../routes/api.php']],
     *     'database' => ['host' => 'localhost'],
     * ];
     * ```
     *
     * This makes `Config::get('router.files')` work without needing a separate
     * `config/router.php` file. Separate files still take precedence if they exist.
     *
     * @throws FrameworkException When files cannot be read or return non-arrays.
     */
    public function loadConfigFiles(string $configDir): void
    {
        if (!is_dir($configDir)) {
            return;
        }

        $files = glob($configDir . '/*.php');

        if ($files === false) {
            Errors::throwConfigurationError(
                'Failed to read configuration directory',
                ['config_dir' => $configDir],
            );
        }

        // Sort files to ensure app.php is loaded first (for namespace promotion)
        sort($files);

        foreach ($files as $file) {
            $namespace = basename($file, '.php');

            try {
                $data = require $file;
            } catch (Throwable $exception) {
                Errors::throwConfigurationError(
                    'Failed to load configuration file',
                    ['file' => $file],
                    $exception,
                );
            }

            if (!is_array($data)) {
                Errors::throwConfigurationError(
                    'Configuration file must return an array',
                    ['file' => $file],
                );
            }

            // For app.php, promote array values to their own namespaces
            if ($namespace === 'app') {
                $this->loadAppConfig($data);
            } else {
                $this->setConfig($namespace, $data);
            }
        }
    }

    /**
     * Load app.php configuration with namespace promotion.
     *
     * Array values at the first level are promoted to their own namespace,
     * but only if that namespace hasn't been set yet (separate files take precedence).
     *
     * @param array<string, mixed> $data The app.php configuration data.
     */
    private function loadAppConfig(array $data): void
    {
        foreach ($data as $key => $value) {
            // Only promote array values to namespaces (not scalars like 'name', 'debug')
            // And only if the namespace doesn't already exist (separate files take precedence)
            if (is_array($value) && !isset($this->config[$key])) {
                $this->setConfig($key, $value);
            }
        }

        // Always set the full app config for backward compatibility
        // This ensures app.name, app.debug etc. still work with the old format
        $this->setConfig('app', $data);
    }

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
    ): mixed {
        $value = $this->getRaw($key);

        if ($value === self::DEFAULT_SENTINEL) {
            return $default;
        }

        return match ($type) {
            'int' => $this->castInt($key, $value, $default),
            'bool' => $this->castBool($key, $value, $default),
            'float' => $this->castFloat($key, $value, $default),
            default => (string) $value,
        };
    }

    /**
     * Retrieves a configuration value as an integer.
     */
    public function getInt(string $key, ?int $default = null): ?int
    {
        return $this->getTyped($key, $default, 'int');
    }

    /**
     * Retrieves a configuration value as a boolean.
     */
    public function getBool(string $key, ?bool $default = null): ?bool
    {
        return $this->getTyped($key, $default, 'bool');
    }

    /**
     * Retrieves a configuration value as a float.
     */
    public function getFloat(string $key, ?float $default = null): ?float
    {
        return $this->getTyped($key, $default, 'float');
    }

    /**
     * Retrieves a configuration value as a string.
     */
    public function getString(string $key, ?string $default = null): ?string
    {
        $value = $this->getRaw($key);
        if ($value === self::DEFAULT_SENTINEL) {
            return $default;
        }
        return (string) $value;
    }

    /**
     * Retrieves a configuration value as an array.
     *
     * @param array<mixed>|null $default
     * @return array<mixed>|null
     */
    public function getArray(string $key, ?array $default = null): ?array
    {
        $value = $this->get($key, $default);
        return is_array($value) ? $value : $default;
    }

    /**
     * Coerces a configuration value to an integer or throws.
     *
     * @throws FrameworkException When coercion fails.
     */
    private function castInt(string $key, mixed $value, mixed $default): mixed
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        if ($value === null && $default !== null) {
            return $default;
        }

        Errors::throwConfigurationError(
            "Invalid integer value for config '{$key}'",
            ['key' => $key, 'value' => $value],
        );
    }

    /**
     * Coerces a configuration value to a float or throws.
     *
     * @throws FrameworkException When coercion fails.
     */
    private function castFloat(string $key, mixed $value, mixed $default): mixed
    {
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        if ($value === null && $default !== null) {
            return $default;
        }

        Errors::throwConfigurationError(
            "Invalid float value for config '{$key}'",
            ['key' => $key, 'value' => $value],
        );
    }

    /**
     * Coerces a configuration value to a boolean or throws.
     *
     * @throws FrameworkException When coercion fails.
     */
    private function castBool(string $key, mixed $value, mixed $default): mixed
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        if (is_int($value)) {
            return $value !== 0;
        }

        if ($value === null && $default !== null) {
            return $default;
        }

        Errors::throwConfigurationError(
            "Invalid boolean value for config '{$key}'",
            ['key' => $key, 'value' => $value],
        );
    }

    /**
     * Validates a configuration value using a custom callback.
     *
     * @param callable(mixed):bool $validator
     * @return bool True when the validator returns truthy.
     */
    public function validate(string $key, callable $validator): bool
    {
        $value = $this->get($key);
        return isset($value) && $validator($value);
    }

    /**
     * Flushes all cached env/config state.
     */
    public function clearCache(): void
    {
        $this->env = null;
        $this->config = [];
        $this->lastModifiedTime = null;
        $this->cacheFile = null;
    }

    /**
     * Returns every tracked config + env value.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return array_merge($this->env ?? [], $_ENV, $this->config);
    }

    /**
     * Returns the cached environment variables.
     *
     * @return array<string, mixed>
     */
    public function allEnv(): array
    {
        return $this->env ?? $_ENV;
    }

    /**
     * Returns the loaded configuration arrays.
     *
     * @return array<string, mixed>
     */
    public function allConfig(): array
    {
        return $this->config;
    }

    /**
     * Checks if the current environment matches the given name.
     */
    public function isEnv(string $env): bool
    {
        $currentEnv = $this->get('app.env', null);

        if ($currentEnv === null || $currentEnv === '') {
            $currentEnv = $this->get('APP_ENV', null);
        }

        if ($currentEnv === null || $currentEnv === '') {
            $currentEnv = $this->get('ENV', 'development');
        }

        return strcasecmp((string) $currentEnv, $env) === 0;
    }

    /**
     * Determines if debug mode is enabled.
     */
    public function isDebug(): bool
    {
        $debugFlag = $this->getTyped('APP_DEBUG', null, 'bool');

        if ($debugFlag !== null) {
            return (bool) $debugFlag;
        }

        return $this->isDevelopment();
    }

    /**
     * Returns true when `app.env` equals `development`.
     */
    public function isDevelopment(): bool
    {
        return $this->isEnv('development');
    }

    /**
     * Returns true when the current environment is `production`.
     */
    public function isProduction(): bool
    {
        return $this->isEnv('production');
    }

    /**
     * Sets the path to the compiled configuration cache file.
     */
    public function setCacheFile(string $path): void
    {
        $this->cacheFile = $path;
    }

    /**
     * Indicates whether config caching is enabled via env/config.
     */
    public function isCacheEnabled(): bool
    {
        return $this->getTyped('CONFIG_CACHE_ENABLED', false, 'bool');
    }

    /**
     * Attempts to hydrate configuration state from the cache file.
     *
     * @return bool True when cache was loaded successfully.
     * @throws FrameworkException When the cache file throws during load.
     */
    public function loadFromCache(string $envFile): bool
    {
        if ($this->cacheFile === null) {
            return false;
        }

        $cache = new PhpArrayCache();
        try {
            $cached = $cache->load($this->cacheFile);
        } catch (Throwable $exception) {
            Errors::throwConfigurationError(
                'Unable to load configuration cache.',
                ['cache_file' => $this->cacheFile],
                $exception,
            );
        }

        if (
            $cached === null ||
            !isset($cached['env']) ||
            !isset($cached['config']) ||
            !isset($cached['hash'])
        ) {
            return false;
        }

        $currentHash = file_exists($envFile) ? md5_file($envFile) : '';
        if ($cached['hash'] !== $currentHash) {
            return false;
        }

        $this->env = $cached['env'];
        $this->config = $cached['config'];

        $_ENV = array_merge($_ENV, $this->env);

        if (file_exists($envFile)) {
            $this->lastModifiedTime = filemtime($envFile);
        }

        return true;
    }

    /**
     * Persists env/config state into the configured cache file.
     *
     * @param string $envFile Path to the original .env file (used for hashing).
     * @return bool True on success, false when caching is disabled.
     */
    public function saveToCache(string $envFile): bool
    {
        if ($this->cacheFile === null || $this->env === null) {
            return false;
        }

        $hash = file_exists($envFile) ? md5_file($envFile) : '';

        $payload = [
            'env' => $this->env ?? [],
            'config' => $this->config,
            'hash' => $hash,
        ];

        $cache = new PhpArrayCache();
        return $cache->save($this->cacheFile, $payload);
    }

    /**
     * Clears the configuration cache file.
     */
    public function clearConfigCache(): bool
    {
        if ($this->cacheFile === null || !file_exists($this->cacheFile)) {
            return false;
        }

        return unlink($this->cacheFile);
    }

    // =========================================================================
    // ArrayAccess Implementation
    // =========================================================================

    /**
     * @param string $offset
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->getRaw((string) $offset) !== self::DEFAULT_SENTINEL;
    }

    /**
     * @param string $offset
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->get((string) $offset);
    }

    /**
     * @param string $offset
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->set((string) $offset, $value);
    }

    /**
     * @param string $offset
     */
    public function offsetUnset(mixed $offset): void
    {
        $key = (string) $offset;
        unset($this->env[$key], $this->config[$key], $_ENV[$key]);
    }
}
