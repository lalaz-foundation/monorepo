<?php

declare(strict_types=1);

namespace Lalaz\Database\Tests\Unit;

use Lalaz\Database\Tests\Common\DatabaseUnitTestCase;
use Lalaz\Database\Schema\Blueprint;
use Lalaz\Database\Schema\ColumnDefinition;
use Lalaz\Database\Schema\ForeignDefinition;

class BlueprintTest extends DatabaseUnitTestCase
{
    public function test_creates_blueprint_with_table_name(): void
    {
        $blueprint = new Blueprint('users');

        $this->assertSame('users', $blueprint->table());
        $this->assertFalse($blueprint->isCreating());
    }

    public function test_marks_blueprint_as_creating(): void
    {
        $blueprint = new Blueprint('users');
        $blueprint->creating();

        $this->assertTrue($blueprint->isCreating());
    }

    public function test_adds_increments_column(): void
    {
        $blueprint = new Blueprint('users');
        $column = $blueprint->increments('id');

        $this->assertInstanceOf(ColumnDefinition::class, $column);
        $this->assertSame('id', $column->name);
        $this->assertSame('increments', $column->type);
        $this->assertTrue($column->primary);
        $this->assertTrue($column->autoIncrement);
        $this->assertTrue($column->unsigned);
        $this->assertFalse($column->nullable);
    }

    public function test_adds_integer_column(): void
    {
        $blueprint = new Blueprint('posts');
        $column = $blueprint->integer('user_id');

        $this->assertSame('user_id', $column->name);
        $this->assertSame('integer', $column->type);
        $this->assertFalse($column->primary);
    }

    public function test_adds_big_integer_column(): void
    {
        $blueprint = new Blueprint('posts');
        $column = $blueprint->bigInteger('views');

        $this->assertSame('views', $column->name);
        $this->assertSame('bigInteger', $column->type);
    }

    public function test_adds_string_column_with_default_length(): void
    {
        $blueprint = new Blueprint('users');
        $column = $blueprint->string('name');

        $this->assertSame('name', $column->name);
        $this->assertSame('string', $column->type);
        $this->assertSame(255, $column->length);
    }

    public function test_adds_string_column_with_custom_length(): void
    {
        $blueprint = new Blueprint('users');
        $column = $blueprint->string('phone', 20);

        $this->assertSame('phone', $column->name);
        $this->assertSame(20, $column->length);
    }

    public function test_adds_text_column(): void
    {
        $blueprint = new Blueprint('posts');
        $column = $blueprint->text('body');

        $this->assertSame('body', $column->name);
        $this->assertSame('text', $column->type);
    }

    public function test_adds_boolean_column(): void
    {
        $blueprint = new Blueprint('users');
        $column = $blueprint->boolean('is_active');

        $this->assertSame('is_active', $column->name);
        $this->assertSame('boolean', $column->type);
    }

    public function test_adds_timestamp_column(): void
    {
        $blueprint = new Blueprint('posts');
        $column = $blueprint->timestamp('published_at');

        $this->assertSame('published_at', $column->name);
        $this->assertSame('timestamp', $column->type);
    }

    public function test_adds_timestamps_columns(): void
    {
        $blueprint = new Blueprint('users');
        $blueprint->timestamps();

        $columns = $blueprint->getColumns();
        $names = array_map(fn($col) => $col->name, $columns);

        $this->assertContains('created_at', $names);
        $this->assertContains('updated_at', $names);
    }

    public function test_adds_soft_deletes_column(): void
    {
        $blueprint = new Blueprint('users');
        $blueprint->softDeletes();

        $columns = $blueprint->getColumns();
        $names = array_map(fn($col) => $col->name, $columns);

        $this->assertContains('deleted_at', $names);
    }

    public function test_adds_soft_deletes_with_custom_column_name(): void
    {
        $blueprint = new Blueprint('users');
        $blueprint->softDeletes('removed_at');

        $columns = $blueprint->getColumns();
        $names = array_map(fn($col) => $col->name, $columns);

        $this->assertContains('removed_at', $names);
    }

    public function test_adds_uuid_column(): void
    {
        $blueprint = new Blueprint('users');
        $column = $blueprint->uuid('uuid');

        $this->assertSame('uuid', $column->name);
        $this->assertSame('uuid', $column->type);
        $this->assertSame(36, $column->length);
    }

    public function test_adds_json_column(): void
    {
        $blueprint = new Blueprint('settings');
        $column = $blueprint->json('data');

        $this->assertSame('data', $column->name);
        $this->assertSame('json', $column->type);
    }

