<?php

declare(strict_types=1);

namespace Lalaz\Database\Migrations;

use Lalaz\Database\Contracts\ConnectionInterface;
use Lalaz\Database\Schema\SchemaBuilder;

final class MigrationRepository
{
    private string $table;

    public function __construct(
        private ConnectionInterface $connection,
        private SchemaBuilder $schema,
        string $table = 'migrations',
    ) {
        $this->table = $table;
    }

    public function ensureTable(): void
    {
        $this->schema->createIfNotExists($this->table, function ($table): void {
            $table->increments('id');
            $table->string('migration');
            $table->integer('batch');
            $table->timestamp('ran_at')->nullable();
        });
    }

    /**
     * @return array<int, array{migration:string,batch:int}>
     */
    public function all(): array
    {
        return $this->connection->select(
            "SELECT migration, batch FROM {$this->table} ORDER BY batch ASC, id ASC",
        );
    }

    public function getLastBatchNumber(): int
    {
        $result = $this->connection->select(
            "SELECT MAX(batch) as batch FROM {$this->table}",
        );
        $batch = $result[0]['batch'] ?? 0;
        return (int) ($batch ?? 0);
    }

    /**
     * @return array<int, string>
     */
    public function getMigrationsForBatch(int $batch): array
    {
        $rows = $this->connection->select(
            "SELECT migration FROM {$this->table} WHERE batch = :batch ORDER BY id DESC",
            [':batch' => $batch],
        );

        return array_map(fn (array $row): string => $row['migration'], $rows);
    }

    public function log(string $migration, int $batch): void
    {
        $this->connection->insert(
            "INSERT INTO {$this->table} (migration, batch, ran_at) VALUES (:migration, :batch, :ran_at)",
            [
                ':migration' => $migration,
                ':batch' => $batch,
                ':ran_at' => date('Y-m-d H:i:s'),
            ],
        );
    }

    public function delete(string $migration): void
    {
        $this->connection->delete(
            "DELETE FROM {$this->table} WHERE migration = :migration",
            [':migration' => $migration],
        );
    }
}
