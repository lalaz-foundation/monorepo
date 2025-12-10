<?php

declare(strict_types=1);

namespace Lalaz\Events\Contracts;

/**
 * Interface for resolving listener class instances.
 *
 * Following DIP - event drivers depend on this abstraction
 * instead of directly using container or instantiation.
 */
interface ListenerResolverInterface
{
    /**
     * Resolve a listener class to an instance.
     *
     * @param string $listenerClass The fully qualified class name
     * @return object The resolved instance
     */
    public function resolve(string $listenerClass): object;
}