    public function test_adds_index_with_auto_generated_name(): void
    {
        $blueprint = new Blueprint('users');
        $blueprint->index('email');

        $indexes = $blueprint->getIndexes();

        $this->assertCount(1, $indexes);
        $this->assertSame(['email'], $indexes[0]['columns']);
        $this->assertSame('users_email_index', $indexes[0]['name']);
        $this->assertFalse($indexes[0]['unique']);
    }

    public function test_adds_index_with_custom_name(): void
    {
        $blueprint = new Blueprint('users');
        $blueprint->index('email', 'idx_users_email');

        $indexes = $blueprint->getIndexes();

        $this->assertSame('idx_users_email', $indexes[0]['name']);
    }

    public function test_adds_composite_index(): void
    {
        $blueprint = new Blueprint('posts');
        $blueprint->index(['user_id', 'created_at']);

        $indexes = $blueprint->getIndexes();

        $this->assertSame(['user_id', 'created_at'], $indexes[0]['columns']);
        $this->assertSame('posts_user_id_created_at_index', $indexes[0]['name']);
    }

    public function test_adds_unique_index(): void
    {
        $blueprint = new Blueprint('users');
        $blueprint->unique('email');

        $indexes = $blueprint->getIndexes();

        $this->assertSame('users_email_unique', $indexes[0]['name']);
        $this->assertTrue($indexes[0]['unique']);
    }

    public function test_renames_column(): void
    {
        $blueprint = new Blueprint('users');
        $blueprint->renameColumn('name', 'full_name');

        $renames = $blueprint->getRenames();

        $this->assertCount(1, $renames);
        $this->assertSame('name', $renames[0]['from']);
        $this->assertSame('full_name', $renames[0]['to']);
    }

    public function test_adds_foreign_key(): void
    {
        $blueprint = new Blueprint('posts');
        $foreign = $blueprint->foreign('user_id', 'id', 'users');

        $this->assertInstanceOf(ForeignDefinition::class, $foreign);
        $this->assertSame('user_id', $foreign->column);
        $this->assertSame('id', $foreign->references);
        $this->assertSame('users', $foreign->on);
    }

    public function test_adds_foreign_key_with_custom_name(): void
    {
        $blueprint = new Blueprint('posts');
        $foreign = $blueprint->foreign('user_id', 'id', 'users', 'fk_posts_users');

        $this->assertSame('fk_posts_users', $foreign->name);
    }

    public function test_drops_index_by_name(): void
    {
        $blueprint = new Blueprint('users');
        $blueprint->dropIndex('users_email_index');

        $this->assertContains('users_email_index', $blueprint->getDropIndexes());
    }

    public function test_drops_index_by_columns(): void
    {
        $blueprint = new Blueprint('users');
        $blueprint->dropIndex(['email']);

        $this->assertContains('users_email_index', $blueprint->getDropIndexes());
    }

    public function test_drops_unique_index(): void
    {
        $blueprint = new Blueprint('users');
        $blueprint->dropUnique(['email']);

        $this->assertContains('users_email_unique', $blueprint->getDropIndexes());
    }

    public function test_drops_foreign_key(): void
    {
        $blueprint = new Blueprint('posts');
        $blueprint->dropForeign('user_id_foreign');

        $this->assertContains('user_id_foreign', $blueprint->getDropForeigns());
    }

    public function test_gets_added_columns(): void
    {
        $blueprint = new Blueprint('users');
        $blueprint->increments('id');
        $blueprint->string('name');
        $blueprint->string('email')->change();

        $added = $blueprint->getAddedColumns();
        $names = array_map(fn($col) => $col->name, $added);

        $this->assertContains('id', $names);
        $this->assertContains('name', $names);
        $this->assertNotContains('email', $names);
    }

    public function test_gets_modified_columns(): void
    {
        $blueprint = new Blueprint('users');
        $blueprint->increments('id');
        $blueprint->string('name');
        $blueprint->string('email')->change();

        $modified = $blueprint->getModifiedColumns();
        $names = array_map(fn($col) => $col->name, $modified);

        $this->assertNotContains('id', $names);
        $this->assertNotContains('name', $names);
        $this->assertContains('email', $names);
    }

    public function test_gets_all_columns(): void
    {
        $blueprint = new Blueprint('users');
        $blueprint->increments('id');
        $blueprint->string('name');

        $columns = $blueprint->getColumns();

        $this->assertCount(2, $columns);
    }
}
