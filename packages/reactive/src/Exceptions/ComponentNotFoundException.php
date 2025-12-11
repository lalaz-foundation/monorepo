<?php

declare(strict_types=1);

namespace Lalaz\Reactive\Exceptions;

/**
 * Exception thrown when a component class is not found
 *
 * @package lalaz/reactive
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
class ComponentNotFoundException extends ReactiveException
{
    public function __construct(string $componentName)
    {
        parent::__construct("Component class not found: {$componentName}");
    }
}
