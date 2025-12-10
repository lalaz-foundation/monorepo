<?php

declare(strict_types=1);

namespace Lalaz\Container;

use Lalaz\Container\Contracts\MethodBindingContainerInterface;
use Lalaz\Exceptions\ConfigurationException;

/**
 * Fluent builder for method binding configuration.
 *
 * Provides a clean API for defining setter injection and method-based
 * dependency injection in the container.
 *
 *  * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 *
 * @example
 * $container->when(UserController::class)
 *     ->method('setLogger')
 *     ->give(FileLogger::class);
 *
 * // Multiple method injections
 * $container->when(UserController::class)
 *     ->method('setLogger')->give(FileLogger::class)
 *     ->method('setCache')->give(RedisCache::class);
 *
 * // With closure
 * $container->when(UserController::class)
 *     ->method('configure')
 *     ->give(fn($container) => ['debug' => true]);
 */
class MethodBindingBuilder
{
    /**
     * The class being configured.
     */
    private string $concrete;

    /**
     * The container instance.
     */
    private MethodBindingContainerInterface $container;

    /**
     * The current method being configured.
     */
    private ?string $currentMethod = null;

    /**
     * Create a new method binding builder.
     *
     * @param MethodBindingContainerInterface $container
     * @param string $concrete The class to configure
     */
    public function __construct(MethodBindingContainerInterface $container, string $concrete)
    {
        $this->container = $container;
        $this->concrete = $concrete;
    }

    /**
     * Specify which method to inject dependencies into.
     *
     * @param string $method The method name
     * @return self
     */
    public function method(string $method): self
    {
        $this->currentMethod = $method;
        return $this;
    }

    /**
     * Alias for method() - Laravel-style API.
     *
     * @param string $method The method name
     * @return self
     */
    public function needs(string $method): self
    {
        return $this->method($method);
    }

    /**
     * Specify what to inject into the method.
     *
     * @param mixed $concrete The concrete implementation, class name, or closure
     * @return self
     * @throws ConfigurationException If no method has been specified
     */
    public function give(mixed $concrete): self
    {
        if ($this->currentMethod === null) {
            throw new ConfigurationException(
                'You must call method() before give() to specify which method to inject into.',
                ['hint' => 'Use ->when(Class::class)->method("methodName")->give($value)'],
            );
        }

        // Store the binding in the container
        $this->storeBinding($this->currentMethod, $concrete);

        // Reset current method for chaining
        $this->currentMethod = null;

        return $this;
    }

    /**
     * Alias for give() - specify the implementation.
     *
     * @param mixed $concrete The concrete implementation
     * @return self
     */
    public function inject(mixed $concrete): self
    {
        return $this->give($concrete);
    }

    /**
     * Store the method binding in the container.
     *
     * @param string $method
     * @param mixed $concrete
     * @return void
     */
    private function storeBinding(string $method, mixed $concrete): void
    {
        $this->container->addMethodBinding($this->concrete, $method, $concrete);
    }

    /**
     * Get the class being configured.
     *
     * @return string
     */
    public function getConcrete(): string
    {
        return $this->concrete;
    }
}
