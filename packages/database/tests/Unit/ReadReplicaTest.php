<?php declare(strict_types=1);

namespace Lalaz\Database\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Lalaz\Database\Connection;
use Lalaz\Database\ConnectionManager;

class ReadReplicaTest extends TestCase
{
    public function test_routes_reads_to_replica_pool_when_configured(): void
    {
        $dbPath = tempnam(sys_get_temp_dir(), "lalaz_read_") ?: ":memory:";

        $config = [
            "driver" => "sqlite",
            "connections" => [
                "sqlite" => ["path" => $dbPath],
            ],
            "read" => [
                "enabled" => true,
                "driver" => "sqlite",
                "sticky" => false,
                "connections" => [["path" => $dbPath]],
            ],
        ];

        $events = [];
        $manager = new ConnectionManager($config);
        $manager->listenQuery(function (array $event) use (&$events): void {
            $events[] = $event;
        });

        $connection = new Connection($manager);
        $connection->query(
            "CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)",
        );
        $connection->insert("INSERT INTO users (name) VALUES (?)", ["Lalaz"]);

        $events = [];
        $rows = $connection->select("SELECT * FROM users");
        $this->assertCount(1, $rows);

        $selectEvents = array_values(
            array_filter(
                $events,
                fn($event) => ($event["type"] ?? null) === "select",
            ),
        );

        $this->assertNotEmpty($selectEvents);
        $this->assertSame("read", $selectEvents[0]["role"] ?? null);
        @unlink($dbPath);
    }

    public function test_sticks_reads_to_writer_when_sticky_mode_is_enabled(): void
    {
        $dbPath = tempnam(sys_get_temp_dir(), "lalaz_read_") ?: ":memory:";

        $config = [
            "driver" => "sqlite",
            "connections" => [
                "sqlite" => ["path" => $dbPath],
            ],
            "read" => [
                "enabled" => true,
                "driver" => "sqlite",
                "sticky" => true,
                "connections" => [["path" => $dbPath]],
            ],
        ];

        $events = [];
        $manager = new ConnectionManager($config);
        $manager->listenQuery(function (array $event) use (&$events): void {
            $events[] = $event;
        });

        $connection = new Connection($manager);
        $connection->query(
            "CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)",
        );
        $connection->insert("INSERT INTO users (name) VALUES (?)", ["Lalaz"]);

        $events = [];
        $rows = $connection->select("SELECT * FROM users");
        $this->assertCount(1, $rows);

        $selectEvents = array_values(
            array_filter(
                $events,
                fn($event) => ($event["type"] ?? null) === "select",
            ),
        );

        $this->assertNotEmpty($selectEvents);
        $this->assertSame("write", $selectEvents[0]["role"] ?? null);
        @unlink($dbPath);
    }
}
