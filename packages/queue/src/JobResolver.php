<?php

declare(strict_types=1);

namespace Lalaz\Queue;

use Lalaz\Queue\Contracts\JobInterface;
use Lalaz\Queue\Contracts\JobResolverInterface;

/**
 * Default job resolver implementation.
 *
 * Following DIP - this provides a concrete implementation that
 * can be replaced with container-based resolution.
 */
class JobResolver implements JobResolverInterface
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
     * @param callable|null $customResolver Optional custom resolver fn(string $class): JobInterface
     */
    public function __construct(?callable $customResolver = null)
    {
        $this->customResolver = $customResolver;
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(string $jobClass): JobInterface
    {
        if (!class_exists($jobClass)) {
            throw new \RuntimeException("Job class '{$jobClass}' does not exist");
        }

        // 1. Use custom resolver if available
        if ($this->customResolver !== null) {
            $instance = ($this->customResolver)($jobClass);
        }
        // 2. Fallback to container if available
        elseif (function_exists('resolve')) {
            $instance = resolve($jobClass);
        }
        // 3. Direct instantiation
        else {
            $instance = new $jobClass();
        }

        if (!($instance instanceof JobInterface)) {
            throw new \RuntimeException(
                "Job class '{$jobClass}' must implement JobInterface"
            );
        }

        return $instance;
    }

    /**
     * Create a resolver from a callable.
     *
     * @param callable $resolver fn(string $class): JobInterface
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
