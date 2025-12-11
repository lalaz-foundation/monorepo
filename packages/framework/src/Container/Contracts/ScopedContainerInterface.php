<?php

declare(strict_types=1);

namespace Lalaz\Container\Contracts;

/**
 * Interface for scoped container operations.
 *
 * Provides methods for managing request-scoped bindings
 * that are instantiated once per scope lifecycle.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 */
interface ScopedContainerInterface
{
    /**
     * Begin a scoped resolution context (e.g., per HTTP request).
     *
     * @return void
     */
    public function beginScope(): void;

    /**
     * End the current scoped context.
     *
     * @return void
     */
    public function endScope(): void;

    /**
     * Determine if the container is currently inside a scope.
     *
     * @return bool
     */
    public function inScope(): bool;
}
