<?php declare(strict_types=1);

namespace Lalaz\Database\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;
use Lalaz\Database\Connection;
use Lalaz\Database\ConnectionManager;
use Lalaz\Database\Schema\SchemaBuilder;
use Lalaz\Database\Tests\Integration\Support\IntegrationEnvironment;

#[Group("integration")]
class DatabaseIntegrationTest extends TestCase
{
    private static IntegrationEnvironment $env;

    public static function setUpBeforeClass(): void
    {
        self::$env = IntegrationEnvironment::instance();
        if (self::$env->isAvailable()) {
            self::$env->boot();
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$env->isAvailable()) {
            self::$env->shutdown();
        }
    }

    private function skipIfUnavailable(): void
    {
        if (!self::$env->isAvailable()) {
            $this->markTestSkipped(
                self::$env->skipReason() ?? "Integration environment unavailable."
            );
        }
    }

    public function test_executes_queries_against_mysql_using_the_fluent_builder(): void
    {
        $this->skipIfUnavailable();

        $config = self::$env->mysqlConfig();
        $manager = new ConnectionManager($config);
        $connection = new Connection($manager);
        $pdo = $connection->getPdo();

        $pdo->exec("DROP TABLE IF EXISTS posts");
        $pdo->exec('CREATE TABLE posts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            views INT NOT NULL DEFAULT 0
        )');

        $connection
            ->table("posts")
            ->insert([
                ["title" => "First", "views" => 1],
                ["title" => "Second", "views" => 5],
            ]);

        $top = $connection
            ->table("posts")
            ->select("title", "views")
            ->orderByDesc("views")
            ->first();

        $this->assertSame("Second", $top["title"] ?? null);
        $this->assertSame(5, (int) ($top["views"] ?? 0));

        $connection->table("posts")->where("title", "First")->increment("views", 2);

        $this->assertSame(
            3,
            (int) $connection
                ->table("posts")
                ->where("title", "First")
                ->value("views")
        );
    }

    public function test_executes_queries_against_postgres_using_the_fluent_builder(): void
    {
        $this->skipIfUnavailable();

        $config = self::$env->postgresConfig();
        $manager = new ConnectionManager($config);
        $connection = new Connection($manager);
        $pdo = $connection->getPdo();

        $pdo->exec("DROP TABLE IF EXISTS accounts");
        $pdo->exec('CREATE TABLE accounts (
            id SERIAL PRIMARY KEY,
            owner VARCHAR(255) NOT NULL,
            balance INT NOT NULL DEFAULT 0
        )');

        $connection
            ->table("accounts")
            ->insert([
                ["owner" => "alice", "balance" => 10],
                ["owner" => "bob", "balance" => 5],
            ]);

        $total = $connection->table("accounts")->sum("balance");
        $this->assertSame(15.0, (float) $total);

        $connection
            ->table("accounts")
            ->where("owner", "alice")
            ->decrement("balance", 3);

        $alice = $connection
            ->table("accounts")
            ->sharedLock()
            ->where("owner", "alice")
            ->first();

        $this->assertSame(7, (int) $alice["balance"]);
    }

    public function test_handles_complex_joins_and_pagination_on_mysql(): void
    {
        $this->skipIfUnavailable();

        $manager = new ConnectionManager(self::$env->mysqlConfig());
        $connection = new Connection($manager);
        $pdo = $connection->getPdo();

        $pdo->exec("DROP TABLE IF EXISTS authors");
        $pdo->exec(
            "CREATE TABLE authors (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255))"
        );
        $pdo->exec("DROP TABLE IF EXISTS posts");
        $pdo->exec(
            "CREATE TABLE posts (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, author_id INT, published INT DEFAULT 0)"
        );

        $connection
            ->table("authors")
            ->insert([["name" => "Ada"], ["name" => "Bob"]]);

        $connection
            ->table("posts")
            ->insert([
                ["author_id" => 1, "published" => 1],
                ["author_id" => 1, "published" => 0],
                ["author_id" => 2, "published" => 1],
            ]);

        $authors = $connection
            ->table("authors")
            ->select("authors.name")
            ->selectRaw("count(posts.id) as total_posts")
            ->join("posts", "posts.author_id", "=", "authors.id")
            ->groupBy("authors.name")
            ->orderByDesc("total_posts")
            ->get();

        $this->assertGreaterThanOrEqual(1, (int) $authors[0]["total_posts"]);

        $page = $connection
            ->table("posts")
            ->orderBy("id")
            ->forPage(1, 2)
            ->pluck("id");

        $this->assertCount(2, $page);
    }

    public function test_handles_aggregation_and_pagination_on_postgres(): void
    {
        $this->skipIfUnavailable();

        $manager = new ConnectionManager(self::$env->postgresConfig());
        $connection = new Connection($manager);
        $pdo = $connection->getPdo();

        $pdo->exec("DROP TABLE IF EXISTS logs");
        $pdo->exec(
            "CREATE TABLE logs (id SERIAL PRIMARY KEY, channel VARCHAR(50))"
        );

        foreach (range(1, 6) as $index) {
            $connection
                ->table("logs")
                ->insert(["channel" => $index <= 3 ? "api" : "jobs"]);
        }

        $counts = $connection
            ->table("logs")
            ->select("channel")
            ->selectRaw("count(*) as total")
            ->groupBy("channel")
            ->orderByDesc("total")
            ->get();

        $this->assertCount(2, $counts);

        $page = $connection
            ->table("logs")
            ->orderBy("id")
            ->forPage(2, 2)
            ->pluck("id");

        $this->assertSame([3, 4], $page);
    }

