<?php

declare(strict_types=1);

namespace Lalaz\Container;

use Lalaz\Console\Contracts\CommandInterface;
use Lalaz\Console\Registry;
use Lalaz\Container\Contracts\ContainerInterface;
use Lalaz\Exceptions\ConfigurationException;

/**
 * Base service provider for registering and booting container bindings.
 *
 * Service providers are the central place to configure application services.
 * They allow you to bind classes into the container, register singletons,
 * aliases, and console commands. All service providers extend this base class.
 *
 * Example usage:
 * ```php
 * class DatabaseServiceProvider extends ServiceProvider
 * {
 *     public function register(): void
 *     {
 *         $this->singleton(Connection::class, function ($container) {
 *             return new Connection(config('database'));
 *         });
 *
 *         $this->alias(Connection::class, 'db');
 *     }
 *
 *     public function boot(): void
 *     {
 *         $this->commands(MigrateCommand::class);
 *     }
 * }
 * ```
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
abstract class ServiceProvider
{
    /**
     * The dependency injection container.
     *
     * @var ContainerInterface
     */
    protected ContainerInterface $container;

    /**
     * Pending commands to register when Registry becomes available.
     *
     * @var array<int, CommandInterface|string>
     */
    protected array $pendingCommands = [];

    /**
     * Creates a new service provider instance.
     *
     * @param ContainerInterface $container The container to register bindings with
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Registers bindings with the container.
     *
     * This method is called during application bootstrap before any
     * services are resolved. Use it to bind interfaces to implementations.
     *
     * @return void
     */
    abstract public function register(): void;

    /**
     * Boots the service provider after registration.
     *
     * This method is called after all providers have been registered.
     * Use it for any setup that requires other services to be available,
     * such as registering console commands or event listeners.
     *
     * @return void
     */
    public function boot(): void
    {
        // Optional - override if needed
    }

    /**
     * Registers a binding with the container.
     *
     * Each time the binding is resolved, a new instance will be created.
     *
     * @param string $abstract The abstract type or interface name
     * @param mixed  $concrete The concrete implementation (class name or factory)
     *
     * @return void
     */
    protected function bind(string $abstract, mixed $concrete = null): void
    {
        $this->container->bind($abstract, $concrete);
    }

    /**
     * Registers a singleton binding with the container.
     *
     * The binding will only be resolved once and the same instance
     * will be returned on subsequent resolutions.
     *
     * @param string $abstract The abstract type or interface name
     * @param mixed  $concrete The concrete implementation (class name or factory)
     *
     * @return void
     */
    protected function singleton(string $abstract, mixed $concrete = null): void
    {
        $this->container->singleton($abstract, $concrete);
    }

    /**
     * Registers a scoped binding with the container.
     *
     * The binding will be resolved once per request/scope and the same
     * instance will be returned within that scope.
     *
     * @param string $abstract The abstract type or interface name
     * @param mixed  $concrete The concrete implementation (class name or factory)
     *
     * @return void
     */
    protected function scoped(string $abstract, mixed $concrete = null): void
    {
        $this->container->scoped($abstract, $concrete);
    }

    /**
     * Registers an existing instance with the container.
     *
     * The provided instance will be returned every time the binding is resolved.
     *
     * @param string $abstract The abstract type or interface name
     * @param mixed  $instance The instance to bind
     *
     * @return void
     */
    protected function instance(string $abstract, mixed $instance): void
    {
        $this->container->instance($abstract, $instance);
    }

    /**
     * Creates an alias for an abstract type.
     *
     * @param string $abstract The original abstract type
     * @param string $alias    The alias name
     *
     * @return void
     */
    protected function alias(string $abstract, string $alias): void
    {
        $this->container->alias($abstract, $alias);
    }

    /**
     * Registers console commands provided by this service provider.
     *
     * Commands can be passed as instances, class names, or arrays.
     * Class names will be resolved from the container.
     *
     * Example:
     * ```php
     * $this->commands(
     *     MigrateCommand::class,
     *     new SeedCommand(),
     *     [RollbackCommand::class, StatusCommand::class]
     * );
     * ```
     *
     * @param CommandInterface|string|array<int, CommandInterface|string> ...$commands Commands to register
     *
     * @return void
     *
     * @throws ConfigurationException If a command does not implement CommandInterface
     */
    protected function commands(
        CommandInterface|string|array ...$commands,
    ): void {
        // Flatten arrays first
        $flatCommands = [];
        foreach ($commands as $command) {
            if (is_array($command)) {
                foreach ($command as $nested) {
                    $flatCommands[] = $nested;
                }
            } else {
                $flatCommands[] = $command;
            }
        }

        // If Registry is not yet available, store commands for later
        if (!$this->container->bound(Registry::class)) {
            $this->pendingCommands = array_merge($this->pendingCommands, $flatCommands);
            return;
        }

        /** @var Registry $registry */
        $registry = $this->container->resolve(Registry::class);

        foreach ($flatCommands as $command) {
            $instance = $command;
            if (is_string($command)) {
                $instance = $this->container->resolve($command);
            }

            if (!$instance instanceof CommandInterface) {
                throw ConfigurationException::invalidValue(
                    'command',
                    $command,
                    CommandInterface::class . ' implementation',
                );
            }

            $registry->add($instance);
        }
    }

    /**
     * Registers any pending console commands.
     *
     * Called by ConsoleKernel after Registry is bound to container.
     * This allows commands declared in register() to be registered
     * even when the Registry wasn't available at registration time.
     *
     * @return void
     */
    public function bootCommands(): void
    {
        if (empty($this->pendingCommands)) {
            return;
        }

        $pending = $this->pendingCommands;
        $this->pendingCommands = [];

        $this->commands(...$pending);
    }
}
