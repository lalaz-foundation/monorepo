<?php

declare(strict_types=1);

namespace Lalaz\Reactive\Exceptions;

/**
 * Exception thrown when trying to access a non-public property
 *
 * @package lalaz/reactive
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
class PropertyNotAccessibleException extends ReactiveException
{
    public function __construct(string $property)
    {
        parent::__construct("Property not accessible: {$property}");
    }
}
