<?php

declare(strict_types=1);

namespace Lalaz\Reactive\Exceptions;

/**
 * Exception thrown when trying to call a non-public method
 *
 * @package lalaz/reactive
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
class MethodNotAccessibleException extends ReactiveException
{
    public function __construct(string $method)
    {
        parent::__construct("Method not accessible: {$method}");
    }
}
