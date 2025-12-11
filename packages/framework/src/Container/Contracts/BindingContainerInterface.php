<?php

declare(strict_types=1);

namespace Lalaz\Container\Contracts;

/**
 * Interface for container binding operations.
 *
 * Provides methods for registering services, singletons,
 * scoped bindings, instances and aliases.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 */
interface BindingContainerInterface
{
    /**
     * Bind a service to the container.
     *
     * @param string $abstract The interface or class name
     * @param mixed $concrete The implementation (class name, closure, or instance)
     * @return void
     */
    public function bind(string $abstract, mixed $concrete = null): void;

    /**
     * Bind a service as a singleton.
     *
     * @param string $abstract The interface or class name
     * @param mixed $concrete The implementation (class name, closure, or instance)
     * @return void
     */
    public function singleton(string $abstract, mixed $concrete = null): void;

    /**
     * Bind a service as scoped (one instance per scope/request).
     *
     * @param string $abstract
     * @param mixed $concrete
     * @return void
     */
    public function scoped(string $abstract, mixed $concrete = null): void;

    /**
     * Register an existing instance as shared.
     *
     * @param string $abstract The service identifier
     * @param mixed $instance The instance to register
     * @return void
     */
    public function instance(string $abstract, mixed $instance): void;

    /**
     * Alias a type to a different name.
     *
     * @param string $abstract The original name
     * @param string $alias The alias name
     * @return void
     */
    public function alias(string $abstract, string $alias): void;

    /**
     * Determine if a given type has been bound.
     *
     * @param string $abstract The service identifier
     * @return bool
     */
    public function bound(string $abstract): bool;
}
