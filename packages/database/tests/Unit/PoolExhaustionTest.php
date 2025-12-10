<?php declare(strict_types=1);

namespace Lalaz\Database\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Lalaz\Database\ConnectionManager;

class PoolExhaustionTest extends TestCase
{
    public function test_throws_when_pool_is_exhausted_within_timeout(): void
    {
        $config = [
            "driver" => "sqlite",
            "connections" => [
                "sqlite" => ["path" => ":memory:"],
            ],
            "pool" => [
                "max" => 0,
                "timeout_ms" => 0,
            ],
        ];

        $manager = new ConnectionManager($config);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Connection pool exhausted");
        $manager->acquire();
    }
}
