<?php

declare(strict_types=1);

namespace Lalaz\Database\Tests\Integration;

use Lalaz\Database\Tests\Common\DatabaseIntegrationTestCase;

class TransactionTest extends DatabaseIntegrationTestCase
{
    public function test_transaction_commits_on_success(): void
    {
        $this->createStandardTestTables();

        $this->connection->transaction(function ($conn) {
            $conn->table('users')->insert(['name' => 'Test', 'email' => 'test@example.com']);
        });

        $count = $this->connection->table('users')->count();
        $this->assertSame(1, $count);
    }

    public function test_transaction_rolls_back_on_exception(): void
    {
        $this->createStandardTestTables();

        try {
            $this->connection->transaction(function ($conn) {
                $conn->table('users')->insert(['name' => 'Test', 'email' => 'test@example.com']);
                throw new \RuntimeException('Intentional failure');
            });
        } catch (\RuntimeException $e) {
            // Expected
        }

        $count = $this->connection->table('users')->count();
        $this->assertSame(0, $count);
    }

    public function test_begin_and_commit_transaction_manually(): void
    {
        $this->createStandardTestTables();

        $this->connection->beginTransaction();
        $this->connection->table('users')->insert(['name' => 'Test', 'email' => 'test@example.com']);
        $this->connection->commit();

        $count = $this->connection->table('users')->count();
        $this->assertSame(1, $count);
    }

    public function test_begin_and_rollback_transaction_manually(): void
    {
        $this->createStandardTestTables();

        $this->connection->beginTransaction();
        $this->connection->table('users')->insert(['name' => 'Test', 'email' => 'test@example.com']);
        $this->connection->rollBack();

        $count = $this->connection->table('users')->count();
        $this->assertSame(0, $count);
    }

    public function test_transaction_returns_callback_result(): void
    {
        $this->createStandardTestTables();

        $result = $this->connection->transaction(function ($conn) {
            $conn->table('users')->insert(['name' => 'Test', 'email' => 'test@example.com']);
            return 'success';
        });

        $this->assertSame('success', $result);
    }

    public function test_multiple_inserts_in_transaction(): void
    {
        $this->createStandardTestTables();

        $this->connection->transaction(function ($conn) {
            $conn->table('users')->insert(['name' => 'User 1', 'email' => 'user1@example.com']);
            $conn->table('users')->insert(['name' => 'User 2', 'email' => 'user2@example.com']);
            $conn->table('users')->insert(['name' => 'User 3', 'email' => 'user3@example.com']);
        });

        $count = $this->connection->table('users')->count();
        $this->assertSame(3, $count);
    }

    public function test_nested_begin_transaction_is_noop(): void
    {
        $this->createStandardTestTables();

        $this->connection->beginTransaction();
        $this->connection->table('users')->insert(['name' => 'Test', 'email' => 'test@example.com']);

        // Second begin should be a no-op
        $this->connection->beginTransaction();
        $this->connection->table('users')->insert(['name' => 'Test2', 'email' => 'test2@example.com']);

        $this->connection->commit();

        $count = $this->connection->table('users')->count();
        $this->assertSame(2, $count);
    }
}
