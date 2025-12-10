<?php

declare(strict_types=1);

namespace Lalaz\Web\Console\Commands;

use Lalaz\Config\Config;
use Lalaz\Console\Contracts\CommandInterface;
use Lalaz\Console\Input;
use Lalaz\Console\Output;

/**
 * Generate a view component.
 *
 * Creates both the PHP class and Twig template for a component.
 * Supports nested paths like 'forms/input' or 'ui/buttons/primary'.
 *
 * @package Lalaz\Web\Console\Commands
 */
final class CraftComponentCommand implements CommandInterface
{
    /**
     * Get the command name.
     *
     * @return string
     */
    public function name(): string
    {
        return 'craft:component';
    }

    /**
     * Get the command description.
     *
     * @return string
     */
    public function description(): string
    {
        return 'Generate a view component (class + template)';
    }

    /**
     * Get the command arguments definition.
     *
     * @return array<int, array<string, mixed>>
     */
    public function arguments(): array
    {
        return [
            [
                'name' => 'name',
                'description' => 'Component name (e.g., Alert, forms/Input)',
                'optional' => false,
            ],
        ];
    }

    /**
     * Get the command options definition.
     *
     * @return array<int, array<string, mixed>>
     */
    public function options(): array
    {
        return [
            [
                'name' => 'template-only',
                'shortcut' => 't',
                'description' => 'Create only the Twig template (no PHP class)',
                'requiresValue' => false,
            ],
            [
                'name' => 'class-only',
                'shortcut' => 'c',
                'description' => 'Create only the PHP class (no template)',
                'requiresValue' => false,
            ],
        ];
    }

    /**
     * Execute the command.
     *
     * @param Input $input
     * @param Output $output
     * @return int Exit code
     */
    public function handle(Input $input, Output $output): int
    {
        $name = $input->argument(0);

        if (!$name) {
            $output->error('Usage: craft:component <name> [--template-only] [--class-only]');
            $output->writeln('');
            $output->writeln('Examples:');
            $output->writeln('  craft:component Alert');
            $output->writeln('  craft:component forms/Input');
            $output->writeln('  craft:component ui/buttons/Primary --template-only');
            return 1;
        }

        $templateOnly = $input->hasFlag('template-only') || $input->hasFlag('t');
        $classOnly = $input->hasFlag('class-only') || $input->hasFlag('c');

        $basePath = getcwd();
        $created = [];

        // Create PHP class (unless template-only)
        if (!$templateOnly) {
            $classResult = $this->createComponentClass($name, $basePath, $output);
            if ($classResult) {
                $created[] = $classResult;
            }
        }

        // Create template (unless class-only)
        if (!$classOnly) {
            $templateResult = $this->createComponentTemplate($name, $basePath, $output);
            if ($templateResult) {
                $created[] = $templateResult;
            }
        }

        if (empty($created)) {
            return 1;
        }

        $output->writeln('');
        $output->writeln('Usage in Twig:');
        $templateName = $this->toTemplateName($name);
        $output->writeln("  {{ component('{$templateName}', { prop: 'value' }) }}");

        return 0;
    }

    /**
     * Create the component PHP class.
     *
     * @param string $name Component name
     * @param string $basePath Base path
     * @param Output $output
     * @return string|null Created file path
     */
    private function createComponentClass(string $name, string $basePath, Output $output): ?string
    {
        $namespace = $this->resolveNamespace();
        $parts = $this->parseName($name);

        $className = $parts['class'];
        $subNamespace = $parts['namespace'];
        $fullNamespace = $subNamespace
            ? $namespace . '\\' . $subNamespace
            : $namespace;

        // Resolve directory from namespace
        $appDir = $basePath . '/app/Components';
        if ($subNamespace) {
            $appDir .= '/' . str_replace('\\', '/', $subNamespace);
        }

        $filePath = $appDir . '/' . $className . '.php';

        if (file_exists($filePath)) {
            $output->writeln("Component class already exists: {$filePath}");
            return null;
        }

        // Ensure directory exists
        if (!is_dir($appDir)) {
            mkdir($appDir, 0755, true);
        }

        $content = $this->generateClassContent($fullNamespace, $className);
        file_put_contents($filePath, $content);

        $output->writeln("✓ Component class created: {$filePath}");

        return $filePath;
    }

