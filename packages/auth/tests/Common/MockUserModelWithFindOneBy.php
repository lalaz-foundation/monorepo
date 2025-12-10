<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Common;

/**
 * Mock model with findOneBy() method.
 */
class MockUserModelWithFindOneBy
{
    public static ?object $findOneByResult = null;
    public static ?array $lastFindOneByConditions = null;

    public static function reset(): void
    {
        self::$findOneByResult = null;
        self::$lastFindOneByConditions = null;
    }

    public static function findOneBy(array $conditions): ?object
    {
        self::$lastFindOneByConditions = $conditions;
        return self::$findOneByResult;
    }
}
