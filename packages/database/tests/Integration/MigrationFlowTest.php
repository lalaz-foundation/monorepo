<?php

declare(strict_types=1);

namespace Lalaz\Database\Tests\Integration;

use Lalaz\Database\Tests\Common\DatabaseIntegrationTestCase;

class MigrationFlowTest extends DatabaseIntegrationTestCase
{
    public function test_runs_single_migration(): void
    {
        $this->createTableMigration('test_users', ['id' => 'id', 'name' => 'string']);

        $ran = $this->runMigrations();

        $this->assertCount(1, $ran);
        $this->assertTableExists($this->connection, 'test_users');
        $this->assertMigrationsTableExists();
    }

    public function test_runs_multiple_migrations_in_order(): void
    {
        // Create first migration
        $this->createTableMigration('authors', ['id' => 'id', 'name' => 'string']);

        // Wait a second to ensure different timestamp
        usleep(100000);

        // Create second migration
        $this->createTableMigration('books', ['id' => 'id', 'title' => 'string']);

        $ran = $this->runMigrations();

        $this->assertCount(2, $ran);
        $this->assertTableExists($this->connection, 'authors');
        $this->assertTableExists($this->connection, 'books');
    }

    public function test_does_not_rerun_executed_migrations(): void
    {
        $this->createTableMigration('test_users', ['id' => 'id', 'name' => 'string']);

        $firstRun = $this->runMigrations();
        $secondRun = $this->runMigrations();

        $this->assertCount(1, $firstRun);
        $this->assertCount(0, $secondRun);
    }

    public function test_rollback_reverses_last_batch(): void
    {
        $this->createTableMigration('test_users', ['id' => 'id', 'name' => 'string']);

        $this->runMigrations();
        $this->assertTableExists($this->connection, 'test_users');

        $rolledBack = $this->rollbackMigrations();

        $this->assertCount(1, $rolledBack);
        $this->assertTableNotExists($this->connection, 'test_users');
    }

    public function test_reset_reverses_all_migrations(): void
    {
        $this->createTableMigration('users', ['id' => 'id', 'name' => 'string']);
        usleep(100000);
        $this->createTableMigration('posts', ['id' => 'id', 'title' => 'string']);

        $this->runMigrations();
        $this->assertTableExists($this->connection, 'users');
        $this->assertTableExists($this->connection, 'posts');

        $reset = $this->resetMigrations();

        $this->assertCount(2, $reset);
        $this->assertTableNotExists($this->connection, 'users');
        $this->assertTableNotExists($this->connection, 'posts');
    }

    public function test_migration_status_reports_ran_migrations(): void
    {
        $this->createTableMigration('test_users', ['id' => 'id', 'name' => 'string']);
        $this->runMigrations();

        $status = $this->migrator->status();

        $this->assertCount(1, $status);
        $this->assertSame(1, (int) $status[0]['batch']);
    }

    public function test_migrations_run_in_separate_batches(): void
    {
        // First batch
        $this->createTableMigration('batch_one', ['id' => 'id']);
        $this->runMigrations();

        // Second batch
        usleep(100000);
        $this->createTableMigration('batch_two', ['id' => 'id']);
        $this->runMigrations();

        $status = $this->migrator->status();
        $batches = array_unique(array_map(fn($row) => $row['batch'], $status));

        $this->assertCount(2, $batches);
    }

    public function test_migration_creates_table_with_correct_columns(): void
    {
        $this->createTableMigration('test_table', [
            'id' => 'id',
            'title' => 'string',
            'content' => 'text',
        ]);

        $this->runMigrations();

        $this->assertColumnExists($this->connection, 'test_table', 'id');
        $this->assertColumnExists($this->connection, 'test_table', 'title');
        $this->assertColumnExists($this->connection, 'test_table', 'content');
    }
}
