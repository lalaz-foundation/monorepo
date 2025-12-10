<?php

declare(strict_types=1);

namespace Lalaz\Database\Tests\Common;

use Lalaz\Database\Connection;
use Lalaz\Database\ConnectionManager;
use Lalaz\Database\Migrations\Migration;
use Lalaz\Database\Migrations\MigrationRepository;
use Lalaz\Database\Migrations\Migrator;
use Lalaz\Database\Schema\SchemaBuilder;
use Lalaz\Database\Seeding\SeederRunner;

/**
 * Base test case for Database integration tests.
 *
 * Extends DatabaseUnitTestCase with additional setup for integration testing
 * including migrator, seeder, and transactional test support.
 */
abstract class DatabaseIntegrationTestCase extends DatabaseUnitTestCase
{
    protected ConnectionManager $manager;
    protected Connection $connection;
    protected SchemaBuilder $schema;
    protected MigrationRepository $migrationRepository;
    protected Migrator $migrator;
    protected string $migrationDir;

    protected function setUp(): void
    {
        parent::setUp();

        $components = $this->createSqliteComponents();
        $this->manager = $components['manager'];
        $this->connection = $components['connection'];
        $this->schema = $components['schema'];

        $this->migrationRepository = new MigrationRepository(
            $this->connection,
            $this->schema
        );

        $this->migrator = new Migrator(
            $this->schema,
            $this->migrationRepository,
            $this->connection
        );

        // Create temporary migration directory
        $this->migrationDir = sys_get_temp_dir() . '/lalaz_migrations_' . bin2hex(random_bytes(4));
        mkdir($this->migrationDir, 0777, true);
    }

    protected function tearDown(): void
    {
        // Clean up migration directory
        if (isset($this->migrationDir) && is_dir($this->migrationDir)) {
            array_map('unlink', glob($this->migrationDir . '/*.php') ?: []);
            @rmdir($this->migrationDir);
        }

        parent::tearDown();
    }

    /**
     * Create a migration file in the temp directory.
     *
     * @param string $name Migration name (e.g., 'create_users_table')
     * @param string $upCode PHP code for the up() method body
     * @param string $downCode PHP code for the down() method body
     * @return string Full path to the created migration file
     */
    protected function createMigration(string $name, string $upCode, string $downCode): string
    {
        $timestamp = date('YmdHis');
        $filename = "{$timestamp}_{$name}.php";
        $path = $this->migrationDir . '/' . $filename;

        $content = <<<PHP
<?php

use Lalaz\Database\Migrations\Migration;
use Lalaz\Database\Schema\SchemaBuilder;

return new class extends Migration {
    public function up(SchemaBuilder \$schema): void
    {
        {$upCode}
    }

    public function down(SchemaBuilder \$schema): void
    {
        {$downCode}
    }
};
PHP;

        file_put_contents($path, $content);
        return $path;
    }

