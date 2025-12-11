<?php

declare(strict_types=1);

namespace Lalaz\Container\Exceptions;

use Psr\Container\NotFoundExceptionInterface;
use Throwable;

/**
 * Exception thrown when a service is not found in the container.
 *
 * This exception is PSR-11 compliant and extends ContainerException
 * to provide contextual information for debugging.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 */
class NotFoundException extends ContainerException implements NotFoundExceptionInterface
{
    /**
     * Create exception for a missing service.
     *
     * @param string $id The service identifier that was not found
     * @param Throwable|null $previous Previous exception
     * @return self
     */
    public static function forService(string $id, ?Throwable $previous = null): self
    {
        $exception = new self("Service '{$id}' not found in the container.", 0, $previous);
        $exception->withContext(['id' => $id]);
        return $exception;
    }

    /**
     * Create exception for a missing class.
     *
     * @param string $class The class that was not found
     * @param Throwable|null $previous Previous exception
     * @return self
     */
    public static function forClass(string $class, ?Throwable $previous = null): self
    {
        $exception = new self("Class '{$class}' does not exist.", 0, $previous);
        $exception->withContext(['class' => $class]);
        return $exception;
    }
}
