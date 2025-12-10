<?php

declare(strict_types=1);

namespace Lalaz\Cache;

use Lalaz\Cache\Contracts\CacheStoreInterface;
use Lalaz\Container\ServiceProvider;

/**
 * Service provider for the Cache package.
 */
final class CacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->singleton(CacheManager::class, function () {
            $config = [];

            if (class_exists(\Lalaz\Config\Config::class)) {
                $config = \Lalaz\Config\Config::getArray('cache', []) ?? [];
            }

            return new CacheManager($config);
        });

        $this->bind(CacheStoreInterface::class, function () {
            /** @var CacheManager $manager */
            $manager = $this->container->resolve(CacheManager::class);
            return $manager->store();
        });
    }
}
