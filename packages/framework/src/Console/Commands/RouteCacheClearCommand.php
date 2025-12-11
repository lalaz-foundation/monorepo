<?php

declare(strict_types=1);

namespace Lalaz\Console\Commands;

use Lalaz\Config\Config;
use Lalaz\Console\Contracts\CommandInterface;
use Lalaz\Console\Input;
use Lalaz\Console\Output;
use Lalaz\Runtime\Http\HttpApplication;
use Lalaz\Runtime\Http\Routing\RouteCacheRepository;

/**
 * Command that clears the route cache file.
 *
 * Removes the compiled route cache to force fresh route
 * registration on subsequent requests.
 *
 * Usage: php lalaz route:cache:clear
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
final class RouteCacheClearCommand implements CommandInterface
{
    /**
     * Creates a new RouteCacheClearCommand instance.
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
        return 'route:cache:clear';
    }

    /**
     * {@inheritdoc}
     */
    public function description(): string
    {
        return 'Remove the cached routes file';
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
        $config = Config::getArray('router.cache', []);
        $file = (string) ($config['file'] ?? '');

        if ($file === '') {
            $output->error('Route cache file is not configured.');
            return 1;
        }

        $path = $this->resolvePath($file);
        $repo = new RouteCacheRepository($path);
        if ($repo->clear()) {
            $output->writeln("Route cache cleared: {$path}");
            return 0;
        }

        $output->error('Unable to clear route cache.');
        return 1;
    }

    /**
     * Resolves a relative path to absolute.
     *
     * @param string $file The file path
     *
     * @return string The resolved path
     */
    private function resolvePath(string $file): string
    {
        if (str_starts_with($file, DIRECTORY_SEPARATOR) || str_contains($file, '://')) {
            return $file;
        }

        $base = $this->app->basePath();
        if ($base === null) {
            return $file;
        }

        return rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($file, DIRECTORY_SEPARATOR);
    }
}
