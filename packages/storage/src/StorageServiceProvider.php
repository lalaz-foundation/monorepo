<?php

declare(strict_types=1);

namespace Lalaz\Storage;

use Lalaz\Config\Config;
use Lalaz\Container\ServiceProvider;
use Lalaz\Storage\Contracts\StorageInterface;

/**
 * StorageServiceProvider
 *
 * Service provider for the Storage package.
 * Registers storage manager and default disk as services.
 *
 * @package lalaz/storage
 * @author  Elasticmind <ola@elasticmind.io>
 * @link    https://lalaz.dev
 */
final class StorageServiceProvider extends ServiceProvider
{
    /**
     * Register storage services.
     */
    public function register(): void
    {
        $this->singleton(StorageManager::class, function (): StorageManager {
            $config = Config::getArray('storage', []) ?? [];
            $manager = new StorageManager($config);
            Storage::setManager($manager);
            return $manager;
        });

        $this->bind(StorageInterface::class, function (): StorageInterface {
            /** @var StorageManager $manager */
            $manager = $this->container->resolve(StorageManager::class);
            return $manager->getDriver();
        });

        $this->alias(StorageManager::class, 'storage');
    }
}
