<?php

declare(strict_types=1);

namespace Lalaz\Container\Exceptions;

use Lalaz\Exceptions\FrameworkException;
use Psr\Container\ContainerExceptionInterface;
use Throwable;

/**
 * General container exception.
 *
 * This exception is PSR-11 compliant and extends FrameworkException
 * to provide contextual information for debugging.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 */
class ContainerException extends FrameworkException implements ContainerExceptionInterface
{
    /**
     * Constructor compatible with standard Exception signature.
     *
     * @param string $message The exception message
     * @param int $code The exception code (ignored, kept for compatibility)
     * @param Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, [], $previous);
    }

    /**
     * Create exception with additional context.
     *
     * @param string $message The exception message
     * @param array<string, mixed> $context Additional context
     * @param Throwable|null $previous Previous exception
     * @return self
     */
    public static function create(string $message, array $context = [], ?Throwable $previous = null): self
    {
        $exception = new self($message, 0, $previous);
        $exception->withContext($context);
        return $exception;
    }

    /**
     * Create exception for binding errors.
     *
     * @param string $id The service identifier
     * @param string|null $reason The reason for failure
     * @param Throwable|null $previous Previous exception
     * @return self
     */
    public static function bindingError(string $id, ?string $reason = null, ?Throwable $previous = null): self
    {
        $message = "Failed to bind service '{$id}'.";

        if ($reason !== null) {
            $message .= " {$reason}";
        }

        return self::create($message, ['id' => $id, 'reason' => $reason], $previous);
    }

    /**
     * Create exception for resolution errors.
     *
     * @param string $id The service identifier
     * @param string|null $reason The reason for failure
     * @param Throwable|null $previous Previous exception
     * @return self
     */
    public static function resolutionError(string $id, ?string $reason = null, ?Throwable $previous = null): self
    {
        $message = "Failed to resolve service '{$id}'.";

        if ($reason !== null) {
            $message .= " {$reason}";
        }

        return self::create($message, ['id' => $id, 'reason' => $reason], $previous);
    }

    /**
     * Create exception for circular dependency detection.
     *
     * @param string $id The service identifier
     * @param array<string> $chain The dependency chain
     * @return self
     */
    public static function circularDependency(string $id, array $chain = []): self
    {
        return self::create(
            "Circular dependency detected while resolving '{$id}'.",
            ['id' => $id, 'dependency_chain' => $chain],
        );
    }
}
