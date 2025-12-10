<?php

declare(strict_types=1);

namespace Lalaz\Web\View\Components;

/**
 * Base class for view components.
 *
 * Components are reusable UI elements with optional PHP logic.
 * Extend this class to create components with computed data,
 * validation, or complex rendering logic.
 *
 * @example
 * ```php
 * class Alert extends Component
 * {
 *     public function __construct(
 *         public string $type = 'info',
 *         public string $message = ''
 *     ) {}
 *
 *     public function data(): array
 *     {
 *         return [
 *             'icon' => $this->getIcon(),
 *             'cssClass' => "alert-{$this->type}",
 *         ];
 *     }
 * }
 * ```
 *
 * @package Lalaz\Web\View\Components
 */
abstract class Component
{
    /**
     * Get the view template name for this component.
     *
     * Override this method to use a custom template path.
     * By default, derives from class name: Alert -> alert
     *
     * @return string|null Template name or null for auto-discovery
     */
    public function template(): ?string
    {
        return null;
    }

    /**
     * Get additional data to pass to the view.
     *
     * This method is called during rendering and its return
     * value is merged with the component's public properties.
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return [];
    }

    /**
     * Determine if the component should be rendered.
     *
     * Return false to skip rendering entirely.
     *
     * @return bool
     */
    public function shouldRender(): bool
    {
        return true;
    }

    /**
     * Get all public properties as an array.
     *
     * @return array<string, mixed>
     */
    public function getPublicProperties(): array
    {
        $reflection = new \ReflectionClass($this);
        $properties = [];

        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if (!$property->isStatic()) {
                $name = $property->getName();
                $properties[$name] = $this->{$name};
            }
        }

        return $properties;
    }

    /**
     * Get all data for rendering (properties + computed data).
     *
     * @return array<string, mixed>
     */
    public function resolveData(): array
    {
        return array_merge(
            $this->getPublicProperties(),
            $this->data()
        );
    }
}
