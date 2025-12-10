<?php

declare(strict_types=1);

namespace Lalaz\Events;

use Lalaz\Events\Contracts\ListenerResolverInterface;

/**
 * Default listener resolver implementation.
 *
 * Following DIP - this provides a concrete implementation that
 * can be replaced with container-based resolution.
 */
class ListenerResolver implements ListenerResolverInterface
{
    /**
     * Optional callable for custom resolution.
     *
     * @var callable|null
     */
    private $customResolver;

    /**
     * Create a new resolver instance.
     *
     * @param callable|null $customResolver Optional custom resolver fn(string $class): object
     */
    public function __construct(?callable $customResolver = null)
    {
        $this->customResolver = $customResolver;
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(string $listenerClass): object
    {
        // 1. Use custom resolver if available
        if ($this->customResolver !== null) {
            return ($this->customResolver)($listenerClass);
        }

        // 2. Fallback to container if available
        if (function_exists('resolve')) {
            return resolve($listenerClass);
        }

        // 3. Direct instantiation
        return new $listenerClass();
    }

    /**
     * Create a resolver from a callable.
     *
     * @param callable $resolver fn(string $class): object
     */
    public static function from(callable $resolver): self
    {
        return new self($resolver);
    }

    /**
     * Create a resolver that always uses direct instantiation.
     */
    public static function direct(): self
    {
        return new self(fn (string $class) => new $class());
    }
}
