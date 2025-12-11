<?php

declare(strict_types=1);

namespace Lalaz\Support\Cache;

/**
 * A simple utility for caching PHP arrays to plain PHP files.
 *
 * This class is designed for internal framework use, primarily for bootstrap
 * performance optimization. It serializes an array into a PHP file that can
 * be loaded efficiently using `require`. This is not a general-purpose
 * application cache.
 *
 * Uses atomic write pattern (write to temp file, then rename) to prevent
 * cache corruption in high-concurrency environments.
 *
 * @package lalaz/framework
 * @author Lalaz Team <hi@lalaz.dev>
 */
final class PhpArrayCache
{
    /**
     * Saves a PHP array to a cacheable PHP file using atomic write.
     *
     * The array is serialized using var_export() and wrapped in a return
     * statement. Uses atomic write pattern to prevent race conditions:
     * 1. Write to temporary file
     * 2. Rename to final destination (atomic on POSIX systems)
     *
     * @param string $filePath The full path to the file where the array will be saved.
     * @param array  $data     The array to save.
     * @return bool True on success, false on failure.
     */
    public function save(string $filePath, array $data): bool
    {
        $directory = dirname($filePath);

        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
                return false;
            }
        }

        $content = '<?php return ' . var_export($data, true) . ";\n";

        // Generate unique temp file in same directory (ensures same filesystem for rename)
        $tempFile = $directory . '/.cache_' . bin2hex(random_bytes(8)) . '.tmp';

        try {
            // Write to temporary file with exclusive lock
            if (file_put_contents($tempFile, $content, LOCK_EX) === false) {
                return false;
            }

            // Set proper permissions before rename
            chmod($tempFile, 0644);

            // Atomic rename (atomic on POSIX, best-effort on Windows)
            if (!rename($tempFile, $filePath)) {
                @unlink($tempFile);
                return false;
            }

            // Invalidate OPcache for the file if available
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($filePath, true);
            }

            return true;
        } catch (\Throwable) {
            // Clean up temp file on any error
            @unlink($tempFile);
            return false;
        }
    }

    /**
     * Loads a PHP array from a cache file.
     *
     * @param string $filePath The full path to the cache file.
     * @return array|null The cached array, or null if the file does not exist.
     */
    public function load(string $filePath): ?array
    {
        if (!file_exists($filePath)) {
            return null;
        }

        return require $filePath;
    }
}
