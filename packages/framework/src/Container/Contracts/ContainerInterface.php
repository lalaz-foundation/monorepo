<?php

declare(strict_types=1);

namespace Lalaz\Container\Contracts;

use Psr\Container\ContainerInterface as PsrContainerInterface;

/**
 * Full Container Interface - PSR-11 compliant with all capabilities.
 *
 * This interface composes all segregated container interfaces for
 * backward compatibility. New code should prefer depending on the
 * specific interface that provides only the methods needed:
 *
 * - PsrContainerInterface: `get`, `has` (PSR-11)
 * - BindingContainerInterface: `bind`, `singleton`, `scoped`, `instance`, `alias`, `bound`
 * - ResolvingContainerInterface: `resolve`, `call`
 * - ScopedContainerInterface: `beginScope`, `endScope`, `inScope`
 * - FlushableContainerInterface: `flush`
 * - TaggableContainerInterface: `tag`, `tagged`, `getTaggedAbstracts`, `hasTag`
 * - MethodBindingContainerInterface: `when`, `getMethodBindings`, `hasMethodBindings`
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 */
interface ContainerInterface extends
    PsrContainerInterface,
    BindingContainerInterface,
    ResolvingContainerInterface,
    ScopedContainerInterface,
    FlushableContainerInterface,
    TaggableContainerInterface,
    MethodBindingContainerInterface
{
    /**
     * Check if the container has a given binding.
     * Alias for bound() to satisfy PSR-11.
     *
     * @param string $id The service identifier
     * @return bool
     */
    public function has(string $id): bool;

    /**
     * Resolve a service from the container.
     * Alias for resolve() to satisfy PSR-11.
     *
     * @param string $id The service identifier
     * @return mixed
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function get(string $id): mixed;
}
