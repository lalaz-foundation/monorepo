<?php

declare(strict_types=1);

namespace Lalaz\Database\Seeding;

use Lalaz\Container\Contracts\ContainerInterface;
use RuntimeException;

final class SeederRunner
{
    public function __construct(private ?ContainerInterface $container = null)
    {
    }

    /**
     * Run a specific seeder class.
     *
     * @param class-string<Seeder> $seeder
     */
    public function run(string $seeder): void
    {
        $this->resolveSeeder($seeder)->run();
    }

    /**
     * @param array<int, string> $paths
     */
    public function runAll(array $paths): void
    {
        $files = $this->discover($paths);
        foreach ($files as $file) {
            $instance = $this->load($file);
            $instance->run();
        }
    }

    /**
     * @param array<int, string> $paths
     * @return array<int, string>
     */
    private function discover(array $paths): array
    {
        $files = [];
        foreach ($paths as $path) {
            if (!is_dir($path)) {
                continue;
            }
            foreach (glob(rtrim($path, '/') . '/*.php') ?: [] as $file) {
                $files[] = $file;
            }
        }

        sort($files);
        return $files;
    }

    private function load(string $file): Seeder
    {
        $seeder = require $file;
        if ($seeder instanceof Seeder) {
            return $seeder;
        }

        if (is_string($seeder) && class_exists($seeder)) {
            $instance = new $seeder($this->container);
            if ($instance instanceof Seeder) {
                return $instance;
            }
        }

        if (is_object($seeder) && $seeder instanceof Seeder) {
            return $seeder;
        }

        throw new RuntimeException(
            "Seeder file {$file} must return a Seeder instance.",
        );
    }

    /**
     * @param class-string<Seeder> $seeder
     */
    private function resolveSeeder(string $seeder): Seeder
    {
        if ($this->container !== null && $this->container->bound($seeder)) {
            /** @var Seeder $instance */
            $instance = $this->container->resolve($seeder);
            return $instance;
        }

        return new $seeder($this->container);
    }
}
