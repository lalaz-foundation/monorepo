<?php

declare(strict_types=1);

namespace Lalaz\Reactive;

/**
 * ComponentRegistry - Stores component instances during request
 *
 * @package lalaz/reactive
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
class ComponentRegistry
{
    /**
     * Registered components
     *
     * @var array<string, ReactiveComponent>
     */
    private array $components = [];

    /**
     * Register a component
     *
     * @param string $id Component ID
     * @param ReactiveComponent $component Component instance
     * @return void
     */
    public function register(string $id, ReactiveComponent $component): void
    {
        $this->components[$id] = $component;
    }

    /**
     * Get a component by ID
     *
     * @param string $id Component ID
     * @return ReactiveComponent|null Component instance or null
     */
    public function get(string $id): ?ReactiveComponent
    {
        return $this->components[$id] ?? null;
    }

    /**
     * Check if component is registered
     *
     * @param string $id Component ID
     * @return bool
     */
    public function has(string $id): bool
    {
        return isset($this->components[$id]);
    }

    /**
     * Remove a component
     *
     * @param string $id Component ID
     * @return void
     */
    public function remove(string $id): void
    {
        unset($this->components[$id]);
    }

    /**
     * Get all registered components
     *
     * @return array<string, ReactiveComponent>
     */
    public function all(): array
    {
        return $this->components;
    }

    /**
     * Clear all components
     *
     * @return void
     */
    public function clear(): void
    {
        $this->components = [];
    }
}
