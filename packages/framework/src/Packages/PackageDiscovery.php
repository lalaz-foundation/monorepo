<?php

declare(strict_types=1);

namespace Lalaz\Packages;

/**
 * Auto-discovers installed Lalaz packages and their configurations.
 *
 * This class scans the vendor directory for packages with lalaz.json manifests
 * and generates a cached manifest file containing all discovered providers,
 * configurations, and other package metadata.
 *
 * The discovery process runs automatically on `composer install` and
 * `composer update` via Composer scripts, ensuring that newly installed
 * packages are immediately available without manual configuration.
 *
 * Example usage:
 * ```php
 * // During composer post-install
 * PackageDiscovery::discover('/path/to/project');
 *
 * // At runtime
 * $discovery = new PackageDiscovery('/path/to/project');
 * $providers = $discovery->providers();
 * ```
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
final class PackageDiscovery
{
    /**
     * Cache file name for discovered packages.
     */
    private const CACHE_FILE = 'storage/cache/packages.php';

    /**
     * Vendor directory name.
     */
    private const VENDOR_DIR = 'vendor';

    /**
     * Lalaz vendor namespace.
     */
    private const LALAZ_VENDOR = 'lalaz';

    /**
     * The base path of the project.
     */
    private string $basePath;

    /**
     * Cached discovered packages data.
     *
     * @var array<string, mixed>|null
     */
    private ?array $discovered = null;

    /**
     * Creates a new package discovery instance.
     *
     * @param string $basePath The base path of the project
     */
    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
    }

    /**
     * Runs the discovery process and caches the result.
     *
     * This method scans all installed packages for lalaz.json manifests
     * and writes a cached PHP file with the discovered configuration.
     *
     * @param string $basePath The project base path
     * @return array{packages: int, providers: int, configs: int} Discovery statistics
     */
    public static function discover(string $basePath): array
    {
        $instance = new self($basePath);
        $packages = $instance->scanPackages();

        $stats = [
            'packages' => count($packages),
            'providers' => 0,
            'configs' => 0,
        ];

        $manifest = [
            'providers' => [],
            'configs' => [],
            'env' => [],
            'migrations' => [],
            'assets' => [],
            'packages' => [],
        ];

        foreach ($packages as $name => $data) {
            $manifest['packages'][$name] = [
                'version' => $data['version'] ?? '1.0.0',
                'description' => $data['description'] ?? '',
            ];

            // Collect provider
            if (isset($data['provider']) && is_string($data['provider'])) {
                $manifest['providers'][$name] = $data['provider'];
                $stats['providers']++;
            }

            // Collect config
            if (isset($data['install']['config'])) {
                $config = $data['install']['config'];
                if (is_array($config) && isset($config['stub'], $config['destination'])) {
                    $manifest['configs'][$name] = [
                        'stub' => $data['path'] . '/' . $config['stub'],
                        'destination' => $config['destination'],
                        'overwrite' => $config['overwrite'] ?? false,
                    ];
                    $stats['configs']++;
                }
            }

            // Collect env variables
            if (isset($data['install']['env']) && is_array($data['install']['env'])) {
                $manifest['env'][$name] = $data['install']['env'];
            }

            // Collect migrations
            if (isset($data['install']['migrations']) && is_string($data['install']['migrations'])) {
                $manifest['migrations'][$name] = $data['path'] . '/' . $data['install']['migrations'];
            }

            // Collect assets
            if (isset($data['install']['assets'])) {
                $assets = $data['install']['assets'];
                if (is_array($assets) && isset($assets['source'])) {
                    $manifest['assets'][$name] = [
                        'source' => $data['path'] . '/' . $assets['source'],
                        'destination' => $assets['destination'] ?? 'public/vendor/' . $name,
                        'overwrite' => $assets['overwrite'] ?? false,
                    ];
                }
            }
        }

        $instance->writeCache($manifest);

        return $stats;
    }

    /**
     * Gets discovered service providers.
     *
     * Returns an array of fully-qualified class names for all
     * discovered Lalaz package service providers.
     *
     * @return array<string, string> Map of package name to provider class
     */
    public function providers(): array
    {
        return $this->getManifest()['providers'] ?? [];
    }

    /**
     * Gets discovered configuration files.
     *
     * @return array<string, array{stub: string, destination: string, overwrite: bool}>
     */
    public function configs(): array
    {
        return $this->getManifest()['configs'] ?? [];
    }

    /**
     * Gets discovered environment variables.
     *
     * @return array<string, array<int, string>>
     */
    public function envVariables(): array
    {
        return $this->getManifest()['env'] ?? [];
    }

    /**
     * Gets discovered migrations paths.
     *
     * @return array<string, string>
     */
    public function migrations(): array
    {
        return $this->getManifest()['migrations'] ?? [];
    }

    /**
     * Gets discovered assets.
     *
     * @return array<string, array{source: string, destination: string, overwrite: bool}>
     */
    public function assets(): array
    {
        return $this->getManifest()['assets'] ?? [];
    }

    /**
     * Gets discovered packages metadata.
     *
     * @return array<string, array{version: string, description: string}>
     */
    public function packages(): array
    {
        return $this->getManifest()['packages'] ?? [];
    }

    /**
     * Checks if a specific package was discovered.
     *
     * @param string $packageName The package name (e.g., 'lalaz/auth')
     * @return bool
     */
    public function hasPackage(string $packageName): bool
    {
        return isset($this->getManifest()['packages'][$packageName]);
    }

    /**
     * Publishes configuration files for discovered packages.
     *
     * Copies stub config files to the project's config directory
     * if they don't already exist (unless overwrite is true).
     *
     * @return array<int, string> List of published config files
     */
    public function publishConfigs(): array
    {
        $published = [];

        foreach ($this->configs() as $package => $config) {
            $destination = $this->basePath . '/' . $config['destination'];

            if (!$config['overwrite'] && file_exists($destination)) {
                continue;
            }

            $stub = $config['stub'];
            if (!file_exists($stub)) {
                continue;
            }

            $dir = dirname($destination);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            copy($stub, $destination);
            $published[] = $config['destination'];
        }

        return $published;
    }

    /**
     * Publishes assets for discovered packages.
     *
     * @return array<int, string> List of published asset directories
     */
    public function publishAssets(): array
    {
        $published = [];

        foreach ($this->assets() as $package => $asset) {
            $source = $asset['source'];
            $destination = $this->basePath . '/' . $asset['destination'];

            if (!$asset['overwrite'] && is_dir($destination)) {
                continue;
            }

            if (!is_dir($source)) {
                continue;
            }

            $this->copyDirectory($source, $destination);
            $published[] = $asset['destination'];
        }

        return $published;
    }

    /**
     * Clears the discovery cache.
     *
     * @return bool True if cache was cleared
     */
    public function clearCache(): bool
    {
        $cacheFile = $this->getCacheFile();

        if (file_exists($cacheFile)) {
            unlink($cacheFile);
            $this->discovered = null;
            return true;
        }

        return false;
    }

    /**
     * Scans the vendor directory for Lalaz packages.
     *
     * @return array<string, array<string, mixed>>
     */
    private function scanPackages(): array
    {
        $packages = [];
        $vendorPath = $this->basePath . '/' . self::VENDOR_DIR . '/' . self::LALAZ_VENDOR;

        if (!is_dir($vendorPath)) {
            return $packages;
        }

        $iterator = new \DirectoryIterator($vendorPath);

        foreach ($iterator as $item) {
            if ($item->isDot() || !$item->isDir()) {
                continue;
            }

            $packageName = self::LALAZ_VENDOR . '/' . $item->getFilename();
            $packagePath = $item->getPathname();
            $manifestFile = $packagePath . '/lalaz.json';

            if (!file_exists($manifestFile)) {
                continue;
            }

            $content = file_get_contents($manifestFile);
            if ($content === false) {
                continue;
            }

            $data = json_decode($content, true);
            if (!is_array($data)) {
                continue;
            }

            $data['path'] = $packagePath;
            $packages[$packageName] = $data;
        }

        return $packages;
    }

    /**
     * Gets the cached manifest data.
     *
     * @return array<string, mixed>
     */
    private function getManifest(): array
    {
        if ($this->discovered !== null) {
            return $this->discovered;
        }

        $cacheFile = $this->getCacheFile();

        if (file_exists($cacheFile)) {
            $this->discovered = require $cacheFile;
            return $this->discovered;
        }

        // Cache doesn't exist, run discovery
        self::discover($this->basePath);

        if (file_exists($cacheFile)) {
            $this->discovered = require $cacheFile;
            return $this->discovered;
        }

        return [];
    }

    /**
     * Writes the manifest cache file.
     *
     * @param array<string, mixed> $manifest
     */
    private function writeCache(array $manifest): void
    {
        $cacheFile = $this->getCacheFile();
        $cacheDir = dirname($cacheFile);

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }

        $content = "<?php\n\n// Auto-generated by Lalaz Package Discovery\n// Do not edit manually\n\nreturn " .
            var_export($manifest, true) . ";\n";

        file_put_contents($cacheFile, $content);
    }

    /**
     * Gets the cache file path.
     *
     * @return string
     */
    private function getCacheFile(): string
    {
        return $this->basePath . '/' . self::CACHE_FILE;
    }

    /**
     * Recursively copies a directory.
     *
     * @param string $source
     * @param string $destination
     */
    private function copyDirectory(string $source, string $destination): void
    {
        if (!is_dir($destination)) {
            mkdir($destination, 0777, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $target = $destination . '/' . $iterator->getSubPathname();

            if ($item->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0777, true);
                }
            } else {
                copy($item->getPathname(), $target);
            }
        }
    }
}
