<?php

declare(strict_types=1);

namespace Lalaz\Testing\Integration;

/**
 * TestApplication - Mini Runtime for Integration Tests
 *
 * Provides a lightweight application bootstrap specifically designed
 * for testing Lalaz packages. It creates a minimal runtime environment
 * with a DI container and optional service providers.
 *
 * This class acts as a standalone container manager when the full
 * Lalaz framework is not available, or integrates with the framework's
 * Application singleton when it is.
 *
 * @package lalaz/testing
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
final class TestApplication
{
    /**
     * The current test application instance.
     */
    private static ?self $instance = null;

    /**
     * The dependency injection container.
     *
     * @var object
     */
    private object $container;

    /**
     * The provider registry (if framework available).
     *
     * @var object|null
     */
    private ?object $providers = null;

    /**
     * Registered service provider classes.
     *
     * @var array<class-string>
     */
    private array $registeredProviders = [];

    /**
     * Service overrides (mocks).
     *
     * @var array<string, mixed>
     */
    private array $overrides = [];

    /**
     * Test environment configuration.
     *
     * @var array<string, mixed>
     */
    private array $config = [];

    /**
     * Whether the application has been booted.
     */
    private bool $booted = false;

    /**
     * Whether the full framework is available.
     */
    private bool $frameworkAvailable = false;

    /**
     * Create a new TestApplication instance.
     */
    private function __construct()
    {
        $this->frameworkAvailable = $this->detectFramework();
        $this->container = $this->createContainer();
    }

    /**
     * Create and boot a new test application.
     *
     * @param array<class-string> $providers Service providers to register
     * @param array<string, mixed> $config Configuration values
     * @param callable|null $beforeBoot Callback to run before booting
     * @param callable|null $afterBoot Callback to run after booting
     * @return self
     */
    public static function create(
        array $providers = [],
        array $config = [],
        ?callable $beforeBoot = null,
        ?callable $afterBoot = null
    ): self {
        $app = new self();
        $app->config = $config;

        // Register core bindings
        $app->registerCoreBindings();

        // Apply overrides before boot
        if ($beforeBoot !== null) {
            $beforeBoot($app);
        }

        // Register service providers
        foreach ($providers as $provider) {
            $app->registerProvider($provider);
        }

        // Boot the application
        $app->boot();

        // Post-boot callback
        if ($afterBoot !== null) {
            $afterBoot($app);
        }

        // Set as global instance
        self::$instance = $app;
        $app->registerGlobalContext();

        return $app;
    }

    /**
     * Get the current test application instance.
     *
     * @return self|null
     */
    public static function getInstance(): ?self
    {
        return self::$instance;
    }

    /**
     * Get the DI container.
     *
     * @return object
     */
    public function container(): object
    {
        return $this->container;
    }

    /**
     * Resolve a service from the container.
     *
     * @template T
     * @param class-string<T>|string $abstract
     * @param array<string, mixed> $parameters
     * @return T|mixed
     */
    public function resolve(string $abstract, array $parameters = []): mixed
    {
        if ($this->frameworkAvailable) {
            return $this->container->resolve($abstract, $parameters);
        }

        // Fallback for simple container
        return $this->container->get($abstract);
    }

    /**
     * Check if a service is bound in the container.
     *
     * @param string $abstract
     * @return bool
     */
    public function bound(string $abstract): bool
    {
        return $this->container->has($abstract);
    }

    /**
     * Register a service provider.
     *
     * @param class-string $providerClass
     * @return self
     */
    public function registerProvider(string $providerClass): self
    {
        if (!class_exists($providerClass)) {
            return $this;
        }

        if (in_array($providerClass, $this->registeredProviders, true)) {
            return $this;
        }

        if ($this->frameworkAvailable && $this->providers !== null) {
            $this->providers->register($providerClass);
        }

        $this->registeredProviders[] = $providerClass;

        return $this;
    }

    /**
     * Override a service binding with a mock or custom implementation.
     *
     * @param string $abstract The service identifier
     * @param mixed $concrete The mock or replacement
     * @return self
     */
    public function mock(string $abstract, mixed $concrete): self
    {
        $this->overrides[$abstract] = $concrete;

        if ($this->booted) {
            $this->instance($abstract, $concrete);
        }

        return $this;
    }

    /**
     * Bind a service in the container.
     *
     * @param string $abstract
     * @param mixed $concrete
     * @return self
     */
    public function bind(string $abstract, mixed $concrete = null): self
    {
        if ($this->frameworkAvailable) {
            $this->container->bind($abstract, $concrete);
        }

        return $this;
    }

    /**
     * Bind a singleton in the container.
     *
     * @param string $abstract
     * @param mixed $concrete
     * @return self
     */
    public function singleton(string $abstract, mixed $concrete = null): self
    {
        if ($this->frameworkAvailable) {
            $this->container->singleton($abstract, $concrete);
        }

        return $this;
    }

    /**
     * Register an existing instance in the container.
     *
     * @param string $abstract
     * @param mixed $instance
     * @return self
     */
    public function instance(string $abstract, mixed $instance): self
    {
        if ($this->frameworkAvailable) {
            $this->container->instance($abstract, $instance);
        }

        return $this;
    }

    /**
     * Get a configuration value.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function config(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Set a configuration value.
     *
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function setConfig(string $key, mixed $value): self
    {
        $this->config[$key] = $value;
        return $this;
    }

    /**
     * Check if the full framework is available.
     *
     * @return bool
     */
    public function hasFramework(): bool
    {
        return $this->frameworkAvailable;
    }

    /**
     * Check if the application has been booted.
     *
     * @return bool
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * Get all registered provider classes.
     *
     * @return array<class-string>
     */
    public function getRegisteredProviders(): array
    {
        return $this->registeredProviders;
    }

    /**
     * Flush the test application and reset global state.
     *
     * @return void
     */
    public function flush(): void
    {
        // Clear global Application singleton if framework available
        if ($this->frameworkAvailable && class_exists(\Lalaz\Runtime\Application::class)) {
            \Lalaz\Runtime\Application::clearInstance();
        }

        // Flush the container if it supports it
        if (method_exists($this->container, 'flush')) {
            $this->container->flush();
        }

        // Clear local state
        $this->registeredProviders = [];
        $this->overrides = [];
        $this->config = [];
        $this->booted = false;

        // Clear static instance
        self::$instance = null;
    }

    /**
     * Destroy the test application.
     *
     * @return void
     */
    public static function destroy(): void
    {
        if (self::$instance !== null) {
            self::$instance->flush();
        }
    }

    /**
     * Detect if the Lalaz framework is available.
     *
     * @return bool
     */
    private function detectFramework(): bool
    {
        return class_exists(\Lalaz\Container\Container::class);
    }

    /**
     * Create the DI container.
     *
     * @return object
     */
    private function createContainer(): object
    {
        if ($this->frameworkAvailable) {
            $container = new \Lalaz\Container\Container();
            $this->providers = new \Lalaz\Container\ProviderRegistry($container);
            return $container;
        }

        // Return a simple array-based container as fallback
        return new SimpleContainer();
    }

    /**
     * Register core container bindings.
     *
     * @return void
     */
    private function registerCoreBindings(): void
    {
        if (!$this->frameworkAvailable) {
            return;
        }

        // Bind the container itself
        $this->container->instance(\Lalaz\Container\Contracts\ContainerInterface::class, $this->container);
        $this->container->instance(\Lalaz\Container\Container::class, $this->container);
        $this->container->instance('container', $this->container);

        // Bind the test application
        $this->container->instance(self::class, $this);
        $this->container->instance('app', $this);
    }

    /**
     * Register the global Application context.
     *
     * @return void
     */
    private function registerGlobalContext(): void
    {
        if (!$this->frameworkAvailable) {
            return;
        }

        if (!class_exists(\Lalaz\Runtime\Application::class)) {
            return;
        }

        $appContext = new \Lalaz\Runtime\Application(
            container: $this->container,
            basePath: $this->config['base_path'] ?? null,
            debug: (bool) ($this->config['debug'] ?? true),
        );

        \Lalaz\Runtime\Application::setInstance($appContext);
    }

    /**
     * Boot the test application.
     *
     * @return void
     */
    private function boot(): void
    {
        if ($this->booted) {
            return;
        }

        // Apply service overrides
        foreach ($this->overrides as $abstract => $concrete) {
            $this->instance($abstract, $concrete);
        }

        // Boot all registered providers
        if ($this->frameworkAvailable && $this->providers !== null) {
            $this->providers->boot();
        }

        $this->booted = true;
    }
}

/**
 * Simple array-based container for when framework is not available.
 *
 * @internal
 */
final class SimpleContainer
{
    /** @var array<string, mixed> */
    private array $bindings = [];

    public function has(string $id): bool
    {
        return isset($this->bindings[$id]);
    }

    public function get(string $id): mixed
    {
        return $this->bindings[$id] ?? throw new \RuntimeException("Service not found: {$id}");
    }

    public function set(string $id, mixed $value): void
    {
        $this->bindings[$id] = $value;
    }

    public function flush(): void
    {
        $this->bindings = [];
    }
}
