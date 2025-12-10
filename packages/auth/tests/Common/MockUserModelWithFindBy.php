<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Common;

/**
 * Mock model with findBy() method (returns array).
 */
class MockUserModelWithFindBy
{
    public static array $findByResult = [];
    public static ?array $lastFindByConditions = null;

    public static function reset(): void
    {
        self::$findByResult = [];
        self::$lastFindByConditions = null;
    }

    public static function findBy(array $conditions): array
    {
        self::$lastFindByConditions = $conditions;
        return self::$findByResult;
    }
}
