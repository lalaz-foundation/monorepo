<?php declare(strict_types=1);

namespace Lalaz\Database\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Lalaz\Database\Connection;
use Lalaz\Database\ConnectionManager;

class ConnectionManagerTest extends TestCase
{
    public function test_acquires_and_releases_sqlite_connections(): void
    {
        $config = [
            'driver' => 'sqlite',
            'connections' => [
                'sqlite' => ['path' => ':memory:'],
            ],
            'pool' => ['max' => 2],
        ];

        $manager = new ConnectionManager($config);
        $connection = new Connection($manager);

        $pdo = $connection->getPdo();
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec("INSERT INTO users (name) VALUES ('Lalaz')");

        $result = $connection->select('SELECT * FROM users');

        $this->assertCount(1, $result);
    }
}
