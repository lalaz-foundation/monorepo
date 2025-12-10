<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Common;

/**
 * Mock model with findById() but no find().
 */
class MockModelWithFindById
{
    public static ?object $findByIdResult = null;
    public static mixed $lastFindByIdArg = null;

    public static function reset(): void
    {
        self::$findByIdResult = null;
        self::$lastFindByIdArg = null;
    }

    public static function findById(mixed $id): ?object
    {
        self::$lastFindByIdArg = $id;
        return self::$findByIdResult;
    }
}
