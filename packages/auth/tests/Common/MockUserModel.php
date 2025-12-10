<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Common;

/**
 * Mock model with find() method.
 */
class MockUserModel
{
    public static ?object $findResult = null;
    public static mixed $lastFindId = null;

    public static function reset(): void
    {
        self::$findResult = null;
        self::$lastFindId = null;
    }

    public static function find(mixed $id): ?object
    {
        self::$lastFindId = $id;
        return self::$findResult;
    }
}
