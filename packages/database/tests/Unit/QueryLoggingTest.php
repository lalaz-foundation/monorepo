<?php declare(strict_types=1);

namespace Lalaz\Database\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Lalaz\Database\Connection;
use Lalaz\Database\ConnectionManager;

class QueryLoggingTest extends TestCase
{
    private array $events = [];
    private ConnectionManager $manager;
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->events = [];

        $config = [
            "driver" => "sqlite",
            "connections" => [
                "sqlite" => [
                    "path" => ":memory:",
                    "options" => [],
                ],
            ],
        ];

        $this->manager = new ConnectionManager($config);
        $this->manager->listenQuery(function (array $event): void {
            $this->events[] = $event;
        });

        $this->connection = new Connection($this->manager);
        $pdo = $this->connection->getPdo();
        $pdo->exec("CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT)");
    }

    public function test_query_listeners_receive_profiling_info(): void
    {
        $this->connection
            ->table("posts")
            ->insert([["title" => "First"], ["title" => "Second"]]);

        $rows = $this->connection->table("posts")->select("title")->get();
        $this->assertCount(2, $rows);

        $this->assertNotEmpty($this->events);
        $last = end($this->events);
        $this->assertSame("select", $last["type"] ?? null);
        $this->assertGreaterThan(0, $last["duration_ms"] ?? 0);
        $this->assertStringContainsString("select", $last["sql"] ?? "");
    }
}
