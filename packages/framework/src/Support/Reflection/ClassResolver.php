<?php

declare(strict_types=1);

namespace Lalaz\Support\Reflection;

/**
 * Helper to resolve fully-qualified class names from file paths.
 *
 * This class parses PHP files to extract namespace and class name
 * information, building the fully-qualified class name (FQCN).
 * Useful for auto-discovery and dynamic class loading.
 *
 * Example usage:
 * ```php
 * $fqcn = ClassResolver::getClassNameFromFile('/path/to/MyClass.php');
 * // Returns: 'App\Models\MyClass'
 * ```
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
class ClassResolver
{
    /**
     * Extracts the fully-qualified class name from a PHP file.
     *
     * Parses the file content to extract the namespace declaration
     * and class name, then combines them into a FQCN.
     *
     * @param string $filePath The path to the PHP file
     *
     * @return string|null The fully-qualified class name or null if not found
     */
    public static function getClassNameFromFile(string $filePath): ?string
    {
        if (!file_exists($filePath)) {
            return null;
        }

        $content = file_get_contents($filePath);
        $namespace = null;

        if (preg_match("/namespace\s+([^;]+);/", $content, $matches)) {
            $namespace = trim($matches[1]);
        }

        $className = null;

        if (preg_match("/class\s+(\w+)/", $content, $matches)) {
            $className = $matches[1];
        }

        if ($namespace && $className) {
            return "$namespace\\$className";
        }

        return $className;
    }
}
