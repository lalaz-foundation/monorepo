<?php

declare(strict_types=1);

namespace Lalaz\Web\Routing\Registrars;

use Lalaz\Web\Routing\Contracts\RouteRegistrarInterface;
use Lalaz\Web\Routing\Contracts\RouterInterface;

/**
 * Discovers controller classes on disk and registers their #[Route] attributes.
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
class ControllerDiscoveryRegistrar implements RouteRegistrarInterface
{
    /**
     * @param array<int, array<string, mixed>> $directories
     */
    public function __construct(private array $directories)
    {
    }

    public function register(RouterInterface $router): void
    {
        $controllers = [];

        foreach ($this->directories as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $namespace = $entry['namespace'] ?? null;
            $path = $entry['path'] ?? null;

            if (!is_string($namespace) || !is_string($path)) {
                continue;
            }

            $pattern = $entry['pattern'] ?? '*Controller.php';
            $recursive = array_key_exists('recursive', $entry)
                ? (bool) $entry['recursive']
                : true;

            $controllers = array_merge(
                $controllers,
                $this->discoverControllers(
                    $namespace,
                    $path,
                    (string) $pattern,
                    $recursive,
                ),
            );
        }

        $controllers = array_values(
            array_filter(
                array_unique($controllers),
                static fn ($class) => is_string($class) && class_exists($class),
            ),
        );

        if ($controllers !== []) {
            $router->registerControllers($controllers);
        }
    }

    private function discoverControllers(
        string $baseNamespace,
        string $basePath,
        string $pattern,
        bool $recursive,
    ): array {
        if (!is_dir($basePath)) {
            return [];
        }

        $controllers = [];

        if ($recursive) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $basePath,
                    \FilesystemIterator::SKIP_DOTS,
                ),
            );

            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }

                if (!fnmatch($pattern, $file->getFilename())) {
                    continue;
                }

                $controllers[] = $this->classFromFile(
                    $baseNamespace,
                    $basePath,
                    $file->getPathname(),
                );
            }
        } else {
            foreach (glob(rtrim($basePath, '/') . '/' . $pattern) as $file) {
                if (is_file($file)) {
                    $controllers[] = $this->classFromFile(
                        $baseNamespace,
                        $basePath,
                        $file,
                    );
                }
            }
        }

        return $controllers;
    }

    private function classFromFile(
        string $baseNamespace,
        string $basePath,
        string $file,
    ): string {
        $relative = ltrim(
            str_replace(
                ['\\', '/'],
                DIRECTORY_SEPARATOR,
                substr($file, strlen(rtrim($basePath, '/') . '/')),
            ),
            DIRECTORY_SEPARATOR,
        );

        $withoutPhp = preg_replace('/\\.php$/', '', $relative);
        $segments = explode(DIRECTORY_SEPARATOR, (string) $withoutPhp);

        $classSuffix = implode('\\', $segments);

        return rtrim($baseNamespace, '\\') . '\\' . $classSuffix;
    }
}
