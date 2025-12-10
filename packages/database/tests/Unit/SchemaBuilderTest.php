<?php declare(strict_types=1);

namespace Lalaz\Database\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Lalaz\Database\Schema\SchemaBuilder;

class SchemaBuilderTest extends TestCase
{
    private SchemaBuilder $schema;
    private $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $components = sqlite_components();
        $this->schema = $components["schema"];
        $this->connection = $components["connection"];
    }

    public function test_it_creates_and_alters_tables_with_new_columns(): void
    {
        $this->schema->create("users", function ($table): void {
            $table->increments("id");
            $table->string("name");
        });

        $this->schema->table("users", function ($table): void {
            $table->string("email")->nullable();
        });

        $columns = $this->connection->select("PRAGMA table_info('users')");

        $names = array_map(fn(array $row): string => $row["name"], $columns);

        $this->assertContains("id", $names);
        $this->assertContains("name", $names);
        $this->assertContains("email", $names);
    }

    public function test_it_renames_columns_and_creates_indexes(): void
    {
        $this->schema->create("posts", function ($table): void {
            $table->increments("id");
            $table->string("title");
        });

        $this->schema->table("posts", function ($table): void {
            $table->renameColumn("title", "headline");
            $table->string("slug")->unique();
            $table->index("headline");
        });

        $columns = $this->connection->select("PRAGMA table_info('posts')");
        $names = array_map(fn(array $row): string => $row["name"], $columns);
        $this->assertContains("headline", $names);
        $this->assertContains("slug", $names);
        $this->assertNotContains("title", $names);

        $indexes = $this->connection->select("PRAGMA index_list('posts')");
        $indexNames = array_map(fn(array $row): string => $row["name"], $indexes);
        $this->assertContains("posts_slug_unique", $indexNames);
        $this->assertContains("posts_headline_index", $indexNames);
    }

    public function test_it_rebuilds_sqlite_tables_when_dropping_foreign_keys(): void
    {
        $this->schema->create("authors", function ($table): void {
            $table->increments("id");
        });

        $this->schema->create("articles", function ($table): void {
            $table->increments("id");
            $table->integer("author_id");
            $table->foreign("author_id", "id", "authors")->onDelete("cascade");
        });

        $this->schema->table("articles", function ($table): void {
            $table->dropForeign("author_id_foreign");
        });

        $fks = $this->connection->select("PRAGMA foreign_key_list('articles')");
        $this->assertEmpty($fks);
    }
}
