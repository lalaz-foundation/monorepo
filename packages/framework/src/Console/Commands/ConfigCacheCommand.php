<?php

declare(strict_types=1);

namespace Lalaz\Console\Commands;

use Lalaz\Config\Config;
use Lalaz\Console\Contracts\CommandInterface;
use Lalaz\Console\Input;
use Lalaz\Console\Output;
use Lalaz\Runtime\Http\HttpApplication;

/**
 * Command that compiles configuration into a cache file.
 *
 * Loads environment variables and configuration files, then writes
 * them to a single PHP cache file for faster subsequent loading.
 *
 * Usage: php lalaz config:cache
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
final class ConfigCacheCommand implements CommandInterface
{
    /**
     * Creates a new ConfigCacheCommand instance.
     *
     * @param HttpApplication $app The application instance
     */
    public function __construct(private HttpApplication $app)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'config:cache';
    }

    /**
     * {@inheritdoc}
     */
    public function description(): string
    {
        return 'Compile configuration into a cache file';
    }

    /**
     * {@inheritdoc}
     */
    public function arguments(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function options(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Input $input, Output $output): int
    {
        $basePath = $this->basePath();
        $envFile = $basePath . '/.env';
        $configDir = $basePath . '/config';
        $cacheFile = $basePath . '/storage/cache/config.php';

        $this->ensureCacheDirectory($cacheFile);

        Config::clearCache();
        Config::setCacheFile($cacheFile);
        Config::load($envFile, forceReload: true);
        Config::loadConfigFiles($configDir);

        if (!Config::saveToCache($envFile)) {
            $output->error('Unable to write configuration cache.');
            return 1;
        }

        $output->writeln("Configuration cached at {$cacheFile}");
        return 0;
    }

    /**
     * Gets the application base path.
     *
     * @return string The base path
     */
    private function basePath(): string
    {
        return $this->app->basePath() ?? getcwd();
    }

    /**
     * Ensures the cache directory exists.
     *
     * @param string $file The cache file path
     *
     * @return void
     */
    private function ensureCacheDirectory(string $file): void
    {
        $directory = dirname($file);
        if (!is_dir($directory)) {
            @mkdir($directory, 0777, true);
        }
    }
}