    public function test_enforces_lockForUpdate_semantics_on_postgres(): void
    {
        $this->skipIfUnavailable();

        $managerOne = new ConnectionManager(self::$env->postgresConfig());
        $managerTwo = new ConnectionManager(self::$env->postgresConfig());

        $locked = new Connection($managerOne);
        $contender = new Connection($managerTwo);

        $pdoOne = $locked->getPdo();
        $pdoTwo = $contender->getPdo();

        $pdoOne->exec("DROP TABLE IF EXISTS lockers");
        $pdoOne->exec(
            "CREATE TABLE lockers (id SERIAL PRIMARY KEY, status INT NOT NULL)"
        );
        $locked->table("lockers")->insert(["status" => 1]);

        $pdoOne->beginTransaction();
        $locked->table("lockers")->where("id", 1)->lockForUpdate()->first();

        $pdoTwo->exec("SET lock_timeout TO '100ms'");

        $failed = false;

        try {
            $contender
                ->table("lockers")
                ->where("id", 1)
                ->update(["status" => 2]);
        } catch (\PDOException $exception) {
            $failed = true;
            $this->assertSame("55P03", $exception->getCode());
        } finally {
            $pdoOne->rollBack();
        }

        $this->assertTrue($failed);
    }

    public function test_wraps_operations_in_transactions_on_mysql(): void
    {
        $this->skipIfUnavailable();

        $manager = new ConnectionManager(self::$env->mysqlConfig());
        $connection = new Connection($manager);
        $pdo = $connection->getPdo();

        $pdo->exec("DROP TABLE IF EXISTS tx_tests");
        $pdo->exec(
            "CREATE TABLE tx_tests (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, value INT NOT NULL)"
        );

        try {
            $connection->transaction(function (Connection $conn): void {
                $conn->table("tx_tests")->insert(["value" => 10]);
                throw new \RuntimeException("boom");
            });
        } catch (\RuntimeException $e) {
            // swallow for assertion
        }

        $this->assertSame(0, $connection->table("tx_tests")->count());

        $connection->transaction(function (Connection $conn): void {
            $conn->table("tx_tests")->insert(["value" => 20]);
        });

        $this->assertSame(1, $connection->table("tx_tests")->count());
    }

    public function test_drops_indexes_and_foreign_keys_on_mysql(): void
    {
        $this->skipIfUnavailable();

        $manager = new ConnectionManager(self::$env->mysqlConfig());
        $connection = new Connection($manager);
        $schema = new SchemaBuilder($connection, $manager);
        $pdo = $connection->getPdo();

        $pdo->exec("DROP TABLE IF EXISTS posts");
        $pdo->exec("DROP TABLE IF EXISTS authors");

        $schema->create("authors", function ($table): void {
            $table->increments("id");
        });

        $schema->create("posts", function ($table): void {
            $table->increments("id");
            $table->integer("author_id")->unsigned();
            $table->string("title");
            $table->index("title");
            $table->foreign("author_id", "id", "authors")->onDelete("cascade");
        });

        $schema->table("posts", function ($table): void {
            $table->dropIndex("posts_title_index");
            $table->dropForeign("author_id_foreign");
        });

        $indexes = $connection->select(
            "SHOW INDEX FROM posts WHERE Key_name != 'PRIMARY'"
        );
        $this->assertEmpty($indexes);

        $constraints = $connection->select(
            "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_NAME='posts' AND CONSTRAINT_TYPE='FOREIGN KEY'"
        );
        $this->assertEmpty($constraints);
    }

    public function test_drops_indexes_and_foreign_keys_on_postgres(): void
    {
        $this->skipIfUnavailable();

        $manager = new ConnectionManager(self::$env->postgresConfig());
        $connection = new Connection($manager);
        $schema = new SchemaBuilder($connection, $manager);
        $pdo = $connection->getPdo();

        $pdo->exec("DROP TABLE IF EXISTS posts");
        $pdo->exec("DROP TABLE IF EXISTS authors");

        $schema->create("authors", function ($table): void {
            $table->increments("id");
        });

        $schema->create("posts", function ($table): void {
            $table->increments("id");
            $table->integer("author_id");
            $table->string("title");
            $table->index("title");
            $table->foreign("author_id", "id", "authors")->onDelete("cascade");
        });

        $schema->table("posts", function ($table): void {
            $table->dropIndex("posts_title_index");
            $table->dropForeign("author_id_foreign");
        });

        $indexes = $connection->select(
            "SELECT indexname FROM pg_indexes WHERE tablename='posts' AND indexname NOT LIKE 'posts_pkey'"
        );
        $this->assertEmpty($indexes);

        $constraints = $connection->select(
            "SELECT conname FROM pg_constraint WHERE conrelid = 'posts'::regclass AND contype='f'"
        );
        $this->assertEmpty($constraints);
    }
}
