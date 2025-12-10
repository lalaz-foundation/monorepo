<?php

declare(strict_types=1);

namespace Lalaz\Web\View\Components;

use Lalaz\Config\Config;

/**
 * Registry for discovering and caching component classes.
 *
 * Handles the mapping between component names and their
 * corresponding PHP classes and template files.
 *
 * @package Lalaz\Web\View\Components
 */
class ComponentRegistry
{
    /**
     * Cache of resolved component classes.
     *
     * @var array<string, class-string|null>
     */
    private array $classCache = [];

    /**
     * Cache of resolved template paths.
     *
     * @var array<string, string>
     */
    private array $templateCache = [];

    /**
     * Base namespace for component classes.
     */
    private string $namespace;

    /**
     * Base path for component templates.
     */
    private string $templatePath;

    /**
     * Create a new component registry.
     *
     * @param string|null $namespace Custom namespace for components
     * @param string|null $templatePath Custom path for component templates
     */
    public function __construct(?string $namespace = null, ?string $templatePath = null)
    {
        $this->namespace = $namespace ?? $this->resolveNamespace();
        $this->templatePath = $templatePath ?? $this->resolveTemplatePath();
    }

    /**
     * Resolve the namespace from configuration.
     *
     * @return string
     */
    private function resolveNamespace(): string
    {
        if (class_exists(Config::class)) {
            return Config::get('views.components.namespace', 'App\\Components');
        }

        return 'App\\Components';
    }

    /**
     * Resolve the template path from configuration.
     *
     * @return string
     */
    private function resolveTemplatePath(): string
    {
        if (class_exists(Config::class)) {
            $path = Config::get('views.components.path');

            if ($path) {
                return rtrim($path, '/');
            }

            // Default: sibling to views path
            $viewsPath = Config::get('views.path', 'resources/views');
            $basePath = dirname($viewsPath);

            return $basePath . '/components';
        }

        return 'resources/components';
    }

    /**
     * Get the PHP class for a component name.
     *
     * @param string $name Component name (e.g., 'alert', 'forms/input')
     * @return class-string<Component>|null
     */
    public function resolveClass(string $name): ?string
    {
        if (array_key_exists($name, $this->classCache)) {
            return $this->classCache[$name];
        }

        $className = $this->buildClassName($name);

        if (class_exists($className) && is_subclass_of($className, Component::class)) {
            $this->classCache[$name] = $className;
            return $className;
        }

        $this->classCache[$name] = null;
        return null;
    }

    /**
     * Build the fully qualified class name from component name.
     *
     * @param string $name Component name
     * @return string Fully qualified class name
     */
    private function buildClassName(string $name): string
    {
        // Convert 'forms/input' to 'Forms\Input'
        $parts = array_map(
            fn ($part) => ucfirst($this->toPascalCase($part)),
            explode('/', $name)
        );

        return $this->namespace . '\\' . implode('\\', $parts);
    }

    /**
     * Convert a string to PascalCase.
     *
     * @param string $string
     * @return string
     */
    private function toPascalCase(string $string): string
    {
        // Handle kebab-case and snake_case
        $string = str_replace(['-', '_'], ' ', $string);
        $string = ucwords($string);
        return str_replace(' ', '', $string);
    }

    /**
     * Get the template path for a component.
     *
     * @param string $name Component name
     * @param Component|null $component Component instance for custom template
     * @return string Template path relative to components directory
     */
    public function resolveTemplate(string $name, ?Component $component = null): string
    {
        // Check if component specifies a custom template
        if ($component && $component->template()) {
            return $component->template();
        }

        if (isset($this->templateCache[$name])) {
            return $this->templateCache[$name];
        }

        // Convert name to template path: 'forms/input' -> 'forms/input'
        $template = strtolower($name);
        $this->templateCache[$name] = $template;

        return $template;
    }

    /**
     * Get the full filesystem path to a component template.
     *
     * @param string $name Component name
     * @param Component|null $component Component instance
     * @return string Full path to template file
     */
    public function resolveFullTemplatePath(string $name, ?Component $component = null): string
    {
        $template = $this->resolveTemplate($name, $component);
        return $this->templatePath . '/' . $template . '.twig';
    }

    /**
     * Check if a component template exists.
     *
     * @param string $name Component name
     * @return bool
     */
    public function templateExists(string $name): bool
    {
        $path = $this->resolveFullTemplatePath($name);
        return file_exists($path);
    }

    /**
     * Get the base template path.
     *
     * @return string
     */
    public function getTemplatePath(): string
    {
        return $this->templatePath;
    }

    /**
     * Get the component namespace.
     *
     * @return string
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * Clear all caches.
     *
     * @return void
     */
    public function clearCache(): void
    {
        $this->classCache = [];
        $this->templateCache = [];
    }

    /**
     * Register a component class manually.
     *
     * @param string $name Component name
     * @param class-string<Component> $className
     * @return void
     */
    public function register(string $name, string $className): void
    {
        $this->classCache[$name] = $className;
    }
}
