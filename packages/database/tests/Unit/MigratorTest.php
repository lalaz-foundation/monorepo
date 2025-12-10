<?php declare(strict_types=1);

namespace Lalaz\Database\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Lalaz\Database\Connection;
use Lalaz\Database\Migrations\Migration;
use Lalaz\Database\Migrations\Migrator;

require_once __DIR__ . '/helpers.php';

class MigratorTest extends TestCase
{
    private array $components;
    private Migrator $migrator;
    private Connection $connection;
    private string $migrationDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->components = sqlite_components();
        $this->migrator = sqlite_migrator_from($this->components);
        $this->connection = $this->components["connection"];

        $this->migrationDir =
            sys_get_temp_dir() . "/lalaz_migrations_" . bin2hex(random_bytes(4));
        mkdir($this->migrationDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->migrationDir ?? "")) {
            array_map("unlink", glob($this->migrationDir . "/*.php") ?: []);
            @rmdir($this->migrationDir);
        }
        parent::tearDown();
    }

    public function test_it_runs_and_rolls_back_migrations(): void
    {
        $file = $this->migrationDir . "/20240101000000_create_users.php";
        file_put_contents(
            $file,
            <<<'PHP'
            <?php

            use Lalaz\Database\Migrations\Migration;
            use Lalaz\Database\Schema\SchemaBuilder;

            return new class extends Migration {
                public function up(SchemaBuilder $schema): void
                {
                    $schema->create('users', function ($table): void {
                        $table->increments('id');
                        $table->string('name');
                    });
                }

                public function down(SchemaBuilder $schema): void
                {
                    $schema->drop('users');
                }
            };
            PHP
            ,
        );

        $ran = $this->migrator->run([$this->migrationDir]);
        $this->assertContains("20240101000000_create_users", $ran);

        $tables = $this->connection->select(
            "SELECT name FROM sqlite_master WHERE type='table' AND name='users'",
        );
        $this->assertCount(1, $tables);

        $rolled = $this->migrator->rollback([$this->migrationDir]);
        $this->assertContains("20240101000000_create_users", $rolled);

        $tables = $this->connection->select(
            "SELECT name FROM sqlite_master WHERE type='table' AND name='users'",
        );
        $this->assertCount(0, $tables);
    }
}
