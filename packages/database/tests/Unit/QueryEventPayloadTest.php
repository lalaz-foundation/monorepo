<?php declare(strict_types=1);

namespace Lalaz\Database\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Lalaz\Database\Connection;
use Lalaz\Database\ConnectionManager;

class QueryEventPayloadTest extends TestCase
{
    public function test_emits_query_events_with_role_and_driver(): void
    {
        $events = [];
        $config = [
            "driver" => "sqlite",
            "connections" => [
                "sqlite" => ["path" => ":memory:"],
            ],
        ];

        $manager = new ConnectionManager($config);
        $manager->listenQuery(function (array $event) use (&$events): void {
            $events[] = $event;
        });

        $connection = new Connection($manager);
        $connection->select("select 1 as one");

        $this->assertNotEmpty($events);
        $last = end($events);
        $this->assertSame("write", $last["role"] ?? null);
        $this->assertSame("sqlite", $last["driver"] ?? null);
        $this->assertSame("select", $last["type"] ?? null);
        $this->assertGreaterThan(0, $last["duration_ms"] ?? 0);
    }
}
