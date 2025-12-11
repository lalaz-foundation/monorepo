<?php

declare(strict_types=1);

namespace Lalaz\Container\Contracts;

/**
 * Interface for container flush operations.
 *
 * Provides method for clearing all bindings and instances.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 */
interface FlushableContainerInterface
{
    /**
     * Flush all bindings and resolved instances.
     *
     * @return void
     */
    public function flush(): void;
}
