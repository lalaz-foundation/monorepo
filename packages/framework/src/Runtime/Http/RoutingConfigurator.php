<?php

declare(strict_types=1);

namespace Lalaz\Runtime\Http;

use Lalaz\Runtime\Http\Routing\RouteCacheRepository;
use Lalaz\Web\Routing\Contracts\RouteRegistrarInterface;
use Lalaz\Web\Routing\Contracts\RouterInterface;
use Lalaz\Web\Routing\Registrars\ControllerAttributeRegistrar;
use Lalaz\Web\Routing\Registrars\ControllerDiscoveryRegistrar;
use Lalaz\Web\Routing\Registrars\RouteFileRegistrar;

/**
 * Handles routing configuration: route files, controllers, discovery, and caching.
 *
 * This class encapsulates all routing-related configuration logic including:
 * - Loading routes from PHP files
 * - Registering controller-based routes via attributes
 * - Auto-discovering controllers in directories
 * - Route caching for production optimization
 *
 * This class is an internal implementation detail of HttpApplication.
 *
 * @internal
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
final class RoutingConfigurator
{
    /**
     * Route file paths to load.
     *
     * @var array<int, string>
     */
    private array $routeFiles = [];

    /**
     * Controller classes to register.
     *
     * @var array<int, class-string>
     */
    private array $controllerClasses = [];

    /**
     * Controller discovery configurations.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $controllerDiscovery = [];

    /**
     * Custom route registrars.
     *
     * @var array<int, RouteRegistrarInterface>
     */
    private array $customRegistrars = [];

    /**
     * The route cache repository.
     *
     * @var RouteCacheRepository|null
     */
    private ?RouteCacheRepository $routeCache = null;

    /**
     * Whether route caching is enabled.
     *
     * @var bool
     */
    private bool $routeCacheEnabled = false;

    /**
     * Whether to auto-warm the cache after configuration.
     *
     * @var bool
     */
    private bool $routeCacheAutoWarm = false;

    /**
     * Whether routing has been configured.
     *
     * @var bool
     */
    private bool $configured = false;

    /**
     * Creates a new routing configurator instance.
     *
     * @param string|null $basePath Base path for resolving relative paths
     */
    public function __construct(
        private readonly ?string $basePath = null,
    ) {
    }

    /**
     * Adds route files to be loaded.
     *
     * Route files should return a closure that receives the router.
     *
     * @param array<int, string> $files Array of file paths
     *
     * @return void
     */
    public function addRouteFiles(array $files): void
    {
        foreach ($files as $file) {
            if (is_string($file)) {
                $this->routeFiles[] = $this->resolvePath($file);
            }
        }
    }

    /**
     * Adds controller classes to register.
     *
     * Controllers should use Route attributes to define their routes.
     *
     * @param array<int, class-string> $controllers Array of controller class names
     *
     * @return void
     */
    public function addControllers(array $controllers): void
    {
        foreach ($controllers as $controller) {
            if (is_string($controller)) {
                $this->controllerClasses[] = $controller;
            }
        }
    }

    /**
     * Adds controller discovery configurations.
     *
     * Each entry should specify a path to scan for controller classes.
     *
     * @param array<int, array<string, mixed>> $paths Discovery configurations
     *
     * @return void
     */
    public function addControllerDiscovery(array $paths): void
    {
        foreach ($paths as $entry) {
            if (isset($entry['path']) && is_string($entry['path'])) {
                $entry['path'] = $this->resolvePath($entry['path']);
            }
            $this->controllerDiscovery[] = $entry;
        }
    }

    /**
     * Adds a custom route registrar.
     *
     * @param RouteRegistrarInterface $registrar The registrar to add
     *
     * @return void
     */
    public function addRegistrar(RouteRegistrarInterface $registrar): void
    {
        $this->customRegistrars[] = $registrar;
    }

    /**
     * Enables route caching.
     *
     * When enabled, routes are loaded from cache if available,
     * avoiding the overhead of parsing route files and attributes.
     *
     * @param string $file     Path to the cache file
     * @param bool   $autoWarm Whether to auto-warm cache after configuration
     *
     * @return void
     */
    public function enableCache(string $file, bool $autoWarm = false): void
    {
        $path = $this->resolvePath($file);
        $this->routeCache = new RouteCacheRepository($path);
        $this->routeCacheEnabled = true;
        $this->routeCacheAutoWarm = $autoWarm;
    }

    /**
     * Configures routing on the given router.
     *
     * This method is idempotent - calling it multiple times has no
     * effect after the first call. It attempts to load from cache
     * first, then falls back to building routes from registrars.
     *
     * @param RouterInterface $router The router to configure
     *
     * @return void
     */
    public function configure(RouterInterface $router): void
    {
        if ($this->configured) {
            return;
        }

        // Try loading from cache first
        if ($this->routeCacheEnabled && $this->routeCache !== null) {
            if ($this->routeCache->load($router)) {
                $this->configured = true;
                return;
            }
        }

        // Build and run registrars
        $registrars = array_merge(
            $this->customRegistrars,
            $this->buildDefaultRegistrars(),
        );

        foreach ($registrars as $registrar) {
            $registrar->register($router);
        }

        // Auto-warm cache if enabled
        if (
            $this->routeCacheEnabled &&
            $this->routeCacheAutoWarm &&
            $this->routeCache !== null
        ) {
            $this->routeCache->save($router);
        }

        $this->configured = true;
    }

    /**
     * Checks if routing has been configured.
     *
     * @return bool True if configure() has been called successfully
     */
    public function isConfigured(): bool
    {
        return $this->configured;
    }

    /**
     * Resets the configuration state.
     *
     * Useful for testing to allow reconfiguration.
     *
     * @return void
     */
    public function reset(): void
    {
        $this->configured = false;
    }

    /**
     * Builds the default route registrars.
     *
     * @return array<int, RouteRegistrarInterface> Array of registrars
     */
    private function buildDefaultRegistrars(): array
    {
        $registrars = [];

        if ($this->routeFiles !== []) {
            $registrars[] = new RouteFileRegistrar($this->routeFiles);
        }

        if ($this->controllerClasses !== []) {
            $registrars[] = new ControllerAttributeRegistrar(
                $this->controllerClasses,
            );
        }

        if ($this->controllerDiscovery !== []) {
            $registrars[] = new ControllerDiscoveryRegistrar(
                $this->controllerDiscovery,
            );
        }

        return $registrars;
    }

    /**
     * Resolves a path relative to the base path.
     *
     * Absolute paths and URLs are returned unchanged.
     *
     * @param string $path The path to resolve
     *
     * @return string The resolved path
     */
    private function resolvePath(string $path): string
    {
        if ($this->basePath === null) {
            return $path;
        }

        if (str_starts_with($path, '/') || str_contains($path, '://')) {
            return $path;
        }

        return $this->basePath . '/' . ltrim($path, '/');
    }
}
