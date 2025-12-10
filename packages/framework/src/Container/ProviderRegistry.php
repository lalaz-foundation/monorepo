<?php

declare(strict_types=1);

namespace Lalaz\Container;

use Lalaz\Container\Contracts\ContainerInterface;

/**
 * Registry for managing service providers.
 *
 * Handles registration and booting of service providers within
 * the application lifecycle. Providers are registered first,
 * then all are booted together to ensure dependencies are available.
 *
 * Example:
 * ```php
 * $registry = new ProviderRegistry($container);
 *
 * // Register providers
 * $registry->register(LoggingServiceProvider::class);
 * $registry->register(new DatabaseServiceProvider($container));
 *
 * // Boot all registered providers
 * $registry->boot();
 * ```
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
class ProviderRegistry
{
    /**
     * The container instance.
     *
     * @var ContainerInterface
     */
    protected ContainerInterface $container;

    /**
     * All registered service providers.
     *
     * @var array<int, ServiceProvider>
     */
    protected array $providers = [];

    /**
     * Service providers that have been booted.
     *
     * @var array<string, bool>
     */
    protected array $booted = [];

    /**
     * Create a new provider registry.
     *
     * @param ContainerInterface $container The DI container.
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Register a service provider.
     *
     * Accepts either a class name (which will be instantiated) or an
     * already-instantiated provider. The provider's register() method
     * is called immediately.
     *
     * @param string|ServiceProvider $provider Class name or instance.
     * @return ServiceProvider The registered provider instance.
     */
    public function register(string|ServiceProvider $provider): ServiceProvider
    {
        if (is_string($provider)) {
            $provider = new $provider($this->container);
        }

        $provider->register();
        $this->providers[] = $provider;
        return $provider;
    }

    /**
     * Register multiple service providers.
     *
     * @param array<int, string|ServiceProvider> $providers Array of providers.
     * @return void
     */
    public function registerProviders(array $providers): void
    {
        foreach ($providers as $provider) {
            $this->register($provider);
        }
    }

    /**
     * Boot all registered service providers.
     *
     * Iterates through all registered providers and calls their
     * boot() method. Each provider is only booted once.
     *
     * @return void
     */
    public function boot(): void
    {
        foreach ($this->providers as $provider) {
            $this->bootProvider($provider);
        }
    }

    /**
     * Boot a single service provider.
     *
     * Ensures the provider is only booted once, even if called multiple times.
     *
     * @param ServiceProvider $provider The provider to boot.
     * @return void
     */
    protected function bootProvider(ServiceProvider $provider): void
    {
        $class = get_class($provider);

        if (isset($this->booted[$class])) {
            return;
        }

        $provider->boot();
        $this->booted[$class] = true;
    }

    /**
     * Get all registered service providers.
     *
     * @return array<int, ServiceProvider> The registered providers.
     */
    public function getProviders(): array
    {
        return $this->providers;
    }

    /**
     * Boot console commands for all registered providers.
     *
     * Called after the command Registry is available to register
     * any commands that were declared before the Registry existed.
     *
     * @return void
     */
    public function bootCommands(): void
    {
        foreach ($this->providers as $provider) {
            $provider->bootCommands();
        }
    }
}