    /**
     * Create the component Twig template.
     *
     * @param string $name Component name
     * @param string $basePath Base path
     * @param Output $output
     * @return string|null Created file path
     */
    private function createComponentTemplate(string $name, string $basePath, Output $output): ?string
    {
        $componentsPath = $this->resolveComponentsPath($basePath);
        $templateName = $this->toTemplateName($name);

        $filePath = $componentsPath . '/' . $templateName . '.twig';
        $directory = dirname($filePath);

        if (file_exists($filePath)) {
            $output->writeln("Component template already exists: {$filePath}");
            return null;
        }

        // Ensure directory exists
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $parts = $this->parseName($name);
        $content = $this->generateTemplateContent($parts['class'], $templateName);
        file_put_contents($filePath, $content);

        $output->writeln("✓ Component template created: {$filePath}");

        return $filePath;
    }

    /**
     * Parse component name into parts.
     *
     * @param string $name
     * @return array{class: string, namespace: string|null, path: string}
     */
    private function parseName(string $name): array
    {
        // Normalize separators
        $name = str_replace('/', '\\', $name);
        $parts = explode('\\', $name);

        $className = ucfirst(array_pop($parts));
        $namespace = !empty($parts)
            ? implode('\\', array_map('ucfirst', $parts))
            : null;

        $path = strtolower(str_replace('\\', '/', $name));

        return [
            'class' => $className,
            'namespace' => $namespace,
            'path' => $path,
        ];
    }

    /**
     * Convert component name to template name (kebab-case path).
     *
     * @param string $name
     * @return string
     */
    private function toTemplateName(string $name): string
    {
        // Normalize separators
        $name = str_replace('\\', '/', $name);
        $parts = explode('/', $name);

        return implode('/', array_map(function ($part) {
            // Convert PascalCase/camelCase to kebab-case
            $result = preg_replace('/([a-z])([A-Z])/', '$1-$2', $part);
            return strtolower($result);
        }, $parts));
    }

    /**
     * Resolve the components namespace from config.
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
     * Resolve the components template path.
     *
     * @param string $basePath
     * @return string
     */
    private function resolveComponentsPath(string $basePath): string
    {
        if (class_exists(Config::class)) {
            $path = Config::get('views.components.path');

            if ($path) {
                // Handle relative paths
                if (!$this->isAbsolutePath($path)) {
                    return $basePath . '/' . $path;
                }
                return $path;
            }
        }

        return $basePath . '/resources/components';
    }

    /**
     * Check if path is absolute.
     *
     * @param string $path
     * @return bool
     */
    private function isAbsolutePath(string $path): bool
    {
        return $path[0] === '/' || (strlen($path) > 1 && $path[1] === ':');
    }

    /**
     * Generate the PHP class content.
     *
     * @param string $namespace
     * @param string $className
     * @return string
     */
    private function generateClassContent(string $namespace, string $className): string
    {
        return <<<PHP
<?php declare(strict_types=1);

namespace {$namespace};

use Lalaz\Web\View\Components\Component;

/**
 * {$className} component.
 *
 * @package {$namespace}
 */
class {$className} extends Component
{
    /**
     * Create a new {$className} component.
     *
     * Define your component's props as constructor parameters.
     * These will be automatically available in the template.
     */
    public function __construct(
        // public string \$title = '',
        // public string \$type = 'default',
    ) {
    }

    /**
     * Get additional data to pass to the view.
     *
     * Use this for computed properties or data that
     * requires logic beyond simple prop values.
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return [
            // 'computedValue' => \$this->computeSomething(),
        ];
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
}

PHP;
    }

    /**
     * Generate the Twig template content.
     *
     * @param string $className
     * @param string $templateName
     * @return string
     */
    private function generateTemplateContent(string $className, string $templateName): string
    {
        return <<<TWIG
{# ────────────────────────────────────────────────────────────
   Component: {$className}
   Template:  {$templateName}

   Props available from the component class are accessible
   directly: {{ prop_name }}

   Usage:
   {{ component('{$templateName}', { prop: 'value' }) }}
   ──────────────────────────────────────────────────────────── #}

<div class="{$templateName}">
    {# Add your component markup here #}
</div>

TWIG;
    }
}
