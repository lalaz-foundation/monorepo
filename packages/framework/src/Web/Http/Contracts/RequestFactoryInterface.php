<?php

declare(strict_types=1);

namespace Lalaz\Web\Http\Contracts;

/**
 * Builds framework requests from the underlying runtime environment.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 */
interface RequestFactoryInterface
{
    /**
     * Creates a Request from PHP superglobals (or equivalent runtime data).
     *
     * @return RequestInterface
     */
    public function fromGlobals(): RequestInterface;
}
