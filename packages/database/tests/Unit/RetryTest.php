<?php declare(strict_types=1);

namespace Lalaz\Database\Tests\Unit;

use PDO;
use PDOStatement;
use PDOException;
use PHPUnit\Framework\TestCase;
use Lalaz\Database\Connection;
use Lalaz\Database\ConnectionManager;

class RetryTest extends TestCase
{
    public function test_retries_configured_pdo_exceptions(): void
    {
        $this->markTestSkipped("Test needs refactoring - cannot extend final classes");
    }
}
