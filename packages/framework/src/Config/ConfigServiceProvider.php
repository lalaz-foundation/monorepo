<?php

declare(strict_types=1);

namespace Lalaz\Config;

use Lalaz\Config\Contracts\ConfigRepositoryInterface;
use Lalaz\Container\ServiceProvider;

/**
 * Service provider for configuration services.
 *
 * Registers the configuration repository in the container, binding
 * the ConfigRepositoryInterface to the singleton instance used by
 * the Config facade. This ensures consistency between static and
 * injected access to configuration values.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
class ConfigServiceProvider extends ServiceProvider
{
    /**
     * Register configuration services in the container.
     *
     * Binds the ConfigRepositoryInterface and ConfigRepository classes
     * to the singleton Config instance, and creates a 'config' alias.
     *
     * @return void
     */
    public function register(): void
    {
        // Bind the interface to the singleton instance from the facade
        $this->singleton(
            ConfigRepositoryInterface::class,
            fn () => Config::getInstance(),
        );

        // Also bind the concrete class for explicit type hints
        $this->singleton(
            ConfigRepository::class,
            fn () => Config::getInstance(),
        );

        // Register aliases for convenience
        $this->alias('config', ConfigRepositoryInterface::class);
    }
}
