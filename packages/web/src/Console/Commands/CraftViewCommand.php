<?php

declare(strict_types=1);

namespace Lalaz\Web\Console\Commands;

use Lalaz\Config\Config;
use Lalaz\Console\Contracts\CommandInterface;
use Lalaz\Console\Input;
use Lalaz\Console\Output;

/**
 * Command that generates a Twig view template.
 *
 * Creates a new view file in the configured views directory
 * with support for nested paths and optional layout inheritance.
 *
 * Usage: php lalaz craft:view pages/home
 *        php lalaz craft:view admin/auth/login --layout=admin
 *        php lalaz craft:view components/alert --blank
 *
 * @package lalaz/web
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
final class CraftViewCommand implements CommandInterface
{
    /**
     * Get the command name.
     *
     * @return string The command name.
     */
    public function name(): string
    {
        return 'craft:view';
    }

    /**
     * Get the command description.
     *
     * @return string The command description.
     */
    public function description(): string
    {
        return 'Generate a Twig view template';
    }

    /**
     * Get the command arguments definition.
     *
     * @return array<int, array<string, mixed>> The arguments configuration.
     */
    public function arguments(): array
    {
        return [
            [
                'name' => 'name',
                'description' => 'View path (e.g., pages/home, admin/users/index)',
                'optional' => false,
            ],
        ];
    }

    /**
     * Get the command options definition.
     *
     * @return array<int, array<string, mixed>> The options configuration.
     */
    public function options(): array
    {
        return [
            [
                'name' => 'layout',
                'shortcut' => 'l',
                'description' => 'Layout to extend (default: layouts/app)',
                'requiresValue' => true,
            ],
            [
                'name' => 'blank',
                'shortcut' => 'b',
                'description' => 'Create a blank view without layout',
                'requiresValue' => false,
            ],
            [
                'name' => 'title',
                'shortcut' => 't',
                'description' => 'Page title for the view',
                'requiresValue' => true,
            ],
        ];
    }

    /**
     * Execute the command.
     *
     * @param Input $input The command input.
     * @param Output $output The command output.
     * @return int Exit code (0 for success, non-zero for failure).
     */
    public function handle(Input $input, Output $output): int
    {
        $name = $input->argument(0);
        if (!$name) {
            $output->error('Usage: php lalaz craft:view pages/home');
            $output->writeln('');
            $output->writeln('Examples:');
            $output->writeln('  php lalaz craft:view pages/home');
            $output->writeln('  php lalaz craft:view admin/users/index');
            $output->writeln('  php lalaz craft:view admin/auth/login --layout=layouts/admin');
            $output->writeln('  php lalaz craft:view components/alert --blank');
            $output->writeln('  php lalaz craft:view pages/about --title="About Us"');
            return 1;
        }

        // Normalize path separators
        $name = str_replace('\\', '/', $name);

        // Remove .twig extension if provided
        $name = preg_replace('/\.twig$/', '', $name);

        // Get views directory from config or use default
        $viewsPath = $this->getViewsPath();

        // Build full file path
        $file = $viewsPath . '/' . $name . '.twig';

        // Check if file already exists
        if (file_exists($file)) {
            $output->error("View already exists: {$file}");
            return 1;
        }

        // Get options
        $isBlank = $input->hasFlag('blank') || $input->hasFlag('b');
        $layout = $input->option('layout') ?? 'layouts/app';
        $title = $input->option('title') ?? $this->generateTitle($name);

        // Generate stub
        $stub = $isBlank
            ? $this->generateBlankStub($name)
            : $this->generateLayoutStub($name, $layout, $title);

        // Create directory if needed
        $dir = dirname($file);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                $output->error("Failed to create directory: {$dir}");
                return 1;
            }
        }

        // Write file
        if (file_put_contents($file, $stub) === false) {
            $output->error("Failed to write file: {$file}");
            return 1;
        }

        $output->writeln("✓ View created: {$file}");
        $output->writeln('');

        if (!$isBlank) {
            $output->writeln("  Layout: {$layout}");
            $output->writeln("  Title:  {$title}");
            $output->writeln('');
            $output->writeln('Make sure the layout exists at:');
            $output->writeln("  {$viewsPath}/{$layout}.twig");
        }

        return 0;
    }

    /**
     * Get the views directory path.
     *
     * @return string The views directory path.
     */
    private function getViewsPath(): string
    {
        // Try to get from config
        $configPath = Config::get('views.path');

        if ($configPath && is_string($configPath) && is_dir($configPath)) {
            return rtrim($configPath, '/');
        }

        // Default fallback
        return getcwd() . '/resources/views';
    }

    /**
     * Generate a title from the view name.
     *
     * @param string $name The view name/path.
     * @return string The generated title.
     */
    private function generateTitle(string $name): string
    {
        // Get the last segment of the path
        $parts = explode('/', $name);
        $lastPart = end($parts);

        // Convert to title case
        $title = str_replace(['-', '_'], ' ', $lastPart);
        $title = ucwords($title);

        return $title;
    }

    /**
     * Generate a view stub with layout inheritance.
     *
     * @param string $name The view name.
     * @param string $layout The layout to extend.
     * @param string $title The page title.
     * @return string The generated Twig template.
     */
    private function generateLayoutStub(string $name, string $layout, string $title): string
    {
        // Remove .twig from layout if present
        $layout = preg_replace('/\.twig$/', '', $layout);

        return <<<TWIG
{% extends "{$layout}.twig" %}

{% block title %}{$title}{% endblock %}

{% block content %}
<div class="container">
    <h1>{$title}</h1>

    {# ────────────────────────────────────────────────────────────
       View: {$name}

       Add your content here. Available variables from controllers
       and ViewComposers are accessible directly.

       Examples:
       - {{ variable }}
       - {% for item in items %}...{% endfor %}
       - {% if condition %}...{% endif %}
       ──────────────────────────────────────────────────────────── #}

    <p>Welcome to the {$title} page.</p>
</div>
{% endblock %}
TWIG;
    }

    /**
     * Generate a blank view stub without layout.
     *
     * @param string $name The view name.
     * @return string The generated Twig template.
     */
    private function generateBlankStub(string $name): string
    {
        return <<<TWIG
{# ────────────────────────────────────────────────────────────
   View: {$name}

   This is a blank template without layout inheritance.
   Useful for partials, components, or AJAX responses.
   ──────────────────────────────────────────────────────────── #}

<div>
    {# Add your content here #}
</div>
TWIG;
    }
}
