<?php

declare(strict_types=1);

namespace Lalaz\Packages;

/**
 * Manager for installing, removing, and listing Lalaz packages.
 *
 * This class handles the full lifecycle of Lalaz packages including:
 * - Installing packages via Composer
 * - Publishing configuration, routes, migrations, and assets
 * - Registering service providers
 * - Running post-install scripts
 * - Removing packages and cleaning up
 * - Auto-detecting local packages in monorepo development
 *
 * Example usage:
 * ```php
 * $manager = new PackageManager('/path/to/project');
 *
 * // Install a package
 * $result = $manager->install('lalaz/auth');
 * foreach ($result->messages() as $message) {
 *     echo $message . PHP_EOL;
 * }
 *
 * // List all installed packages
 * $packages = $manager->packages();
 *
 * // Remove a package
 * $result = $manager->remove('lalaz/auth', keepConfig: false);
 * ```
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
final class PackageManager
{
    /**
     * Environment variable to disable local package detection.
     */
    private const ENV_DISABLE_LOCAL = 'LALAZ_DISABLE_LOCAL_PACKAGES';

    /**
     * Creates a new package manager instance.
     *
     * @param string        $basePath          The base path of the project
     * @param callable|null $composerRunner    Custom composer runner (for testing)
     *                                         Signature: fn(string $action, string $package, bool $dev, string $basePath): array{success:bool,output:string}
     * @param callable|null $commandRunner     Custom command runner (for testing)
     *                                         Signature: fn(string $command, string $basePath): array{success:bool,output:string}
     * @param string|null   $composerBinary    Path to composer binary (defaults to "composer")
     * @param string|null   $localPackagesPath Override path to local packages (for testing)
     */
    public function __construct(
        private string $basePath,
        private $composerRunner = null,
        private $commandRunner = null,
        private ?string $composerBinary = null,
        private ?string $localPackagesPath = null,
    ) {
        $this->basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
        $this->composerBinary = $composerBinary ?? 'composer';
    }

    /**
     * Installs a package.
     *
     * This method:
     * 1. Checks if a local version exists (monorepo development)
     * 2. If local, adds path repositories for the package AND its lalaz/* dependencies
     * 3. If not local, runs standard composer require
     * 4. Loads the package manifest (lalaz.json)
     * 5. Publishes configuration, routes, migrations, and assets
     * 6. Registers the service provider
     * 7. Runs post-install scripts
     *
     * @param string $packageName The composer package name (vendor/package)
     * @param bool   $dev         Whether to install as a dev dependency
     *
     * @return PackageOperationResult The result of the installation
     */
    public function install(
        string $packageName,
        bool $dev = false,
    ): PackageOperationResult {
        $messages = [];

        // Check for local package (monorepo development)
        $localPath = $this->findLocalPackage($packageName);

        if ($localPath !== null) {
            $messages[] = "Local package detected: {$localPath}";

            // Add path repository to composer.json
            $repoResult = $this->addPathRepository($packageName, $localPath);
            if (!$repoResult['success']) {
                $messages[] = 'Failed to add path repository: ' . $repoResult['error'];
                return new PackageOperationResult(false, $messages);
            }

            if ($repoResult['added']) {
                $messages[] = 'Path repository added to composer.json';
            }

            // Resolve transitive dependencies (other lalaz/* packages)
            $depMessages = $this->resolveLocalDependencies($localPath);
            $messages = array_merge($messages, $depMessages);

            // Force @dev version for local packages
            $composer = $this->runComposer('require', $packageName . ':@dev', $dev);
        } else {
            $composer = $this->runComposer('require', $packageName, $dev);
        }

        $messages[] =
            'Composer: ' .
            ($composer['success'] ? 'success' : 'failed (see output below)');

        if (!$composer['success']) {
            if ($composer['output'] !== '') {
                $messages[] = $composer['output'];
            }
            return new PackageOperationResult(false, $messages);
        }

        try {
            $manifest = $this->loadManifest($packageName);
        } catch (ManifestValidationException $e) {
            $messages[] = 'Manifest invalid: ' . $e->getMessage();
            return new PackageOperationResult(false, $messages);
        }

        if ($manifest === null) {
            $messages[] =
                'No lalaz.json manifest found. Package installed via Composer only.';
            return new PackageOperationResult(true, $messages);
        }

        $messages = array_merge(
            $messages,
            $this->publishConfig($manifest),
            $this->publishRoutes($manifest),
            $this->publishMigrations($manifest),
            $this->publishAssets($manifest),
            $this->registerProvider($manifest),
            $this->runPostInstallScripts($manifest),
        );

        $env = $manifest->envVariables();
        if ($env !== []) {
            $messages[] = 'Environment variables required:';
            foreach ($env as $var) {
                $messages[] = "  - {$var}";
            }
        }

        $post = $manifest->postInstallMessage();
        if ($post !== null && $post !== '') {
            $messages[] = $post;
        }

        return new PackageOperationResult(true, $messages, $manifest);
    }

    /**
     * Removes a package.
     *
     * This method:
     * 1. Loads the package manifest
     * 2. Unregisters the service provider
     * 3. Optionally removes published configuration
     * 4. Runs composer remove
     *
     * @param string $packageName The composer package name (vendor/package)
     * @param bool   $keepConfig  Whether to keep published config files
     *
     * @return PackageOperationResult The result of the removal
     */
    public function remove(
        string $packageName,
        bool $keepConfig = true,
    ): PackageOperationResult {
        $messages = [];
        try {
            $manifest = $this->loadManifest($packageName);
        } catch (ManifestValidationException $e) {
            $messages[] = 'Manifest invalid: ' . $e->getMessage();
            $manifest = null;
        }

        if ($manifest !== null) {
            $messages = array_merge(
                $messages,
                $this->unregisterProvider($manifest),
            );

            $shouldRemoveConfig =
                !$keepConfig && !$manifest->uninstallKeepConfig();

            if ($shouldRemoveConfig) {
                $messages = array_merge(
                    $messages,
                    $this->removePublishedConfig($manifest),
                );
            }
        }

        $composer = $this->runComposer('remove', $packageName, false);
        $messages[] =
            'Composer: ' .
            ($composer['success'] ? 'success' : 'failed (see output below)');

        if (!$composer['success'] && $composer['output'] !== '') {
            $messages[] = $composer['output'];
        }

        return new PackageOperationResult(
            $composer['success'],
            $messages,
            $manifest,
        );
    }

    /**
     * Gets all installed Lalaz packages.
     *
     * Scans the vendor directory for packages with lalaz.json manifests
     * and returns them sorted alphabetically by name.
     *
     * @return array<int, PackageManifest> Array of package manifests
     */
    public function packages(): array
    {
        $list = [];
        $vendorPath = $this->vendorPath();

        if (!is_dir($vendorPath)) {
            return [];
        }

        $vendors = array_filter(
            scandir($vendorPath) ?: [],
            fn ($item) => $item !== '.' && $item !== '..',
        );

        foreach ($vendors as $vendor) {
            $vendorDir = $vendorPath . DIRECTORY_SEPARATOR . $vendor;
            if (!is_dir($vendorDir)) {
                continue;
            }

            $packages = array_filter(
                scandir($vendorDir) ?: [],
                fn ($item) => $item !== '.' && $item !== '..',
            );

            foreach ($packages as $package) {
                try {
                    $manifest = $this->loadManifest($vendor . '/' . $package);
                } catch (ManifestValidationException $e) {
                    continue;
                }
                if ($manifest !== null) {
                    $list[] = $manifest;
                }
            }
        }

        usort(
            $list,
            fn (PackageManifest $a, PackageManifest $b) => strcmp(
                $a->name(),
                $b->name(),
            ),
        );

        return $list;
    }

    /**
     * Gets the manifest for a specific package.
     *
     * @param string $packageName The composer package name (vendor/package)
     *
     * @return PackageManifest|null The manifest or null if not found
     */
    public function getManifest(string $packageName): ?PackageManifest
    {
        return $this->loadManifest($packageName);
    }

    /**
     * Gets the path to the vendor directory.
     *
     * @return string The absolute path to vendor/
     */
    private function vendorPath(): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'vendor';
    }

    /**
     * Runs a composer command.
     *
     * @param string $action      The composer action (require, remove)
     * @param string $packageName The package name
     * @param bool   $dev         Whether to use --dev flag
     *
     * @return array{success: bool, output: string} The command result
     */
    private function runComposer(
        string $action,
        string $packageName,
        bool $dev,
    ): array {
        if ($this->composerRunner !== null) {
            return call_user_func(
                $this->composerRunner,
                $action,
                $packageName,
                $dev,
                $this->basePath,
            );
        }

        $bin = escapeshellcmd($this->composerBinary ?? 'composer');
        $devFlag = $dev ? ' --dev' : '';
        $command =
            $bin .
            ' ' .
            escapeshellarg($action) .
            ' ' .
            escapeshellarg($packageName) .
            $devFlag;

        $descriptor = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptor, $pipes, $this->basePath);

        if (!is_resource($process)) {
            return [
                'success' => false,
                'output' => 'Unable to execute composer command.',
            ];
        }

        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);

        foreach ($pipes as $pipe) {
            fclose($pipe);
        }

        $status = proc_close($process);
        $buffer = trim($output . "\n" . $error);

        return [
            'success' => $status === 0,
            'output' => $buffer,
        ];
    }

    /**
     * Runs a shell command.
     *
     * @param string $command The command to execute
     *
     * @return array{success: bool, output: string} The command result
     */
    private function runCommand(string $command): array
    {
        if ($this->commandRunner !== null) {
            return call_user_func(
                $this->commandRunner,
                $command,
                $this->basePath,
            );
        }

        $descriptor = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptor, $pipes, $this->basePath);

        if (!is_resource($process)) {
            return [
                'success' => false,
                'output' => "Unable to execute command: {$command}",
            ];
        }

        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);

        foreach ($pipes as $pipe) {
            fclose($pipe);
        }

        $status = proc_close($process);
        $buffer = trim($output . "\n" . $error);

        return [
            'success' => $status === 0,
            'output' => $buffer,
        ];
    }

    /**
     * Gets the path to a package's manifest file.
     *
     * @param string $packageName The composer package name
     *
     * @return string The absolute path to lalaz.json
     */
    private function manifestPath(string $packageName): string
    {
        return $this->vendorPath() .
            DIRECTORY_SEPARATOR .
            $packageName .
            DIRECTORY_SEPARATOR .
            'lalaz.json';
    }

    /**
     * Loads a package manifest.
     *
     * @param string $packageName The composer package name
     *
     * @return PackageManifest|null The manifest or null if not found
     *
     * @throws ManifestValidationException If manifest validation fails
     */
    private function loadManifest(string $packageName): ?PackageManifest
    {
        $path = $this->manifestPath($packageName);

        if (!is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        $data = json_decode($contents, true);

        if (!is_array($data)) {
            return null;
        }

        return PackageManifest::fromArray(dirname($path), $packageName, $data);
    }

    /**
     * Publishes configuration files from a package.
     *
     * @param PackageManifest $manifest The package manifest
     *
     * @return array<int, string> Status messages
     */
    private function publishConfig(PackageManifest $manifest): array
    {
        $config = $manifest->configPublication();
        if ($config === null) {
            return [];
        }

        return $this->publishFile(
            $manifest->packagePath(),
            $config['stub'],
            $config['destination'],
            (bool) $config['overwrite'],
        );
    }

    /**
     * Publishes route files from a package.
     *
     * @param PackageManifest $manifest The package manifest
     *
     * @return array<int, string> Status messages
     */
    private function publishRoutes(PackageManifest $manifest): array
    {
        $routes = $manifest->routesPublication();
        if ($routes === null) {
            return [];
        }

        return $this->publishFile(
            $manifest->packagePath(),
            $routes['stub'],
            $routes['destination'],
            (bool) $routes['overwrite'],
        );
    }

    /**
     * Publishes assets from a package.
     *
     * @param PackageManifest $manifest The package manifest
     *
     * @return array<int, string> Status messages
     */
    private function publishAssets(PackageManifest $manifest): array
    {
        $assets = $manifest->assetsPublication();
        if ($assets === null) {
            return [];
        }

        $source = $this->joinPath($manifest->packagePath(), $assets['source']);
        $destination = $this->resolveProjectPath($assets['destination']);
        $overwrite = (bool) $assets['overwrite'];

        if (!is_dir($source)) {
            return ["Assets directory not found: {$assets['source']}"];
        }

        if (is_dir($destination) && !$overwrite) {
            return ["Assets already published: {$assets['destination']}"];
        }

        $this->copyDirectory($source, $destination);
        return ["Assets published to {$assets['destination']}"];
    }

    /**
     * Publishes migrations from a package.
     *
     * @param PackageManifest $manifest The package manifest
     *
     * @return array<int, string> Status messages
     */
    private function publishMigrations(PackageManifest $manifest): array
    {
        $path = $manifest->migrationsPath();
        if ($path === null) {
            return [];
        }

        $source = $this->joinPath($manifest->packagePath(), $path);
        $destination = $this->resolveProjectPath(
            'database/migrations/' . $this->slug($manifest->name()),
        );

        if (!is_dir($source)) {
            return ["Migrations directory not found: {$path}"];
        }

        $this->copyDirectory($source, $destination);
        return ['Migrations published to database/migrations'];
    }

    /**
     * Runs post-install scripts from a package.
     *
     * @param PackageManifest $manifest The package manifest
     *
     * @return array<int, string> Status messages
     */
    private function runPostInstallScripts(PackageManifest $manifest): array
    {
        $scripts = $manifest->postInstallScripts();
        if ($scripts === []) {
            return [];
        }

        $messages = ['Running post-install scripts:'];

        foreach ($scripts as $script) {
            $result = $this->runCommand($script);
            if ($result['success']) {
                $messages[] = "  ✓ {$script}";
            } else {
                $messages[] = "  ✗ {$script}";
                if ($result['output'] !== '') {
                    $messages[] = $result['output'];
                }
            }
        }

        return $messages;
    }

    /**
     * Publishes a single file from source to destination.
     *
     * @param string $packagePath The package base path
     * @param string $stub        The source file relative path
     * @param string $destination The destination relative path
     * @param bool   $overwrite   Whether to overwrite existing files
     *
     * @return array<int, string> Status messages
     */
    private function publishFile(
        string $packagePath,
        string $stub,
        string $destination,
        bool $overwrite,
    ): array {
        $source = $this->joinPath($packagePath, $stub);
        $target = $this->resolveProjectPath($destination);

        if (!is_file($source)) {
            return ["File not found: {$stub}"];
        }

        if (is_file($target) && !$overwrite) {
            return ["File already exists: {$destination}"];
        }

        $directory = dirname($target);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        copy($source, $target);
        return ["Published {$destination}"];
    }

    /**
     * Registers a service provider from a package.
     *
     * @param PackageManifest $manifest The package manifest
     *
     * @return array<int, string> Status messages
     */
    private function registerProvider(PackageManifest $manifest): array
    {
        $provider = $manifest->provider();
        if ($provider === null) {
            return [];
        }

        $providersFile = $this->resolveProjectPath('config/providers.php');
        $config = $this->readProvidersConfig($providersFile);
        $providers = $config['providers'] ?? [];

        if (!in_array($provider, $providers, true)) {
            $providers[] = $provider;
            $config['providers'] = $providers;
            $this->writeProvidersConfig($providersFile, $config);
            return ["Provider registered: {$provider}"];
        }

        return ["Provider already registered: {$provider}"];
    }

    /**
     * Unregisters a service provider from a package.
     *
     * @param PackageManifest $manifest The package manifest
     *
     * @return array<int, string> Status messages
     */
    private function unregisterProvider(PackageManifest $manifest): array
    {
        $provider = $manifest->provider();
        if ($provider === null) {
            return [];
        }

        $providersFile = $this->resolveProjectPath('config/providers.php');
        if (!is_file($providersFile)) {
            return ['config/providers.php not found while removing provider.'];
        }

        $config = $this->readProvidersConfig($providersFile);
        $providers = $config['providers'] ?? [];

        $filtered = array_values(
            array_filter($providers, fn ($entry) => $entry !== $provider),
        );

        if ($filtered === $providers) {
            return ["Provider not registered: {$provider}"];
        }

        $config['providers'] = $filtered;
        $this->writeProvidersConfig($providersFile, $config);

        return ["Provider removed: {$provider}"];
    }

    /**
     * Removes published configuration files from a package.
     *
     * @param PackageManifest $manifest The package manifest
     *
     * @return array<int, string> Status messages
     */
    private function removePublishedConfig(PackageManifest $manifest): array
    {
        $config = $manifest->configPublication();
        if ($config === null) {
            return [];
        }

        $target = $this->resolveProjectPath($config['destination']);
        if (is_file($target)) {
            unlink($target);
            return ["Config removed: {$config['destination']}"];
        }

        return ['Config file not found while removing.'];
    }

    /**
     * Reads the providers configuration file.
     *
     * @param string $file The path to providers.php
     *
     * @return array<string, mixed> The configuration array
     */
    private function readProvidersConfig(string $file): array
    {
        if (is_file($file)) {
            $config = require $file;
            if (is_array($config)) {
                return $config;
            }
        }

        return ['providers' => []];
    }

    /**
     * Writes the providers configuration file.
     *
     * @param string              $file   The path to providers.php
     * @param array<string,mixed> $config The configuration to write
     *
     * @return void
     */
    private function writeProvidersConfig(string $file, array $config): void
    {
        $providers = $config['providers'] ?? [];
        $lines = [
            '<?php declare(strict_types=1);',
            '',
            'return [',
            "    'providers' => [",
        ];

        foreach ($providers as $provider) {
            $lines[] = "        {$provider}::class,";
        }

        $lines[] = '    ],';
        $lines[] = '];';
        $lines[] = '';

        file_put_contents($file, implode(PHP_EOL, $lines));
    }

    /**
     * Resolves a relative path to an absolute project path.
     *
     * @param string $path The relative path
     *
     * @return string The absolute path
     */
    private function resolveProjectPath(string $path): string
    {
        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        return $this->basePath .
            DIRECTORY_SEPARATOR .
            ltrim($path, DIRECTORY_SEPARATOR);
    }

    /**
     * Joins a base path with a relative path.
     *
     * @param string $base     The base path
     * @param string $relative The relative path
     *
     * @return string The combined path
     */
    private function joinPath(string $base, string $relative): string
    {
        if ($this->isAbsolutePath($relative)) {
            return $relative;
        }

        return rtrim($base, DIRECTORY_SEPARATOR) .
            DIRECTORY_SEPARATOR .
            ltrim($relative, DIRECTORY_SEPARATOR);
    }

    /**
     * Determines if a path is absolute.
     *
     * @param string $path The path to check
     *
     * @return bool True if the path is absolute
     */
    private function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        return $path[0] === '/' || preg_match('/^[A-Z]:\\\\/i', $path) === 1;
    }

    /**
     * Recursively copies a directory.
     *
     * @param string $source      The source directory
     * @param string $destination The destination directory
     *
     * @return void
     */
    private function copyDirectory(string $source, string $destination): void
    {
        if (!is_dir($destination)) {
            mkdir($destination, 0777, true);
        }

        $items = scandir($source);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $from = $source . DIRECTORY_SEPARATOR . $item;
            $to = $destination . DIRECTORY_SEPARATOR . $item;

            if (is_dir($from)) {
                $this->copyDirectory($from, $to);
                continue;
            }

            copy($from, $to);
        }
    }

    /**
     * Converts a string to a URL-friendly slug.
     *
     * @param string $value The string to slugify
     *
     * @return string The slugified string
     */
    private function slug(string $value): string
    {
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? $value;
        return trim($value, '-');
    }

    /**
     * Checks if local package detection is enabled.
     *
     * Local detection is disabled when:
     * - LALAZ_DISABLE_LOCAL_PACKAGES environment variable is set to "true", "1", or "yes"
     * - Running in production (APP_ENV=production)
     *
     * @return bool True if local detection is enabled
     */
    private function isLocalDetectionEnabled(): bool
    {
        // Check explicit disable flag
        $disabled = getenv(self::ENV_DISABLE_LOCAL);
        if ($disabled !== false) {
            $disabled = strtolower($disabled);
            if (in_array($disabled, ['true', '1', 'yes'], true)) {
                return false;
            }
        }

        // Disable in production environment
        $env = getenv('APP_ENV');
        if ($env === 'production') {
            return false;
        }

        return true;
    }

    /**
     * Finds a local package in the monorepo structure.
     *
     * Searches for the package in common monorepo locations:
     * - ../../packages/{package-name}
     * - ../packages/{package-name}
     * - Custom path set via constructor
     *
     * @param string $packageName The composer package name (vendor/package)
     *
     * @return string|null The relative path to local package, or null if not found
     */
    private function findLocalPackage(string $packageName): ?string
    {
        // Skip if local detection is disabled
        if (!$this->isLocalDetectionEnabled()) {
            return null;
        }

        // Extract package short name (e.g., "lalaz/auth" -> "auth")
        $parts = explode('/', $packageName);
        if (count($parts) !== 2) {
            return null;
        }

        $shortName = $parts[1];

        // Possible locations to check (relative to basePath)
        $searchPaths = [];

        // Custom path takes priority
        if ($this->localPackagesPath !== null) {
            $searchPaths[] = $this->localPackagesPath . '/' . $shortName;
        }

        // Standard monorepo locations
        $searchPaths = array_merge($searchPaths, [
            '../../packages/' . $shortName,
            '../packages/' . $shortName,
            '../' . $shortName,
        ]);

        foreach ($searchPaths as $relativePath) {
            $absolutePath = $this->basePath . '/' . $relativePath;
            $realPath = realpath($absolutePath);

            if ($realPath !== false && is_dir($realPath)) {
                // Verify it's a valid package (has composer.json)
                if (is_file($realPath . '/composer.json')) {
                    return $relativePath;
                }
            }
        }

        return null;
    }

    /**
     * Adds a path repository to composer.json for local package development.
     *
     * @param string $packageName  The composer package name
     * @param string $relativePath The relative path to the local package
     *
     * @return array{success: bool, added: bool, error?: string}
     */
    private function addPathRepository(string $packageName, string $relativePath): array
    {
        $composerJsonPath = $this->basePath . '/composer.json';

        if (!is_file($composerJsonPath)) {
            return ['success' => false, 'added' => false, 'error' => 'composer.json not found'];
        }

        $content = file_get_contents($composerJsonPath);
        if ($content === false) {
            return ['success' => false, 'added' => false, 'error' => 'Could not read composer.json'];
        }

        $json = json_decode($content, true);
        if (!is_array($json)) {
            return ['success' => false, 'added' => false, 'error' => 'Invalid composer.json'];
        }

        // Initialize repositories array if needed
        if (!isset($json['repositories'])) {
            $json['repositories'] = [];
        }

        // Check if this path repository already exists
        foreach ($json['repositories'] as $repo) {
            if (isset($repo['type']) && $repo['type'] === 'path') {
                if (isset($repo['url'])) {
                    // Normalize paths for comparison
                    $existingPath = rtrim($repo['url'], '/');
                    $newPath = rtrim($relativePath, '/');

                    if ($existingPath === $newPath) {
                        // Already exists
                        return ['success' => true, 'added' => false];
                    }
                }
            }
        }

        // Add new path repository
        $json['repositories'][] = [
            'type' => 'path',
            'url' => $relativePath,
            'options' => [
                'symlink' => true,
            ],
        ];

        // Write back with pretty print
        $newContent = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($newContent === false) {
            return ['success' => false, 'added' => false, 'error' => 'Could not encode composer.json'];
        }

        $result = file_put_contents($composerJsonPath, $newContent . "\n");
        if ($result === false) {
            return ['success' => false, 'added' => false, 'error' => 'Could not write composer.json'];
        }

        return ['success' => true, 'added' => true];
    }

    /**
     * Resolves and adds path repositories for lalaz/* dependencies of a local package.
     *
     * This method reads the composer.json of a local package and adds path
     * repositories for any lalaz/* dependencies found, enabling transitive
     * dependency resolution in monorepo development.
     *
     * @param string $localPath The relative path to the local package
     *
     * @return array<int, string> Status messages
     */
    private function resolveLocalDependencies(string $localPath): array
    {
        $messages = [];
        $absolutePath = $this->basePath . '/' . $localPath;
        $composerJsonPath = $absolutePath . '/composer.json';

        if (!is_file($composerJsonPath)) {
            return $messages;
        }

        $content = file_get_contents($composerJsonPath);
        if ($content === false) {
            return $messages;
        }

        $json = json_decode($content, true);
        if (!is_array($json)) {
            return $messages;
        }

        // Collect all lalaz/* dependencies from require and require-dev
        $dependencies = [];
        foreach (['require', 'require-dev'] as $section) {
            if (isset($json[$section]) && is_array($json[$section])) {
                foreach ($json[$section] as $dep => $version) {
                    if (str_starts_with($dep, 'lalaz/') && $dep !== 'lalaz/framework') {
                        $dependencies[] = $dep;
                    }
                }
            }
        }

        if (empty($dependencies)) {
            return $messages;
        }

        // Add path repositories for each dependency
        foreach ($dependencies as $depPackage) {
            $depLocalPath = $this->findLocalPackage($depPackage);

            if ($depLocalPath !== null) {
                $result = $this->addPathRepository($depPackage, $depLocalPath);

                if ($result['success'] && $result['added']) {
                    $messages[] = "Dependency path repository added: {$depPackage}";

                    // Recursively resolve dependencies of the dependency
                    $nestedMessages = $this->resolveLocalDependencies($depLocalPath);
                    $messages = array_merge($messages, $nestedMessages);
                }
            }
        }

        return $messages;
    }
}
