<?php

declare(strict_types=1);

namespace Lalaz\Reactive;

use Lalaz\Container\Contracts\ContainerInterface;
use Lalaz\Reactive\Exceptions\ComponentNotFoundException;

/**
 * ReactiveManager - Manages component lifecycle and rendering
 *
 * @package lalaz/reactive
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
class ReactiveManager
{
    /**
     * Component registry
     */
    private ComponentRegistry $registry;

    /**
     * DI container used to resolve components.
     */
    private ContainerInterface $container;

    /**
     * Component namespace (where to find components)
     */
    private string $namespace = 'App\\Reactive\\';

    public function __construct(
        ComponentRegistry $registry,
        ContainerInterface $container,
    ) {
        $this->registry = $registry;
        $this->container = $container;
    }

    /**
     * Create and mount a component
     *
     * @param string $name Component name
     * @param array $params Mount parameters
     * @return ReactiveComponent Component instance
     */
    public function mount(string $name, array $params = []): ReactiveComponent
    {
        $class = $this->resolveComponentClass($name);

        if (!class_exists($class)) {
            throw new ComponentNotFoundException($name);
        }

        $component = $this->container->resolve($class);

        if (!$component instanceof ReactiveComponent) {
            throw new ComponentNotFoundException($name);
        }

        // Generate unique ID
        $id = $this->generateId($name);
        $component->setId($id);
        $component->setName($name);
        $component->setMountParams($params);

        // Mount component with params
        $component->mount(...$params);

        // Register component
        $this->registry->register($id, $component);

        return $component;
    }

    /**
     * Recreate a component from dehydrated state (postback).
     *
     * @param string $name
     * @param array $state
     * @return ReactiveComponent
     */
    public function restore(string $name, array $state): ReactiveComponent
    {
        $class = $this->resolveComponentClass($name);

        if (!class_exists($class)) {
            throw new ComponentNotFoundException($name);
        }

        $component = $this->container->resolve($class);

        if (!$component instanceof ReactiveComponent) {
            throw new ComponentNotFoundException($name);
        }

        $componentId = $state['id'] ?? $this->generateId($name);
        $mountParams = $state['mount'] ?? [];

        $component->setId($componentId);
        $component->setName($state['name'] ?? $name);
        $component->setMountParams($mountParams);

        // Re-run mount to restore listeners and initial wiring before hydrating state
        $component->mount(...$mountParams);
        $component->hydrate($state);

        $this->registry->register($componentId, $component);

        return $component;
    }

    /**
     * Render a component to HTML
     *
     * @param ReactiveComponent $component Component instance
     * @return string HTML output
     */
    public function render(ReactiveComponent $component): string
    {
        $html = $component->render();

        // Wrap in reactive component container
        return $this->wrapInContainer($component, $html);
    }

    /**
     * Call a method on a component
     *
     * @param string $id Component ID
     * @param string $method Method name
     * @param array $params Method parameters
     * @return ReactiveComponent Updated component
     */
    public function call(
        string $id,
        string $method,
        array $params = [],
    ): ReactiveComponent {
        $component = $this->registry->get($id);

        if (!$component) {
            throw new \Exception("Component not found: {$id}");
        }

        if (!method_exists($component, $method)) {
            throw new \Exception("Method not found: {$method}");
        }

        // Call method
        $component->$method(...$params);

        return $component;
    }

    /**
     * Update a component property
     *
     * @param string $id Component ID
     * @param string $property Property name
     * @param mixed $value New value
     * @return ReactiveComponent Updated component
     */
    public function updateProperty(
        string $id,
        string $property,
        mixed $value,
    ): ReactiveComponent {
        $component = $this->registry->get($id);

        if (!$component) {
            throw new \Exception("Component not found: {$id}");
        }

        $component->setProperty($property, $value);

        return $component;
    }

    /**
     * Resolve component class name from component name
     *
     * @param string $name Component name (kebab-case, PascalCase, or full class name)
     * @return string Full class name
     */
    private function resolveComponentClass(string $name): string
    {
        // If it's already a fully qualified class name (contains backslash), return as-is
        if (str_contains($name, '\\')) {
            return $name;
        }

        // Convert kebab-case to PascalCase
        $pascalCase = str_replace('-', '', ucwords($name, '-'));

        return $this->namespace . $pascalCase;
    }

    /**
     * Generate unique component ID
     *
     * @param string $name Component name
     * @return string Unique ID
     */
    private function generateId(string $name): string
    {
        return $name . '-' . uniqid();
    }

    /**
     * Wrap component HTML in container with metadata
     *
     * @param ReactiveComponent $component Component instance
     * @param string $html Component HTML
     * @return string Wrapped HTML
     */
    private function wrapInContainer(
        ReactiveComponent $component,
        string $html,
    ): string {
        $state = $component->dehydrate();

        $attributes = [
            'reactive:id' => $state['id'],
            'reactive:name' => $state['name'],
        ];

        if (!empty($state['listeners'])) {
            $attributes['reactive:listeners'] = json_encode($state['listeners']);
        }

        // Add serialized state for client-side tracking
        if (!empty($state['properties'])) {
            $attributes['reactive:state'] = json_encode($state['properties']);
        }

        if (!empty($state['mount'])) {
            $attributes['reactive:params'] = json_encode($state['mount']);
        }

        $attrString = '';
        foreach ($attributes as $key => $value) {
            $attrString .= sprintf(
                ' %s="%s"',
                $key,
                htmlspecialchars($value, ENT_QUOTES),
            );
        }

        return sprintf('<div%s>%s</div>', $attrString, $html);
    }

    /**
     * Set component namespace
     *
     * @param string $namespace Namespace
     * @return void
     */
    public function setNamespace(string $namespace): void
    {
        $this->namespace = rtrim($namespace, '\\') . '\\';
    }
}
