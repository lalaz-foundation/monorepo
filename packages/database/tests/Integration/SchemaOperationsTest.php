<?php

declare(strict_types=1);

namespace Lalaz\Database\Tests\Integration;

use Lalaz\Database\Tests\Common\DatabaseIntegrationTestCase;

class SchemaOperationsTest extends DatabaseIntegrationTestCase
{
    public function test_creates_table_with_all_column_types(): void
    {
        $this->schema->create('all_types', function ($table): void {
            $table->increments('id');
            $table->integer('count');
            $table->bigInteger('big_count');
            $table->string('name');
            $table->string('code', 10);
            $table->text('description');
            $table->boolean('is_active');
            $table->timestamp('published_at');
            $table->json('meta');
            $table->uuid('uuid');
            $table->timestamps();
        });

        $columns = $this->connection->select("PRAGMA table_info('all_types')");
        $names = array_map(fn($col) => $col['name'], $columns);

        $this->assertContains('id', $names);
        $this->assertContains('count', $names);
        $this->assertContains('big_count', $names);
        $this->assertContains('name', $names);
        $this->assertContains('code', $names);
        $this->assertContains('description', $names);
        $this->assertContains('is_active', $names);
        $this->assertContains('published_at', $names);
        $this->assertContains('meta', $names);
        $this->assertContains('uuid', $names);
        $this->assertContains('created_at', $names);
        $this->assertContains('updated_at', $names);
    }

    public function test_creates_table_with_nullable_column(): void
    {
        $this->schema->create('nullable_test', function ($table): void {
            $table->increments('id');
            $table->string('required_field');
            $table->string('optional_field')->nullable();
        });

        $columns = $this->connection->select("PRAGMA table_info('nullable_test')");

        $requiredCol = array_filter($columns, fn($c) => $c['name'] === 'required_field');
        $optionalCol = array_filter($columns, fn($c) => $c['name'] === 'optional_field');

        $requiredCol = reset($requiredCol);
        $optionalCol = reset($optionalCol);

        $this->assertSame(1, (int) $requiredCol['notnull']);
        $this->assertSame(0, (int) $optionalCol['notnull']);
    }

    public function test_creates_table_with_default_values(): void
    {
        $this->schema->create('defaults_test', function ($table): void {
            $table->increments('id');
            $table->string('status')->default('pending');
            $table->integer('priority')->default(0);
            $table->boolean('active')->default(true);
        });

        // Insert with defaults
        $this->connection->table('defaults_test')->insert(['id' => 1]);
        $row = $this->connection->table('defaults_test')->first();

        $this->assertSame('pending', $row['status']);
        $this->assertSame(0, (int) $row['priority']);
        $this->assertSame(1, (int) $row['active']);
    }

    public function test_creates_index_on_column(): void
    {
        $this->schema->create('indexed_table', function ($table): void {
            $table->increments('id');
            $table->string('email');
            $table->index('email');
        });

        $indexes = $this->connection->select("PRAGMA index_list('indexed_table')");
        $names = array_map(fn($idx) => $idx['name'], $indexes);

        $this->assertContains('indexed_table_email_index', $names);
    }

    public function test_creates_unique_index_on_column(): void
    {
        $this->schema->create('unique_table', function ($table): void {
            $table->increments('id');
            $table->string('email')->unique();
        });

        $indexes = $this->connection->select("PRAGMA index_list('unique_table')");
        $uniqueIndexes = array_filter($indexes, fn($idx) => $idx['unique'] == 1);

        $this->assertNotEmpty($uniqueIndexes);
    }

    public function test_creates_composite_index(): void
    {
        $this->schema->create('composite_index', function ($table): void {
            $table->increments('id');
            $table->integer('user_id');
            $table->string('type');
            $table->index(['user_id', 'type']);
        });

        $indexes = $this->connection->select("PRAGMA index_list('composite_index')");
        $names = array_map(fn($idx) => $idx['name'], $indexes);

        $this->assertContains('composite_index_user_id_type_index', $names);
    }

    public function test_creates_table_with_foreign_key(): void
    {
        $this->schema->create('authors', function ($table): void {
            $table->increments('id');
            $table->string('name');
        });

        $this->schema->create('books', function ($table): void {
            $table->increments('id');
            $table->integer('author_id');
            $table->string('title');
            $table->foreign('author_id', 'id', 'authors');
        });

        $foreignKeys = $this->connection->select("PRAGMA foreign_key_list('books')");

        $this->assertNotEmpty($foreignKeys);
        $this->assertSame('authors', $foreignKeys[0]['table']);
        $this->assertSame('id', $foreignKeys[0]['to']);
        $this->assertSame('author_id', $foreignKeys[0]['from']);
    }

    public function test_foreign_key_with_cascade_delete(): void
    {
        $this->schema->create('categories', function ($table): void {
            $table->increments('id');
            $table->string('name');
        });

        $this->schema->create('products', function ($table): void {
            $table->increments('id');
            $table->integer('category_id');
            $table->string('name');
            $table->foreign('category_id', 'id', 'categories')->onDelete('cascade');
        });

        // Insert test data
        $this->connection->table('categories')->insert(['name' => 'Electronics']);
        $this->connection->table('products')->insert(['category_id' => 1, 'name' => 'Phone']);

        // Delete category should cascade to products
        $this->connection->table('categories')->where('id', 1)->delete();

        $productCount = $this->connection->table('products')->count();
        $this->assertSame(0, $productCount);
    }

    public function test_alters_table_to_add_column(): void
    {
        $this->schema->create('users', function ($table): void {
            $table->increments('id');
            $table->string('name');
        });

        $this->schema->table('users', function ($table): void {
            $table->string('email')->nullable();
        });

        $this->assertColumnExists($this->connection, 'users', 'email');
    }

    public function test_renames_column(): void
    {
        $this->schema->create('posts', function ($table): void {
            $table->increments('id');
            $table->string('title');
        });

        $this->schema->table('posts', function ($table): void {
            $table->renameColumn('title', 'headline');
        });

        $this->assertColumnExists($this->connection, 'posts', 'headline');
        $this->assertColumnNotExists($this->connection, 'posts', 'title');
    }

    public function test_drops_table(): void
    {
        $this->schema->create('temp_table', function ($table): void {
            $table->increments('id');
        });

        $this->assertTableExists($this->connection, 'temp_table');

        $this->schema->drop('temp_table');

        $this->assertTableNotExists($this->connection, 'temp_table');
    }

    public function test_drop_if_exists_does_not_throw(): void
    {
        // Should not throw even if table doesn't exist
        $this->schema->dropIfExists('nonexistent_table');

        // Create and drop
        $this->schema->create('temp_table', function ($table): void {
            $table->increments('id');
        });

        $this->schema->dropIfExists('temp_table');

        $this->assertTableNotExists($this->connection, 'temp_table');
    }

    public function test_creates_table_with_soft_deletes(): void
    {
        $this->schema->create('soft_delete_test', function ($table): void {
            $table->increments('id');
            $table->string('name');
            $table->softDeletes();
        });

        $this->assertColumnExists($this->connection, 'soft_delete_test', 'deleted_at');
    }

    public function test_creates_table_if_not_exists(): void
    {
        $this->schema->createIfNotExists('idempotent_table', function ($table): void {
            $table->increments('id');
            $table->string('name');
        });

        // Second call should not throw
        $this->schema->createIfNotExists('idempotent_table', function ($table): void {
            $table->increments('id');
            $table->string('name');
        });

        $this->assertTableExists($this->connection, 'idempotent_table');
    }
}
