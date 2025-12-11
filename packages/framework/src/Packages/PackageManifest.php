<?php

declare(strict_types=1);

namespace Lalaz\Packages;

/**
 * Represents a package manifest from lalaz.json.
 *
 * This class parses and provides access to package metadata defined
 * in a lalaz.json manifest file. It handles validation of the manifest
 * structure and provides normalized access to installation, publication,
 * and configuration settings.
 *
 * Example lalaz.json:
 * ```json
 * {
 *     "name": "lalaz/auth",
 *     "description": "Authentication package",
 *     "version": "1.0.0",
 *     "provider": "Lalaz\\Auth\\AuthServiceProvider",
 *     "install": {
 *         "config": "config/auth.php",
 *         "migrations": "migrations",
 *         "env": ["APP_KEY", "JWT_SECRET"]
 *     }
 * }
 * ```
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
final class PackageManifest
{
    /**
     * Creates a new package manifest instance.
     *
     * @param string              $packagePath The absolute path to the package directory
     * @param array<string,mixed> $data        The parsed manifest data
     * @param string              $packageName The composer package name (vendor/package)
     */
    private function __construct(
        private string $packagePath,
        private array $data,
        private string $packageName,
    ) {
    }

    /**
     * Creates a manifest from an array of data.
     *
     * Validates the data structure before creating the instance.
     *
     * @param string              $packagePath The absolute path to the package directory
     * @param string              $packageName The composer package name
     * @param array<string,mixed> $data        The manifest data array
     *
     * @return self A new manifest instance
     *
     * @throws ManifestValidationException If validation fails
     */
    public static function fromArray(
        string $packagePath,
        string $packageName,
        array $data,
    ): self {
        self::validate($data);
        return new self($packagePath, $data, $packageName);
    }

    /**
     * Gets the absolute path to the package directory.
     *
     * @return string The package path
     */
    public function packagePath(): string
    {
        return $this->packagePath;
    }

    /**
     * Gets the package name.
     *
     * Returns the name from the manifest or falls back to the composer package name.
     *
     * @return string The package name
     */
    public function name(): string
    {
        return (string) ($this->data['name'] ?? $this->packageName);
    }

    /**
     * Gets the package description.
     *
     * @return string|null The description or null if not set
     */
    public function description(): ?string
    {
        $description = $this->data['description'] ?? null;
        return is_string($description) ? $description : null;
    }

    /**
     * Gets the package version.
     *
     * @return string|null The version string or null if not set
     */
    public function version(): ?string
    {
        $version = $this->data['version'] ?? null;
        return is_string($version) ? $version : null;
    }

    /**
     * Gets the service provider class name.
     *
     * @return string|null The fully-qualified provider class name or null
     */
    public function provider(): ?string
    {
        $provider = $this->data['provider'] ?? null;
        return is_string($provider) ? $provider : null;
    }

    /**
     * Gets required environment variables.
     *
     * @return array<int, string> List of environment variable names
     */
    public function envVariables(): array
    {
        $env = $this->data['install']['env'] ?? [];
        if (!is_array($env)) {
            return [];
        }

        return array_values(
            array_filter(
                array_map(fn ($var) => is_string($var) ? $var : null, $env),
            ),
        );
    }

    /**
     * Gets config publication settings.
     *
     * Returns normalized publication settings for configuration files.
     *
     * @return array<string,mixed>|null Publication settings or null if not configured
     */
    public function configPublication(): ?array
    {
        return $this->normalizePublication(
            $this->data['install']['config'] ?? null,
            'config',
        );
    }

    /**
     * Gets routes publication settings.
     *
     * Returns normalized publication settings for route files.
     *
     * @return array<string,mixed>|null Publication settings or null if not configured
     */
    public function routesPublication(): ?array
    {
        return $this->normalizePublication(
            $this->data['install']['routes'] ?? null,
            'routes',
        );
    }

    /**
     * Gets assets publication settings.
     *
     * Returns normalized settings for publishing package assets
     * to the public directory.
     *
     * @return array<string,mixed>|null Asset settings with source, destination, overwrite keys
     */
    public function assetsPublication(): ?array
    {
        $assets = $this->data['install']['assets'] ?? null;
        if ($assets === null) {
            return null;
        }

        if (is_string($assets)) {
            return [
                'source' => $assets,
                'destination' => 'public/vendor/' . $this->name(),
                'overwrite' => false,
            ];
        }

        if (!is_array($assets)) {
            return null;
        }

        $source = $assets['source'] ?? null;
        if (!is_string($source)) {
            return null;
        }

        $destination = $assets['destination'] ?? null;
        if (!is_string($destination)) {
            $destination = 'public/vendor/' . $this->name();
        }

        $overwrite = (bool) ($assets['overwrite'] ?? false);

        return [
            'source' => $source,
            'destination' => $destination,
            'overwrite' => $overwrite,
        ];
    }

    /**
     * Gets the migrations directory path.
     *
     * @return string|null The relative path to migrations or null
     */
    public function migrationsPath(): ?string
    {
        $path = $this->data['install']['migrations'] ?? null;
        if (!is_string($path) || $path === '') {
            return null;
        }

        return $path;
    }

    /**
     * Gets the post-install message.
     *
     * @return string|null Message to display after installation or null
     */
    public function postInstallMessage(): ?string
    {
        $message = $this->data['post_install']['message'] ?? null;
        return is_string($message) ? $message : null;
    }

    /**
     * Gets post-install scripts to run.
     *
     * @return array<int, string> List of shell commands to execute
     */
    public function postInstallScripts(): array
    {
        $scripts = $this->data['post_install']['scripts'] ?? [];
        if (!is_array($scripts)) {
            return [];
        }

        return array_values(
            array_filter(
                array_map(
                    fn ($value) => is_string($value) ? $value : null,
                    $scripts,
                ),
            ),
        );
    }

    /**
     * Determines if config should be kept on uninstall.
     *
     * @return bool True if config files should be preserved
     */
    public function uninstallKeepConfig(): bool
    {
        $keep = $this->data['uninstall']['keep_config'] ?? true;
        return (bool) $keep;
    }

    /**
     * Normalizes publication configuration.
     *
     * Handles both string shortcuts and full object configurations,
     * returning a normalized array with stub, destination, and overwrite keys.
     *
     * @param mixed  $value     The raw publication value
     * @param string $directory The default destination directory
     *
     * @return array<string,mixed>|null Normalized publication settings or null
     */
    private function normalizePublication(
        mixed $value,
        string $directory,
    ): ?array {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return [
                'stub' => $value,
                'destination' => $directory . '/' . basename($value),
                'overwrite' => false,
            ];
        }

        if (!is_array($value)) {
            return null;
        }

        $stub = $value['stub'] ?? null;
        if (!is_string($stub)) {
            return null;
        }

        $destination = $value['destination'] ?? null;
        if (!is_string($destination)) {
            $destination = $directory . '/' . basename($stub);
        }

        $overwrite = (bool) ($value['overwrite'] ?? false);

        return [
            'stub' => $stub,
            'destination' => $destination,
            'overwrite' => $overwrite,
        ];
    }

    /**
     * Validates manifest data structure.
     *
     * @param array<string,mixed> $data The manifest data to validate
     *
     * @return void
     *
     * @throws ManifestValidationException If validation fails
     */
    private static function validate(array $data): void
    {
        if (isset($data['name']) && !is_string($data['name'])) {
            throw new ManifestValidationException(
                "Manifest 'name' must be a string.",
            );
        }

        if (isset($data['provider']) && !is_string($data['provider'])) {
            throw new ManifestValidationException(
                "Manifest 'provider' must be a string.",
            );
        }

        if (isset($data['install']) && !is_array($data['install'])) {
            throw new ManifestValidationException(
                "Manifest 'install' must be an object.",
            );
        }

        if (
            isset($data['install']['env']) &&
            !self::isArrayOfStrings($data['install']['env'])
        ) {
            throw new ManifestValidationException(
                "'install.env' must be an array of strings.",
            );
        }

        foreach (['config', 'routes'] as $key) {
            if (!isset($data['install'][$key])) {
                continue;
            }

            $value = $data['install'][$key];

            if (is_string($value)) {
                continue;
            }

            if (!is_array($value) || !isset($value['stub'])) {
                throw new ManifestValidationException(
                    "'install.{$key}' must be a string or an object with a 'stub'.",
                );
            }

            if (!is_string($value['stub'])) {
                throw new ManifestValidationException(
                    "'install.{$key}.stub' must be a string.",
                );
            }

            if (
                isset($value['destination']) &&
                !is_string($value['destination'])
            ) {
                throw new ManifestValidationException(
                    "'install.{$key}.destination' must be a string.",
                );
            }
        }

        if (isset($data['install']['assets'])) {
            $assets = $data['install']['assets'];
            if (is_array($assets)) {
                if (
                    !isset($assets['source']) ||
                    !is_string($assets['source'])
                ) {
                    throw new ManifestValidationException(
                        "'install.assets.source' must be a string.",
                    );
                }

                if (
                    isset($assets['destination']) &&
                    !is_string($assets['destination'])
                ) {
                    throw new ManifestValidationException(
                        "'install.assets.destination' must be a string.",
                    );
                }
            } elseif (!is_string($assets)) {
                throw new ManifestValidationException(
                    "'install.assets' must be a string or object.",
                );
            }
        }

        if (
            isset($data['install']['migrations']) &&
            !is_string($data['install']['migrations'])
        ) {
            throw new ManifestValidationException(
                "'install.migrations' must be a string path.",
            );
        }

        if (
            isset($data['post_install']['scripts']) &&
            !self::isArrayOfStrings($data['post_install']['scripts'])
        ) {
            throw new ManifestValidationException(
                "'post_install.scripts' must be an array of strings.",
            );
        }

        if (
            isset($data['uninstall']['keep_config']) &&
            !is_bool($data['uninstall']['keep_config'])
        ) {
            throw new ManifestValidationException(
                "'uninstall.keep_config' must be boolean.",
            );
        }
    }

    /**
     * Checks if a value is an array of strings.
     *
     * @param mixed $value The value to check
     *
     * @return bool True if value is an array containing only strings
     */
    private static function isArrayOfStrings(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (!is_string($item)) {
                return false;
            }
        }

        return true;
    }
}
