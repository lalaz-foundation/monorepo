<?php

declare(strict_types=1);

namespace Lalaz\Database\Migrations;

use Lalaz\Database\Contracts\ConnectionInterface;
use Lalaz\Database\Schema\SchemaBuilder;

final class Migrator
{
    public function __construct(
        private SchemaBuilder $schema,
        private MigrationRepository $repository,
        private ConnectionInterface $connection,
    ) {
    }

    /**
     * Run all outstanding migrations for the given paths.
     *
     * @param array<int, string> $paths
     * @return array<int, string> ran migration names
     */
    public function run(array $paths): array
    {
        $this->repository->ensureTable();

        $ran = $this->migrationNames();
        $files = $this->getMigrationFiles($paths);

        $batch = $this->repository->getLastBatchNumber() + 1;
        $executed = [];

        foreach ($files as $name => $path) {
            if (in_array($name, $ran, true)) {
                continue;
            }

            $migration = $this->resolve($path);
            $this->connection->transaction(function () use ($migration): void {
                $migration->up($this->schema);
            });

            $this->repository->log($name, $batch);
            $executed[] = $name;
        }

        return $executed;
    }

    /**
     * Rollback the last batch.
     *
     * @param array<int, string> $paths
     * @return array<int, string> rolled back migration names
     */
    public function rollback(array $paths): array
    {
        $this->repository->ensureTable();

        $batch = $this->repository->getLastBatchNumber();
        if ($batch === 0) {
            return [];
        }

        $files = $this->getMigrationFiles($paths);
        $migrations = $this->repository->getMigrationsForBatch($batch);
        $rolledBack = [];

        foreach ($migrations as $name) {
            if (!isset($files[$name])) {
                continue;
            }
            $migration = $this->resolve($files[$name]);

            $this->connection->transaction(function () use ($migration): void {
                $migration->down($this->schema);
            });

            $this->repository->delete($name);
            $rolledBack[] = $name;
        }

        return $rolledBack;
    }

    /**
     * Reset all migrations.
     *
     * @param array<int, string> $paths
     * @return array<int, string> rolled back migrations
     */
    public function reset(array $paths): array
    {
        $this->repository->ensureTable();
        $files = $this->getMigrationFiles($paths);
        $ran = $this->repository->all();
        $rolled = [];

        // Rollback in reverse batch order
        $batches = array_unique(
            array_map(static fn (array $row): int => (int) $row['batch'], $ran),
        );
        rsort($batches);

        foreach ($batches as $batch) {
            foreach (
                $this->repository->getMigrationsForBatch($batch) as $name
            ) {
                if (!isset($files[$name])) {
                    continue;
                }
                $migration = $this->resolve($files[$name]);
                $this->connection->transaction(function () use (
                    $migration,
                ): void {
                    $migration->down($this->schema);
                });
                $this->repository->delete($name);
                $rolled[] = $name;
            }
        }

        return $rolled;
    }

    /**
     * @return array<int, array{migration:string,batch:int}>
     */
    public function status(): array
    {
        return $this->repository->all();
    }

    /**
     * @param array<int, string> $paths
     * @return array<string, string> name => path
     */
    private function getMigrationFiles(array $paths): array
    {
        $files = [];
        foreach ($paths as $path) {
            if (!is_dir($path)) {
                continue;
            }
            foreach (glob(rtrim($path, '/') . '/*.php') ?: [] as $file) {
                $name = basename($file, '.php');
                $files[$name] = $file;
            }
        }

        ksort($files);
        return $files;
    }

    /**
     * @return array<int, string>
     */
    private function migrationNames(): array
    {
        return array_map(
            static fn (array $row): string => $row['migration'],
            $this->repository->all(),
        );
    }

    private function resolve(string $path): Migration
    {
        $migration = require $path;

        if ($migration instanceof Migration) {
            return $migration;
        }

        if (is_string($migration) && class_exists($migration)) {
            $instance = new $migration();
            if ($instance instanceof Migration) {
                return $instance;
            }
        }

        if (is_object($migration) && $migration instanceof Migration) {
            return $migration;
        }

        throw new \RuntimeException(
            "Migration file {$path} must return a Migration instance.",
        );
    }
}
