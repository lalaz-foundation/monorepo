<?php

declare(strict_types=1);

namespace Lalaz\Reactive;

use Lalaz\Config\Config;
use Lalaz\Container\ServiceProvider;
use Lalaz\Reactive\Http\ReactiveController;
use Lalaz\Web\Routing\Router;

/**
 * ReactiveServiceProvider - Registers reactive components system
 *
 * @package lalaz/reactive
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
class ReactiveServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register ComponentRegistry as singleton
        $this->singleton(ComponentRegistry::class);

        // Register ReactiveManager as singleton
        $this->singleton(ReactiveManager::class, function ($container) {
            return new ReactiveManager(
                $container->resolve(ComponentRegistry::class),
                $container,
            );
        });

        // Register ReactiveController
        $this->bind(ReactiveController::class, function ($container) {
            return new ReactiveController(
                $container->resolve(ReactiveManager::class),
            );
        });
    }

    public function boot(): void
    {
        $this->configureComponentNamespace();
        $this->registerRoutes();
    }

    private function configureComponentNamespace(): void
    {
        $namespace = Config::getString('reactive.namespace', 'App\\Reactive');

        if (is_string($namespace) && $namespace !== '') {
            $manager = $this->container->resolve(ReactiveManager::class);
            $manager->setNamespace($namespace);
        }
    }

    private function registerRoutes(): void
    {
        /** @var Router $router */
        $router = $this->container->resolve(Router::class);

        $prefix =
            Config::getString('reactive.prefix', '/lalaz-reactive') ?: '/lalaz-reactive';
        $prefix = '/' . ltrim($prefix, '/');
        $prefix = rtrim($prefix, '/');

        $middlewares = Config::getArray('reactive.middleware', []);

        $callPath = $prefix . '/call';
        $updatePath = $prefix . '/update';

        $this->registerRouteIfMissing(
            $router,
            'POST',
            $callPath,
            [ReactiveController::class, 'call'],
            'reactive.call',
            $middlewares,
        );

        $this->registerRouteIfMissing(
            $router,
            'POST',
            $updatePath,
            [ReactiveController::class, 'update'],
            'reactive.update',
            $middlewares,
        );
    }

    private function registerRouteIfMissing(
        Router $router,
        string $method,
        string $path,
        array|callable $handler,
        string $name,
        array $middlewares,
    ): void {
        if ($this->routeExists($router, $method, $path)) {
            return;
        }

        // If handler is provided as [Class, method], wrap in a closure
        if (is_array($handler)) {
            [$class, $methodName] = $handler;
            $container = $this->container;
            $handler = function () use ($class, $methodName, $container): string {
                $controller = $container->resolve($class);
                $request = $container->resolve(\Lalaz\Web\Http\Request::class);
                return $controller->{$methodName}($request);
            };
        }

        $route = $router->route($method, $path, $handler, $middlewares);
        $route->as($name);
    }

    private function routeExists(
        Router $router,
        string $method,
        string $path,
    ): bool {
        foreach ($router->all() as $route) {
            if (
                $route->method() === strtoupper($method) &&
                $route->path() === $path
            ) {
                return true;
            }
        }

        return false;
    }
}
