<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Fixtures\Factories;

/**
 * Factory for creating temporary directory structures for testing.
 */
final class TempDirectoryFactory
{
    /** @var array<int, string> */
    private static array $createdPaths = [];

    /**
     * Create a temporary application directory structure.
     *
     * @return string The base path of the created structure.
     */
    public static function applicationStructure(): string
    {
        $basePath = sys_get_temp_dir() . '/lalaz_app_' . uniqid('', true);

        @mkdir($basePath . '/config', 0777, true);
        @mkdir($basePath . '/routes', 0777, true);
        @mkdir($basePath . '/storage/cache', 0777, true);

        self::$createdPaths[] = $basePath;

        return $basePath;
    }

    /**
     * Create an .env file in the given base path.
     *
     * @param array<string, string> $variables
     */
    public static function createEnvFile(string $basePath, array $variables = []): string
    {
        $content = '';
        foreach ($variables as $key => $value) {
            $content .= "{$key}={$value}\n";
        }

        $path = $basePath . '/.env';
        file_put_contents($path, $content);

        return $path;
    }

    /**
     * Create a config file in the given base path.
     *
     * @param array<string, mixed> $config
     */
    public static function createConfigFile(string $basePath, string $name, array $config): string
    {
        $path = $basePath . '/config/' . $name . '.php';
        $content = '<?php return ' . var_export($config, true) . ';';
        file_put_contents($path, $content);

        return $path;
    }

    /**
     * Clean up all created temporary directories.
     */
    public static function cleanup(): void
    {
        foreach (self::$createdPaths as $path) {
            self::removeDirectory($path);
        }

        self::$createdPaths = [];
    }

    /**
     * Clean up a specific directory.
     */
    public static function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                @rmdir($file->getPathname());
                continue;
            }

            @unlink($file->getPathname());
        }

        @rmdir($path);
    }
}
