<?php

declare(strict_types=1);

namespace Lalaz\Container\Contracts;

use Lalaz\Container\MethodBindingBuilder;

/**
 * Interface for method binding (setter injection) capabilities.
 *
 * This interface follows the Interface Segregation Principle (ISP),
 * allowing consumers to depend only on method binding functionality.
 *
 *  * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
interface MethodBindingContainerInterface
{
    /**
     * Start defining method injections for a class.
     *
     * This initiates a fluent interface for configuring setter injection
     * or any method-based dependency injection.
     *
     * @param string $concrete The class to configure method injections for
     * @return \Lalaz\Container\MethodBindingBuilder
     *
     * @example
     * $container->when(UserController::class)
     *     ->method('setLogger')
     *     ->give(FileLogger::class);
     */
    public function when(string $concrete): \Lalaz\Container\MethodBindingBuilder;

    /**
     * Add a method binding for a class.
     *
     * @param string $concrete The class name
     * @param string $method The method name
     * @param mixed $implementation The concrete implementation
     * @return void
     * @internal Used by MethodBindingBuilder
     */
    public function addMethodBinding(string $concrete, string $method, mixed $implementation): void;

    /**
     * Get all method bindings for a class.
     *
     * @param string $concrete The class to get method bindings for
     * @return array<string, mixed> Map of method name to concrete implementation
     */
    public function getMethodBindings(string $concrete): array;

    /**
     * Check if a class has any method bindings.
     *
     * @param string $concrete The class to check
     * @return bool
     */
    public function hasMethodBindings(string $concrete): bool;
}
