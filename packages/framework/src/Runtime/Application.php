<?php

declare(strict_types=1);

namespace Lalaz\Runtime;

use Lalaz\Container\Contracts\ContainerInterface;
use Lalaz\Events\Contracts\EventDispatcherInterface;

/**
 * Global application singleton for accessing the current application context.
 *
 * This class provides a static facade to access the current application's
 * container, event dispatcher, and other core services from anywhere in the
 * application, including helper functions and service providers.
 *
 * The singleton is automatically set when using HttpApplication::boot() or
 * when running console commands through ConsoleKernel.
 *
 * Example usage:
 * ```php
 * // Access the container
 * $container = Application::container();
 *
 * // Resolve a service
 * $logger = Application::container()->resolve(LoggerInterface::class);
 *
 * // Access the full context
 * $context = Application::context();
 * $events = $context->events();
 * ```
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
final class Application
{
    /**
     * The singleton application instance.
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * The dependency injection container.
     *
     * @var ContainerInterface
     */
    private ContainerInterface $container;

    /**
     * The event dispatcher instance.
     *
     * @var EventDispatcherInterface|null
     */
    private ?EventDispatcherInterface $events = null;

    /**
     * The application base path.
     *
     * @var string|null
     */
    private ?string $basePath = null;

    /**
     * Whether debug mode is enabled.
     *
     * @var bool
     */
    private bool $debug = false;

    /**
     * Create a new Application instance.
     *
     * @param ContainerInterface $container The DI container.
     * @param string|null $basePath The application base path.
     * @param bool $debug Whether debug mode is enabled.
     */
    public function __construct(
        ContainerInterface $container,
        ?string $basePath = null,
        bool $debug = false,
    ) {
        $this->container = $container;
        $this->basePath = $basePath;
        $this->debug = $debug;
    }

    /**
     * Set the global application instance.
     *
     * This is typically called by HttpApplication::boot() or ConsoleKernel
     * to establish the application context for the current request/command.
     *
     * @param self|null $app The application instance, or null to clear.
     * @return void
     */
    public static function setInstance(?self $app): void
    {
        self::$instance = $app;
    }

    /**
     * Get the global application instance.
     *
     * @return self|null The application instance, or null if not set.
     */
    public static function getInstance(): ?self
    {
        return self::$instance;
    }

    /**
     * Check if the application instance is set.
     *
     * @return bool True if the application instance is available.
     */
    public static function hasInstance(): bool
    {
        return self::$instance !== null;
    }

    /**
     * Get the application context (alias for getInstance).
     *
     * This method is used by packages like events and reactive to access
     * the current application context.
     *
     * @return self The application context.
     *
     * @throws \RuntimeException If no application instance is set.
     */
    public static function context(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException(
                'No application context available. ' .
                'Ensure HttpApplication::boot() or ConsoleKernel has been called.',
            );
        }

        return self::$instance;
    }

    /**
     * Get the DI container from the global instance.
     *
     * This is a convenience static method to access the container
     * without first calling context().
     *
     * @return ContainerInterface The DI container.
     *
     * @throws \RuntimeException If no application instance is set.
     */
    public static function container(): ContainerInterface
    {
        return self::context()->getContainer();
    }

    /**
     * Get the container instance.
     *
     * @return ContainerInterface The DI container.
     */
    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    /**
     * Get the event dispatcher.
     *
     * @return EventDispatcherInterface|null The event dispatcher, or null if not set.
     */
    public function events(): ?EventDispatcherInterface
    {
        return $this->events;
    }

    /**
     * Set the event dispatcher.
     *
     * @param EventDispatcherInterface|null $events The event dispatcher.
     * @return self
     */
    public function setEvents(?EventDispatcherInterface $events): self
    {
        $this->events = $events;
        return $this;
    }

    /**
     * Get the application base path.
     *
     * @return string|null The base path, or null if not set.
     */
    public function basePath(): ?string
    {
        return $this->basePath;
    }

    /**
     * Set the application base path.
     *
     * @param string|null $basePath The base path.
     * @return self
     */
    public function setBasePath(?string $basePath): self
    {
        $this->basePath = $basePath;
        return $this;
    }

    /**
     * Check if debug mode is enabled.
     *
     * @return bool True if debug mode is enabled.
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * Set debug mode.
     *
     * @param bool $debug Whether debug mode is enabled.
     * @return self
     */
    public function setDebug(bool $debug): self
    {
        $this->debug = $debug;
        return $this;
    }

    /**
     * Resolve a service from the container.
     *
     * Convenience method to resolve services directly from the application.
     *
     * @template T
     * @param class-string<T>|string $abstract The service to resolve.
     * @param array<string, mixed> $parameters Additional parameters.
     * @return T|mixed The resolved service.
     */
    public function resolve(string $abstract, array $parameters = []): mixed
    {
        return $this->container->resolve($abstract, $parameters);
    }

    /**
     * Clear the singleton instance.
     *
     * This is primarily useful for testing to reset the global state.
     *
     * @return void
     */
    public static function clearInstance(): void
    {
        self::$instance = null;
    }
}
