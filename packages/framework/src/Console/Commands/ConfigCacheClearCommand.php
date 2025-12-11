<?php

declare(strict_types=1);

namespace Lalaz\Console\Commands;

use Lalaz\Config\Config;
use Lalaz\Console\Contracts\CommandInterface;
use Lalaz\Console\Input;
use Lalaz\Console\Output;
use Lalaz\Runtime\Http\HttpApplication;

/**
 * Command that clears the configuration cache file.
 *
 * Removes the compiled configuration cache to force fresh loading
 * of environment variables and configuration files.
 *
 * Usage: php lalaz config:cache:clear
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
final class ConfigCacheClearCommand implements CommandInterface
{
    /**
     * Creates a new ConfigCacheClearCommand instance.
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
        return 'config:cache:clear';
    }

    /**
     * {@inheritdoc}
     */
    public function description(): string
    {
        return 'Remove the configuration cache file';
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
        $cacheFile = $this->resolveCacheFile();
        Config::setCacheFile($cacheFile);

        if (!is_file($cacheFile)) {
            $output->writeln('Configuration cache already cleared.');
            return 0;
        }

        if (!Config::clearConfigCache()) {
            $output->error('Unable to clear configuration cache.');
            return 1;
        }

        $output->writeln("Configuration cache cleared: {$cacheFile}");
        return 0;
    }

    /**
     * Resolves the configuration cache file path.
     *
     * @return string The cache file path
     */
    private function resolveCacheFile(): string
    {
        $basePath = $this->app->basePath() ?? getcwd();
        return $basePath . '/storage/cache/config.php';
    }
}
