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
 * Command that compiles routes into a PHP cache file.
 *
 * Serializes the router's route definitions to a PHP file for
 * faster route registration on subsequent requests.
 *
 * Usage: php lalaz route:cache
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
final class RouteCacheCommand implements CommandInterface
{
    /**
     * Creates a new RouteCacheCommand instance.
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
        return 'route:cache';
    }

    /**
     * {@inheritdoc}
     */
    public function description(): string
    {
        return 'Compile routes into a PHP cache file';
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
        $enabled = (bool) ($config['enabled'] ?? false);
        $file = (string) ($config['file'] ?? '');

        if (!$enabled || $file === '') {
            $output->error('Route cache is not configured. Update router.php with cache settings.');
            return 1;
        }

        $path = $this->resolvePath($file);
        $this->app->enableRouteCache($path, true);
        $this->app->warmRouting();

        if (!is_file($path)) {
            $repo = new RouteCacheRepository($path);
            $repo->save($this->app->router());
        }

        $output->writeln("Routes cached at {$path}");
        return 0;
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
