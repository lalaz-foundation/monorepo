<?php

declare(strict_types=1);

namespace Lalaz\Runtime\Http\Routing;

use Lalaz\Support\Cache\PhpArrayCache;
use Lalaz\Web\Routing\Contracts\RouterInterface;

/**
 * Repository for caching compiled route definitions.
 *
 * Provides persistence for route definitions to avoid re-parsing route
 * files and attributes on every request. Uses PhpArrayCache for efficient
 * native PHP array caching with opcode cache support.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
class RouteCacheRepository
{
    /**
     * The PHP array cache instance.
     *
     * @var PhpArrayCache
     */
    private PhpArrayCache $cache;

    /**
     * Create a new route cache repository.
     *
     * @param string $filePath Path to the cache file.
     */
    public function __construct(private string $filePath)
    {
        $this->cache = new PhpArrayCache();
    }

    /**
     * Load cached route definitions into the router.
     *
     * @param RouterInterface $router The router to populate.
     * @return bool True if cache was loaded successfully, false otherwise.
     */
    public function load(RouterInterface $router): bool
    {
        if (!is_file($this->filePath)) {
            return false;
        }

        $data = $this->cache->load($this->filePath);

        if (!is_array($data)) {
            return false;
        }

        $router->loadFromDefinitions($data);

        return true;
    }

    /**
     * Save route definitions from the router to cache.
     *
     * Creates the cache directory if it doesn't exist.
     *
     * @param RouterInterface $router The router to export definitions from.
     * @return bool True if cache was saved successfully, false otherwise.
     */
    public function save(RouterInterface $router): bool
    {
        $definitions = $router->exportDefinitions();

        $directory = dirname($this->filePath);

        if (
            !is_dir($directory) &&
            !mkdir($directory, 0777, true) &&
            !is_dir($directory)
        ) {
            return false;
        }

        return $this->cache->save($this->filePath, $definitions);
    }

    /**
     * Clear the route cache.
     *
     * @return bool True if cache was cleared successfully, false otherwise.
     */
    public function clear(): bool
    {
        if (!file_exists($this->filePath)) {
            return true;
        }

        return unlink($this->filePath);
    }
}
