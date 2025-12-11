<?php

declare(strict_types=1);

namespace Lalaz\Container;

use Lalaz\Container\Contracts\TaggableContainerInterface;

/**
 * Fluent binding configuration for container registrations.
 *
 * Provides a chainable API for configuring bindings with tags and other options.
 *
 * @example
 * ```php
 * $container->bind(FileLogger::class)
 *     ->tag('loggers')
 *     ->tag('file-handlers');
 *
 * $container->singleton(CacheStore::class)
 *     ->tag(['cache', 'stores']);
 * ```
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 */
class BindingConfiguration
{
    /**
     * @param TaggableContainerInterface $container The container instance
     * @param string $abstract The abstract type being configured
     */
    public function __construct(
        private TaggableContainerInterface $container,
        private string $abstract,
    ) {
    }

    /**
     * Assign one or more tags to this binding.
     *
     * @param string|array<string> $tags Tag or array of tags
     * @return self For method chaining
     */
    public function tag(string|array $tags): self
    {
        $this->container->tag($this->abstract, $tags);
        return $this;
    }

    /**
     * Get the abstract type being configured.
     *
     * @return string
     */
    public function getAbstract(): string
    {
        return $this->abstract;
    }
}
