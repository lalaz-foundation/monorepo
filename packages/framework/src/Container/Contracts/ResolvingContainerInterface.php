<?php

declare(strict_types=1);

namespace Lalaz\Container\Contracts;

/**
 * Interface for container resolution operations.
 *
 * Provides methods for resolving services and calling
 * callables with dependency injection.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 */
interface ResolvingContainerInterface
{
    /**
     * Resolve a service from the container.
     *
     * @param string $abstract The service identifier
     * @param array<string, mixed> $parameters Additional parameters for instantiation
     * @return mixed The resolved instance
     * @throws \Lalaz\Container\Exceptions\ContainerException
     */
    public function resolve(string $abstract, array $parameters = []): mixed;

    /**
     * Call a callback with dependency injection.
     *
     * @param callable $callback The callback to call
     * @param array<string, mixed> $parameters Additional parameters
     * @return mixed
     */
    public function call(callable $callback, array $parameters = []): mixed;
}
