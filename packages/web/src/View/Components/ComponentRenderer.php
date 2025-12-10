<?php

declare(strict_types=1);

namespace Lalaz\Web\View\Components;

use Lalaz\Web\View\Contracts\TemplateEngineInterface;

/**
 * Renders view components.
 *
 * Handles the full lifecycle of component rendering:
 * - Resolving component class (if exists)
 * - Instantiating with props
 * - Merging computed data
 * - Rendering the template
 *
 * @package Lalaz\Web\View\Components
 */
class ComponentRenderer
{
    private ComponentRegistry $registry;
    private ?TemplateEngineInterface $engine;

    /**
     * Singleton instance.
     */
    private static ?self $instance = null;

    /**
     * Create a new component renderer.
     *
     * @param ComponentRegistry|null $registry
     * @param TemplateEngineInterface|null $engine
     */
    public function __construct(
        ?ComponentRegistry $registry = null,
        ?TemplateEngineInterface $engine = null
    ) {
        $this->registry = $registry ?? new ComponentRegistry();
        $this->engine = $engine;
    }

    /**
     * Get or create the singleton instance.
     *
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Set the singleton instance.
     *
     * @param self $instance
     * @return void
     */
    public static function setInstance(self $instance): void
    {
        self::$instance = $instance;
    }

    /**
     * Render a component by name.
     *
     * @param string $name Component name (e.g., 'alert', 'forms/input')
     * @param array<string, mixed> $props Properties to pass to the component
     * @return string Rendered HTML
     *
     * @throws ComponentNotFoundException If template doesn't exist
     */
    public function render(string $name, array $props = []): string
    {
        $component = $this->createComponent($name, $props);
        $data = $this->resolveData($component, $props);

        // Check if component should render
        if ($component && !$component->shouldRender()) {
            return '';
        }

        return $this->renderTemplate($name, $data, $component);
    }

    /**
     * Create a component instance if class exists.
     *
     * @param string $name Component name
     * @param array<string, mixed> $props Properties
     * @return Component|null
     */
    private function createComponent(string $name, array $props): ?Component
    {
        $className = $this->registry->resolveClass($name);

        if ($className === null) {
            return null;
        }

        return $this->instantiateComponent($className, $props);
    }

    /**
     * Instantiate a component class with props.
     *
     * Supports both constructor injection and property assignment.
     *
     * @param class-string<Component> $className
     * @param array<string, mixed> $props
     * @return Component
     */
    private function instantiateComponent(string $className, array $props): Component
    {
        $reflection = new \ReflectionClass($className);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            // No constructor, create instance and assign properties
            $component = new $className();
            $this->assignProperties($component, $props);
            return $component;
        }

        // Map props to constructor parameters
        $parameters = $constructor->getParameters();
        $args = [];

        foreach ($parameters as $param) {
            $paramName = $param->getName();

            if (array_key_exists($paramName, $props)) {
                $args[] = $props[$paramName];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                $args[] = null;
            }
        }

        $component = $reflection->newInstanceArgs($args);

        // Assign any remaining props as properties
        $constructorParams = array_map(
            fn ($p) => $p->getName(),
            $parameters
        );
        $remainingProps = array_diff_key($props, array_flip($constructorParams));
        $this->assignProperties($component, $remainingProps);

        return $component;
    }

    /**
     * Assign properties to a component instance.
     *
     * @param Component $component
     * @param array<string, mixed> $props
     * @return void
     */
    private function assignProperties(Component $component, array $props): void
    {
        foreach ($props as $name => $value) {
            if (property_exists($component, $name)) {
                $component->{$name} = $value;
            }
        }
    }

    /**
     * Resolve all data for template rendering.
     *
     * @param Component|null $component
     * @param array<string, mixed> $props
     * @return array<string, mixed>
     */
    private function resolveData(?Component $component, array $props): array
    {
        if ($component === null) {
            return $props;
        }

        return $component->resolveData();
    }

    /**
     * Render the component template.
     *
     * @param string $name Component name
     * @param array<string, mixed> $data Template data
     * @param Component|null $component Component instance
     * @return string Rendered HTML
     */
    private function renderTemplate(string $name, array $data, ?Component $component): string
    {
        $templatePath = $this->registry->resolveFullTemplatePath($name, $component);

        if (!file_exists($templatePath)) {
            throw new ComponentNotFoundException(
                "Component template not found: {$templatePath}"
            );
        }

        // Use injected engine or load template directly
        if ($this->engine) {
            $template = $this->registry->resolveTemplate($name, $component);

            // Need to render from components path, not views path
            // So we read the file and render from string
            $content = file_get_contents($templatePath);
            return $this->engine->renderFromString($content, $data);
        }

        // Fallback: use Twig directly for component templates
        return $this->renderWithTwig($templatePath, $data);
    }

    /**
     * Render using Twig directly (for standalone component rendering).
     *
     * @param string $templatePath Full path to template
     * @param array<string, mixed> $data
     * @return string
     */
    private function renderWithTwig(string $templatePath, array $data): string
    {
        if (!class_exists('\\Twig\\Environment')) {
            throw new \RuntimeException(
                'Twig is required for component rendering. Install with: composer require twig/twig:^3.0'
            );
        }

        $loaderClass = '\\Twig\\Loader\\FilesystemLoader';
        $envClass = '\\Twig\\Environment';

        $directory = dirname($templatePath);
        $filename = basename($templatePath);

        $loader = new $loaderClass($directory);
        $twig = new $envClass($loader);

        return $twig->render($filename, $data);
    }

    /**
     * Get the component registry.
     *
     * @return ComponentRegistry
     */
    public function getRegistry(): ComponentRegistry
    {
        return $this->registry;
    }

    /**
     * Set the template engine.
     *
     * @param TemplateEngineInterface $engine
     * @return void
     */
    public function setEngine(TemplateEngineInterface $engine): void
    {
        $this->engine = $engine;
    }
}