    /**
     * Create a simple table migration.
     */
    protected function createTableMigration(string $table, array $columns = []): string
    {
        $columnsCode = '';
        foreach ($columns as $name => $type) {
            if ($type === 'id') {
                $columnsCode .= "\$table->increments('{$name}');\n            ";
            } elseif ($type === 'string') {
                $columnsCode .= "\$table->string('{$name}');\n            ";
            } elseif ($type === 'integer') {
                $columnsCode .= "\$table->integer('{$name}');\n            ";
            } elseif ($type === 'text') {
                $columnsCode .= "\$table->text('{$name}');\n            ";
            } elseif ($type === 'timestamps') {
                $columnsCode .= "\$table->timestamps();\n            ";
            }
        }

        if (empty($columnsCode)) {
            $columnsCode = "\$table->increments('id');\n            \$table->string('name');";
        }

        $upCode = "\$schema->create('{$table}', function (\$table): void {
            {$columnsCode}
        });";

        $downCode = "\$schema->drop('{$table}');";

        return $this->createMigration("create_{$table}_table", $upCode, $downCode);
    }

    /**
     * Run migrations in the test migration directory.
     *
     * @return array<int, string> List of executed migration names
     */
    protected function runMigrations(): array
    {
        return $this->migrator->run([$this->migrationDir]);
    }

    /**
     * Rollback the last batch of migrations.
     *
     * @return array<int, string> List of rolled back migration names
     */
    protected function rollbackMigrations(): array
    {
        return $this->migrator->rollback([$this->migrationDir]);
    }

    /**
     * Reset all migrations.
     *
     * @return array<int, string> List of reset migration names
     */
    protected function resetMigrations(): array
    {
        return $this->migrator->reset([$this->migrationDir]);
    }

    /**
     * Execute a callback within a database transaction.
     * The transaction will be rolled back after the callback executes.
     */
    protected function withinTransaction(callable $callback): mixed
    {
        $this->connection->beginTransaction();

        try {
            return $callback($this->connection, $this->schema);
        } finally {
            $this->connection->rollBack();
        }
    }

    /**
     * Create a test seeder runner.
     */
    protected function createSeederRunner(): SeederRunner
    {
        return new SeederRunner();
    }

    /**
     * Assert that migrations table exists.
     */
    protected function assertMigrationsTableExists(): void
    {
        $this->assertTableExists($this->connection, 'migrations');
    }

    /**
     * Assert that a migration was run.
     */
    protected function assertMigrationRan(string $migrationName): void
    {
        $migrations = $this->migrationRepository->all();
        $names = array_map(fn(array $row): string => $row['migration'], $migrations);
        $this->assertContains($migrationName, $names, "Migration '{$migrationName}' should have been run");
    }

    /**
     * Assert that a migration was not run.
     */
    protected function assertMigrationNotRan(string $migrationName): void
    {
        $migrations = $this->migrationRepository->all();
        $names = array_map(fn(array $row): string => $row['migration'], $migrations);
        $this->assertNotContains($migrationName, $names, "Migration '{$migrationName}' should not have been run");
    }

    /**
     * Get the count of rows in a table.
     */
    protected function getTableCount(string $table): int
    {
        return $this->connection->table($table)->count();
    }

    /**
     * Insert multiple rows and return their IDs.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, string>
     */
    protected function insertAndGetIds(string $table, array $rows): array
    {
        $ids = [];
        foreach ($rows as $row) {
            $id = $this->connection->table($table)->insertGetId($row);
            $ids[] = $id;
        }
        return $ids;
    }

    /**
     * Create standard test tables for integration tests.
     */
    protected function createStandardTestTables(): void
    {
        $this->schema->create('users', function ($table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->integer('age')->nullable();
            $table->timestamps();
        });

        $this->schema->create('posts', function ($table): void {
            $table->increments('id');
            $table->integer('user_id');
            $table->string('title');
            $table->text('body')->nullable();
            $table->integer('views')->default(0);
            $table->timestamps();
            $table->foreign('user_id', 'id', 'users')->onDelete('cascade');
        });

        $this->schema->create('comments', function ($table): void {
            $table->increments('id');
            $table->integer('post_id');
            $table->string('author');
            $table->text('content');
            $table->timestamps();
            $table->foreign('post_id', 'id', 'posts')->onDelete('cascade');
        });
    }

    /**
     * Seed standard test data.
     */
    protected function seedStandardTestData(): void
    {
        // Create users
        $this->connection->table('users')->insert([
            ['name' => 'Alice', 'email' => 'alice@example.com', 'age' => 30],
            ['name' => 'Bob', 'email' => 'bob@example.com', 'age' => 25],
            ['name' => 'Charlie', 'email' => 'charlie@example.com', 'age' => 35],
        ]);

        // Create posts
        $this->connection->table('posts')->insert([
            ['user_id' => 1, 'title' => 'First Post', 'body' => 'Content 1', 'views' => 100],
            ['user_id' => 1, 'title' => 'Second Post', 'body' => 'Content 2', 'views' => 50],
            ['user_id' => 2, 'title' => 'Bob\'s Post', 'body' => 'Bob content', 'views' => 75],
        ]);

        // Create comments
        $this->connection->table('comments')->insert([
            ['post_id' => 1, 'author' => 'Guest', 'content' => 'Great post!'],
            ['post_id' => 1, 'author' => 'Bob', 'content' => 'I agree'],
            ['post_id' => 2, 'author' => 'Charlie', 'content' => 'Nice work'],
        ]);
    }
}
