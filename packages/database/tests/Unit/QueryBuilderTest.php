<?php declare(strict_types=1);

namespace Lalaz\Database\Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;
use Lalaz\Database\Connection;
use Lalaz\Database\ConnectionManager;
use Lalaz\Database\Contracts\ConnectorInterface;
use Lalaz\Database\Query\QueryBuilder;

class QueryBuilderTest extends TestCase
{
    public function test_builds_select_update_delete_statements_fluently(): void
    {
        $config = [
            "driver" => "sqlite",
            "connections" => ["sqlite" => ["path" => ":memory:"]],
        ];

        $manager = new ConnectionManager($config);
        $connection = new Connection($manager);
        $pdo = $connection->getPdo();

        $pdo->exec(
            "CREATE TABLE posts (id INTEGER PRIMARY KEY, title TEXT, views INT)",
        );

        $connection->table("posts")->insert(["title" => "Hello", "views" => 10]);
        $connection->table("posts")->insert(["title" => "World", "views" => 5]);

        $top = $connection
            ->table("posts")
            ->select("title")
            ->where("views", ">", 6)
            ->orderBy("views", "desc")
            ->first();

        $this->assertArrayHasKey("title", $top);
        $this->assertSame("Hello", $top["title"]);

        $connection
            ->table("posts")
            ->where("title", "=", "World")
            ->update(["views" => 8]);

        $count = $connection->table("posts")->where("views", "<", 7)->delete();

        $this->assertSame(0, $count);
    }

    public function test_handles_subqueries_joins_and_column_comparisons(): void
    {
        $config = [
            "driver" => "sqlite",
            "connections" => ["sqlite" => ["path" => ":memory:"]],
        ];

        $manager = new ConnectionManager($config);
        $connection = new Connection($manager);
        $pdo = $connection->getPdo();

        $pdo->exec("CREATE TABLE posts (id INTEGER PRIMARY KEY, title TEXT)");
        $pdo->exec(
            "CREATE TABLE comments (id INTEGER PRIMARY KEY, post_id INT, body TEXT)",
        );

        $connection->table("posts")->insert(["title" => "Hello"]);
        $connection->table("posts")->insert(["title" => "World"]);

        $connection->table("comments")->insert(["post_id" => 1, "body" => "First"]);
        $connection
            ->table("comments")
            ->insert(["post_id" => 1, "body" => "Second"]);
        $connection->table("comments")->insert(["post_id" => 2, "body" => "Only"]);

        $popular = $connection
            ->table("posts as p")
            ->select("p.title")
            ->selectSub(function (QueryBuilder $query): void {
                $query
                    ->from("comments as c")
                    ->selectRaw("count(*)")
                    ->whereColumn("c.post_id", "p.id");
            }, "comments_count")
            ->leftJoinSub(
                function (QueryBuilder $query): void {
                    $query
                        ->from("comments")
                        ->select("post_id")
                        ->selectRaw("count(*) as total")
                        ->groupBy("post_id");
                },
                "comment_totals",
                function ($join): void {
                    $join->on("comment_totals.post_id", "=", "p.id");
                },
            )
            ->whereIn("p.id", function (QueryBuilder $query): void {
                $query->from("comments")->select("post_id");
            })
            ->whereExists(function (QueryBuilder $query): void {
                $query
                    ->from("comments as c")
                    ->selectRaw("1")
                    ->whereColumn("c.post_id", "p.id");
            })
            ->orderByDesc("comments_count")
            ->first();

        $this->assertNotNull($popular);
        $this->assertSame("Hello", $popular["title"]);
        $this->assertSame(2, (int) $popular["comments_count"]);
    }

    public function test_performs_complex_joins_with_grouping_and_ordering(): void
    {
        $config = [
            "driver" => "sqlite",
            "connections" => ["sqlite" => ["path" => ":memory:"]],
        ];

        $manager = new ConnectionManager($config);
        $connection = new Connection($manager);
        $pdo = $connection->getPdo();

        $pdo->exec("CREATE TABLE authors (id INTEGER PRIMARY KEY, name TEXT)");
        $pdo->exec(
            "CREATE TABLE posts (id INTEGER PRIMARY KEY, author_id INT, published INT DEFAULT 0)",
        );

        $connection
            ->table("authors")
            ->insert([["name" => "Ada"], ["name" => "Bob"], ["name" => "Cathy"]]);

        $connection
            ->table("posts")
            ->insert([
                ["author_id" => 1, "published" => 1],
                ["author_id" => 1, "published" => 1],
                ["author_id" => 1, "published" => 0],
                ["author_id" => 2, "published" => 1],
                ["author_id" => 2, "published" => 0],
            ]);

        $leaders = $connection
            ->table("authors")
            ->select("authors.name")
            ->selectRaw("count(posts.id) as total_posts")
            ->selectRaw("sum(posts.published) as published_count")
            ->join("posts", "posts.author_id", "=", "authors.id")
            ->groupBy("authors.name")
            ->having("total_posts", ">=", 2)
            ->orderByDesc("total_posts")
            ->orderBy("authors.name")
            ->get();

        $this->assertCount(2, $leaders);
        $this->assertSame("Ada", $leaders[0]["name"]);
        $this->assertSame(3, (int) $leaders[0]["total_posts"]);
        $this->assertSame(2, (int) $leaders[1]["total_posts"]);
    }

