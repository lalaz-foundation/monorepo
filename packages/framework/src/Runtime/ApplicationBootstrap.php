<?php

declare(strict_types=1);

namespace Lalaz\Runtime;

use Lalaz\Config\Config;
use Lalaz\Container\ProviderRegistry;
use Lalaz\Exceptions\FrameworkException;
use Lalaz\Packages\PackageDiscovery;

/**
 * Application bootstrap helper for environment and provider setup.
 *
 * This class provides static methods to bootstrap the application environment
 * including loading configuration files, setting up the timezone, and
 * registering service providers from configuration and discovered packages.
 *
 * Typical usage in application entry point:
 * ```php
 * $basePath = dirname(__DIR__);
 * ApplicationBootstrap::bootstrapEnvironment($basePath);
 *
 * $registry = new ProviderRegistry($container);
 * ApplicationBootstrap::registerConfiguredProviders($registry);
 * ```
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
final class ApplicationBootstrap
{
    /**
     * The current base path being used.
     */
    private static ?string $basePath = null;

    /**
     * Bootstrap the application environment.
     *
     * This method:
     * - Sets up the configuration cache file path
     * - Loads environment variables from .env file
     * - Loads configuration files from the config directory
     * - Sets the application timezone
     *
     * @param string $basePath The base path of the application (project root).
     * @return void
     *
     * @throws FrameworkException If an invalid timezone is configured.
     *
     * @example
     * ```php
     * ApplicationBootstrap::bootstrapEnvironment('/var/www/myapp');
     * ```
     */
    public static function bootstrapEnvironment(string $basePath): void
    {
        self::$basePath = rtrim($basePath, DIRECTORY_SEPARATOR);

        $cacheFile = self::$basePath . '/storage/cache/config.php';
        $envFile = self::$basePath . '/.env';
        $configDir = self::$basePath . '/config';

        if (!is_dir(dirname($cacheFile))) {
            @mkdir(dirname($cacheFile), 0777, true);
        }

        Config::setCacheFile($cacheFile);
        Config::load($envFile);
        Config::loadConfigFiles($configDir);

        $timezone = Config::get('app.timezone', 'UTC');

        if (is_string($timezone) && $timezone !== '') {
            try {
                new \DateTimeZone($timezone);
                date_default_timezone_set($timezone);
            } catch (\Throwable $e) {
                throw new FrameworkException(
                    'Invalid timezone configured',
                    ['timezone' => $timezone],
                    $e,
                );
            }
        }
    }

    /**
     * Register and boot service providers from configuration and discovered packages.
     *
     * This method:
     * 1. First loads providers from auto-discovered Lalaz packages (lalaz.json)
     * 2. Then loads providers from the manual configuration (config/providers.php)
     *
     * Manual providers take precedence and can override discovered ones.
     *
     * The configuration should be in config/providers.php:
     * ```php
     * return [
     *     'providers' => [
     *         App\Providers\AppServiceProvider::class,
     *         App\Providers\RouteServiceProvider::class,
     *     ],
     * ];
     * ```
     *
     * @param ProviderRegistry $registry The provider registry to register providers with.
     * @return void
     *
     * @example
     * ```php
     * $registry = new ProviderRegistry($container);
     * ApplicationBootstrap::registerConfiguredProviders($registry);
     * ```
     */
    public static function registerConfiguredProviders(
        ProviderRegistry $registry,
    ): void {
        // First, register discovered providers from packages
        self::registerDiscoveredProviders($registry);

        // Then, register manually configured providers (these take precedence)
        $providers = Config::getArray('providers.providers', []);

        if (is_array($providers) && $providers !== []) {
            foreach ($providers as $provider) {
                if (is_string($provider) && class_exists($provider)) {
                    $registry->register($provider);
                }
            }
        }

        $registry->boot();
    }

    /**
     * Register service providers from discovered Lalaz packages.
     *
     * Scans for packages with lalaz.json manifests and registers
     * their service providers automatically.
     *
     * @param ProviderRegistry $registry The provider registry.
     * @return void
     */
    private static function registerDiscoveredProviders(
        ProviderRegistry $registry,
    ): void {
        if (self::$basePath === null) {
            return;
        }

        $discovery = new PackageDiscovery(self::$basePath);
        $providers = $discovery->providers();

        foreach ($providers as $packageName => $providerClass) {
            if (is_string($providerClass) && class_exists($providerClass)) {
                $registry->register($providerClass);
            }
        }
    }

    /**
     * Get the current base path.
     *
     * @return string|null
     */
    public static function basePath(): ?string
    {
        return self::$basePath;
    }
}
