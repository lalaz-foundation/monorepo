<?php

declare(strict_types=1);

namespace Lalaz\Reactive\Tests\Fixtures\Mocks;

use Lalaz\Container\Contracts\ContainerInterface;
use Lalaz\Container\MethodBindingBuilder;

/**
 * Mock container for testing purposes.
 * Implements ContainerInterface with minimal functionality.
 */
class MockContainer implements ContainerInterface
{
    private array $bindings = [];
    private array $instances = [];
    private array $aliases = [];
    private array $tags = [];

    public function bind(string $abstract, mixed $concrete = null): void
    {
        $this->bindings[$abstract] = $concrete ?? $abstract;
    }

    public function singleton(string $abstract, mixed $concrete = null): void
    {
        $this->bindings[$abstract] = $concrete ?? $abstract;
    }

    public function scoped(string $abstract, mixed $concrete = null): void
    {
        $this->bindings[$abstract] = $concrete ?? $abstract;
    }

    public function instance(string $abstract, mixed $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    public function alias(string $abstract, string $alias): void
    {
        $this->aliases[$alias] = $abstract;
    }

    public function bound(string $abstract): bool
    {
        return isset($this->bindings[$abstract])
            || isset($this->instances[$abstract])
            || isset($this->aliases[$abstract]);
    }

    public function resolve(string $abstract, array $parameters = []): mixed
    {
        // Check aliases first
        if (isset($this->aliases[$abstract])) {
            $abstract = $this->aliases[$abstract];
        }

        // Check instances
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Check bindings
        if (isset($this->bindings[$abstract])) {
            $concrete = $this->bindings[$abstract];
            if (is_callable($concrete)) {
                return $concrete($this, $parameters);
            }
            if (is_string($concrete) && class_exists($concrete)) {
                return new $concrete();
            }
            return $concrete;
        }

        // Try to auto-resolve
        if (class_exists($abstract)) {
            return new $abstract();
        }

        throw new \Exception("Cannot resolve: {$abstract}");
    }

    public function call(callable|array|string $callback, array $parameters = []): mixed
    {
        if (is_callable($callback)) {
            return $callback(...$parameters);
        }
        return null;
    }

    public function has(string $id): bool
    {
        return $this->bound($id) || class_exists($id);
    }

    public function get(string $id): mixed
    {
        return $this->resolve($id);
    }

    public function beginScope(): void
    {
    }

    public function endScope(): void
    {
    }

    public function inScope(): bool
    {
        return false;
    }

    public function flush(): void
    {
        $this->bindings = [];
        $this->instances = [];
        $this->aliases = [];
        $this->tags = [];
    }

    public function tag(string $abstract, string|array $tags): void
    {
        $tags = is_array($tags) ? $tags : [$tags];
        foreach ($tags as $tag) {
            $this->tags[$tag][] = $abstract;
        }
    }

    public function tagged(string $tag): array
    {
        $abstracts = $this->tags[$tag] ?? [];
        return array_map(fn($abstract) => $this->resolve($abstract), $abstracts);
    }

    public function getTaggedAbstracts(string $tag): array
    {
        return $this->tags[$tag] ?? [];
    }

    public function hasTag(string $tag): bool
    {
        return isset($this->tags[$tag]) && !empty($this->tags[$tag]);
    }

    public function when(string $concrete): MethodBindingBuilder
    {
        return new MethodBindingBuilder($this, $concrete);
    }

    public function addMethodBinding(string $concrete, string $method, mixed $implementation): void
    {
    }

    public function getMethodBindings(string $concrete): array
    {
        return [];
    }

    public function hasMethodBindings(string $concrete): bool
    {
        return false;
    }
}
