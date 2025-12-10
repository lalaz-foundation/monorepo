<?php

declare(strict_types=1);

namespace Lalaz\Events;

use Lalaz\Config\Config;
use Lalaz\Container\ServiceProvider;
use Lalaz\Events\Contracts\EventDispatcherInterface;
use Lalaz\Events\Contracts\EventDriverInterface;
use Lalaz\Events\Drivers\NullDriver;
use Lalaz\Events\Drivers\QueueDriver;
use Lalaz\Support\Reflection\ClassResolver;

/**
 * Service provider for the Events package.
 *
 * Handles DI container bindings and bootstraps the event system
 * including driver configuration and listener auto-discovery.
 */
final class EventServiceProvider extends ServiceProvider
{
    /**
     * Register bindings in the container.
     */
    public function register(): void
    {
        // Register the async driver based on config
        $this->singleton(EventDriverInterface::class, function () {
            return $this->createAsyncDriver();
        });

        // Register the main EventHub
        $this->singleton(EventHub::class, function () {
            $asyncDriver = null;

            try {
                if ($this->container->has(EventDriverInterface::class)) {
                    $asyncDriver = $this->container->resolve(EventDriverInterface::class);
                }
            } catch (\Throwable $e) {
                // No async driver available
            }

            return new EventHub($asyncDriver);
        });

        // Alias for the interface
        $this->bind(EventDispatcherInterface::class, function () {
            return $this->container->resolve(EventHub::class);
        });
    }

    /**
     * Bootstrap the event system after all providers are registered.
     */
    public function boot(): void
    {
        /** @var EventHub $hub */
        $hub = $this->container->resolve(EventHub::class);

        // Set as global instance for facade
        Events::setInstance($hub);

        // Auto-discover and register listeners
        if ($this->isDiscoveryEnabled()) {
            $this->registerListeners($hub);
        }
    }

    /**
     * Create the async driver based on configuration.
     */
    private function createAsyncDriver(): ?EventDriverInterface
    {
        $driverName = Config::get('events.driver');

        if ($driverName === null || $driverName === 'sync') {
            return null;
        }

        return match ($driverName) {
            'null' => new NullDriver(),
            'queue' => $this->createQueueDriver(),
            default => $this->createCustomDriver($driverName),
        };
    }

    /**
     * Create the Queue driver with configuration.
     */
    private function createQueueDriver(): QueueDriver
    {
        $queue = Config::get('events.drivers.queue.queue') ?? 'events';
        $priority = Config::get('events.drivers.queue.priority') ?? 9;
        $delay = Config::get('events.drivers.queue.delay');

        return new QueueDriver(
            queue: is_string($queue) ? $queue : 'events',
            priority: is_int($priority) ? $priority : 9,
            delay: is_int($delay) ? $delay : null
        );
    }

    /**
     * Create a custom driver from config.
     */
    private function createCustomDriver(string $driverName): ?EventDriverInterface
    {
        $driverClass = Config::get("events.drivers.{$driverName}.driver");

        if (!is_string($driverClass) || !class_exists($driverClass)) {
            return null;
        }

        $driver = new $driverClass();

        if (!$driver instanceof EventDriverInterface) {
            return null;
        }

        return $driver;
    }

    private function isDiscoveryEnabled(): bool
    {
        return Config::getBool('events.discovery.enabled') ?? false;
    }

    private function registerListeners(EventHub $hub): void
    {
        $listenersPath = Config::get('events.discovery.path');

        if (!is_string($listenersPath) || $listenersPath === '') {
            return;
        }

        $listenersFiles = $this->loadListenerFiles($listenersPath);

        foreach ($listenersFiles as $listener) {
            if (!\is_string($listener)) {
                continue;
            }

            $listenerClassName = ClassResolver::getClassNameFromFile($listener);

            if (!class_exists($listenerClassName)) {
                continue;
            }

            try {
                $tempInstance = $this->container->resolve($listenerClassName);
            } catch (\Throwable $e) {
                continue;
            }

            if (!($tempInstance instanceof EventListener)) {
                continue;
            }

            foreach ($tempInstance->subscribers() as $eventName) {
                $hub->register($eventName, $listenerClassName);
            }
        }
    }

    private function loadListenerFiles(string $directory): array
    {
        $directory = rtrim($directory, '/\\');

        if (!is_dir($directory)) {
            return [];
        }

        $files = glob($directory . '/*.php');
        if ($files === false) {
            return [];
        }

        foreach ($files as $file) {
            require_once $file;
        }

        return $files;
    }
}