    public function test_paginates_and_reorders_datasets_consistently(): void
    {
        $config = [
            "driver" => "sqlite",
            "connections" => ["sqlite" => ["path" => ":memory:"]],
        ];

        $manager = new ConnectionManager($config);
        $connection = new Connection($manager);
        $pdo = $connection->getPdo();

        $pdo->exec("CREATE TABLE logs (id INTEGER PRIMARY KEY, channel TEXT)");

        foreach (range(1, 10) as $index) {
            $connection
                ->table("logs")
                ->insert(["channel" => $index % 2 === 0 ? "api" : "jobs"]);
        }

        $page = $connection
            ->table("logs")
            ->orderBy("id")
            ->forPage(2, 3)
            ->pluck("id");

        $this->assertSame([4, 5, 6], $page);

        $latest = $connection
            ->table("logs")
            ->orderByDesc("id")
            ->limit(2)
            ->pluck("id");

        $this->assertSame([10, 9], $latest);
    }

    public function test_supports_range_comparisons_having_clauses_pluck_helpers_and_increments(): void
    {
        $config = [
            "driver" => "sqlite",
            "connections" => ["sqlite" => ["path" => ":memory:"]],
        ];

        $manager = new ConnectionManager($config);
        $connection = new Connection($manager);
        $pdo = $connection->getPdo();

        $pdo->exec(
            "CREATE TABLE price_ranges (id INTEGER PRIMARY KEY, price INT, min_price INT, max_price INT)",
        );
        $pdo->exec(
            "CREATE TABLE votes (id INTEGER PRIMARY KEY, post_id INT, score INT)",
        );

        $connection
            ->table("price_ranges")
            ->insert([
                ["price" => 10, "min_price" => 5, "max_price" => 15],
                ["price" => 20, "min_price" => 1, "max_price" => 5],
                ["price" => 8, "min_price" => 7, "max_price" => 12],
            ]);

        $connection
            ->table("votes")
            ->insert([
                ["post_id" => 1, "score" => 3],
                ["post_id" => 1, "score" => 3],
                ["post_id" => 2, "score" => 10],
                ["post_id" => 3, "score" => 4],
            ]);

        $matching = $connection
            ->table("price_ranges")
            ->whereBetweenColumns("price", ["min_price", "max_price"])
            ->pluck("id");

        $this->assertSame([1, 3], array_map("intval", $matching));

        $moderateTotals = $connection
            ->table("votes")
            ->select("post_id")
            ->selectRaw("sum(score) as total")
            ->groupBy("post_id")
            ->havingBetween("total", [5, 7])
            ->pluck("total", "post_id");

        $this->assertSame([1 => 6], array_map("intval", $moderateTotals));

        $connection
            ->table("price_ranges")
            ->where("id", 1)
            ->increment("price", 5, ["min_price" => 4]);

        $this->assertSame(
            15,
            $connection->table("price_ranges")->where("id", 1)->value("price"),
        );
        $this->assertSame(
            4,
            $connection
                ->table("price_ranges")
                ->where("id", 1)
                ->value("min_price"),
        );
    }

    public function test_can_reorder_clauses_and_apply_locks_when_compiling_sql(): void
    {
        $config = [
            "driver" => "sqlite",
            "connections" => ["sqlite" => ["path" => ":memory:"]],
        ];

        $manager = new ConnectionManager($config);
        $connection = new Connection($manager);
        $pdo = $connection->getPdo();

        $pdo->exec("CREATE TABLE posts (id INTEGER PRIMARY KEY, title TEXT)");

        $query = $connection
            ->table("posts")
            ->orderBy("title")
            ->lockForUpdate()
            ->reorder()
            ->sharedLock();

        $this->assertSame('select * from "posts" lock in share mode', $query->toSql());
    }

    public function test_uses_mysql_grammar_for_quoting_and_share_locks(): void
    {
        $manager = new ConnectionManager(
            [
                "driver" => "mysql",
                "connections" => ["mysql" => []],
            ],
            connectors: ["mysql" => new InMemoryConnector()],
        );

        $connection = new Connection($manager);

        $sql = $connection
            ->table("users as u")
            ->select("u.id")
            ->sharedLock()
            ->toSql();

        $this->assertSame(
            "select `u`.`id` from `users` as `u` lock in share mode",
            $sql,
        );
    }

    public function test_uses_postgres_grammar_for_shared_locks(): void
    {
        $manager = new ConnectionManager(
            [
                "driver" => "postgres",
                "connections" => ["postgres" => []],
            ],
            connectors: ["postgres" => new InMemoryConnector()],
        );

        $connection = new Connection($manager);

        $sql = $connection->table("reports")->sharedLock()->toSql();

        $this->assertSame('select * from "reports" for share', $sql);
    }
}

final class InMemoryConnector implements ConnectorInterface
{
    public function connect(array $config): PDO
    {
        return new PDO("sqlite::memory:");
    }
}
