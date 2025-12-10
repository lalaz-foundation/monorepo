<?php

declare(strict_types=1);

namespace Lalaz\Storage;

use Lalaz\Storage\Contracts\StorageInterface;

/**
 * Storage Facade
 *
 * Provides static access to storage functionality.
 *
 * @method static string upload(string $path, string $localPath)
 * @method static string download(string $path)
 * @method static bool delete(string $path)
 * @method static bool exists(string $path)
 * @method static string getPublicUrl(string $path)
 * @method static int size(string $path)
 * @method static int lastModified(string $path)
 * @method static string|null mimeType(string $path)
 * @method static bool copy(string $from, string $to)
 * @method static bool move(string $from, string $to)
 * @method static string get(string $path)
 * @method static bool put(string $path, string $contents)
 * @method static bool append(string $path, string $contents)
 * @method static bool prepend(string $path, string $contents)
 * @method static array files(string $directory = '', bool $recursive = false)
 * @method static array directories(string $directory = '', bool $recursive = false)
 * @method static bool makeDirectory(string $path)
 * @method static bool deleteDirectory(string $directory, bool $recursive = false)
 *
 * @package lalaz/storage
 * @author  Elasticmind <ola@elasticmind.io>
 * @link    https://lalaz.dev
 */
class Storage
{
    /**
     * The storage manager instance.
     */
    protected static ?StorageManager $manager = null;

    /**
     * Set the storage manager instance.
     */
    public static function setManager(StorageManager $manager): void
    {
        self::$manager = $manager;
    }

    /**
     * Get the storage manager instance.
     */
    public static function getManager(): StorageManager
    {
        if (self::$manager === null) {
            self::$manager = new StorageManager();
        }

        return self::$manager;
    }

    /**
     * Get a storage driver instance by disk name.
     *
     * @param string|null $disk The disk name (null for default)
     */
    public static function disk(?string $disk = null): StorageInterface
    {
        return self::getManager()->disk($disk);
    }

    /**
     * Get the default driver instance.
     */
    public static function driver(): StorageInterface
    {
        return self::getManager()->getDriver();
    }

    /**
     * Reset the manager (useful for testing).
     */
    public static function reset(): void
    {
        self::$manager = null;
    }

    /**
     * Dynamically pass method calls to the default driver.
     *
     * @param string $method
     * @param array<int, mixed> $parameters
     * @return mixed
     */
    public static function __callStatic(string $method, array $parameters): mixed
    {
        return self::driver()->$method(...$parameters);
    }
}
