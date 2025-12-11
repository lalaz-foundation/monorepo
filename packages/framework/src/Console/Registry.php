<?php

declare(strict_types=1);

namespace Lalaz\Console;

use Lalaz\Console\Contracts\CommandInterface;
use Lalaz\Container\Container;
use Lalaz\Container\Contracts\ContainerInterface;

/**
 * Command registry for managing CLI commands.
 *
 * This class maintains a registry of all available console commands
 * and provides resolution capabilities through both direct registration
 * and container-based lazy loading.
 *
 * Example usage:
 * ```php
 * $registry = new Registry($container);
 * $registry->add(new MigrateCommand());
 *
 * // Resolve by name
 * $command = $registry->resolve('migrate');
 *
 * // Get all commands
 * $commands = $registry->all();
 * ```
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
final class Registry
{
    /**
     * Registered commands indexed by name.
     *
     * @var array<string, CommandInterface>
     */
    private array $commands = [];

    /**
     * The dependency injection container.
     *
     * @var ContainerInterface
     */
    private ContainerInterface $container;

    /**
     * Creates a new command registry instance.
     *
     * @param ContainerInterface|null $container The container for lazy-loading commands
     */
    public function __construct(?ContainerInterface $container = null)
    {
        $this->container = $container ?? new Container();
    }

    /**
     * Adds a command to the registry.
     *
     * The command is indexed by its name for fast lookup.
     *
     * @param CommandInterface $command The command to register
     *
     * @return void
     */
    public function add(CommandInterface $command): void
    {
        $this->commands[$command->name()] = $command;
    }

    /**
     * Resolves a command by name.
     *
     * First checks the registered commands, then attempts to resolve
     * from the container if the command is bound there.
     *
     * @param string $name The command name to resolve
     *
     * @return CommandInterface|null The resolved command or null if not found
     */
    public function resolve(string $name): ?CommandInterface
    {
        if (isset($this->commands[$name])) {
            return $this->commands[$name];
        }

        if ($this->container->bound($name)) {
            /** @var CommandInterface $command */
            $command = $this->container->resolve($name);
            $this->add($command);
            return $command;
        }

        return null;
    }

    /**
     * Gets the dependency injection container.
     *
     * @return ContainerInterface The container instance
     */
    public function container(): ContainerInterface
    {
        return $this->container;
    }

    /**
     * Gets all registered commands sorted by name.
     *
     * @return array<int, CommandInterface> Array of command instances
     */
    public function all(): array
    {
        $commands = array_values($this->commands);
        usort($commands, fn ($a, $b) => $a->name() <=> $b->name());
        return $commands;
    }
}
