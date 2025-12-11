<?php

declare(strict_types=1);

namespace Lalaz\Console\Generators;

/**
 * Code generation utilities for craft commands.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
final class Generator
{
    /**
     * Ensure a directory exists, creating it if necessary.
     *
     * @param string $path The directory path.
     * @return void
     */
    public static function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }

    /**
     * Write content to a file, creating directories as needed.
     *
     * @param string $path     The file path.
     * @param string $contents The file contents.
     * @return void
     */
    public static function writeFile(string $path, string $contents): void
    {
        self::ensureDirectory(dirname($path));
        file_put_contents($path, $contents);
    }

    /**
     * Ensure a class name has a specific suffix.
     *
     * Examples:
     *   - ensureSuffix('Home', 'Controller') => 'HomeController'
     *   - ensureSuffix('HomeController', 'Controller') => 'HomeController'
     *   - ensureSuffix('Admin/Auth', 'Controller') => 'Admin/AuthController'
     *
     * @param string $name   The class name (may include path separators).
     * @param string $suffix The required suffix.
     * @return string The class name with suffix.
     */
    public static function ensureSuffix(string $name, string $suffix): string
    {
        // Handle path separators - only add suffix to the last segment
        $separator = str_contains($name, '/') ? '/' : '\\';
        $parts = explode($separator, $name);
        $lastPart = array_pop($parts);

        if (!str_ends_with($lastPart, $suffix)) {
            $lastPart .= $suffix;
        }

        if (count($parts) > 0) {
            return implode($separator, $parts) . $separator . $lastPart;
        }

        return $lastPart;
    }

    /**
     * Normalize a class name and generate its file path.
     *
     * This method handles:
     * - Simple names: "Home" => App\Controllers\HomeController
     * - Subfolders with /: "Admin/Auth" => App\Controllers\Admin\AuthController
     * - FQCN: "App\Controllers\HomeController" (used as-is)
     *
     * The returned path is relative to the app/ directory.
     *
     * @param string $name             The class name (simple, with subfolders, or FQCN).
     * @param string $defaultNamespace The default namespace if not provided.
     * @return array{0: string, 1: string} [fully qualified class name, relative path]
     */
    public static function normalizeClass(string $name, string $defaultNamespace): array
    {
        $name = ltrim($name, '\\');

        // Convert forward slashes to backslashes for namespace consistency
        $name = str_replace('/', '\\', $name);

        // Check if this is a fully qualified class name (starts with App\)
        // If not, prepend the default namespace
        if (!str_starts_with($name, 'App\\')) {
            $name = $defaultNamespace . '\\' . $name;
        }

        // Clean the class name (only allow alphanumeric and backslashes)
        $class = preg_replace('/[^A-Za-z0-9\\\\]/', '', $name);

        // Convert to path and remove "App/" prefix since we add "app/" manually
        $path = str_replace('\\', '/', $class);
        if (str_starts_with($path, 'App/')) {
            $path = substr($path, 4); // Remove "App/" prefix
        }

        return [$class, $path];
    }
}
