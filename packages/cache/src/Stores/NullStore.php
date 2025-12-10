<?php

declare(strict_types=1);

namespace Lalaz\Cache\Stores;

use Lalaz\Cache\Contracts\CacheStoreInterface;

/**
 * No-op cache store.
 */
final class NullStore implements CacheStoreInterface
{
    public function get(string $key, mixed $default = null): mixed
    {
        return $default;
    }

    public function set(
        string $key,
        mixed $value,
        int|\DateInterval|null $ttl = null,
    ): bool {
        return true;
    }

    public function has(string $key): bool
    {
        return false;
    }

    public function delete(string $key): bool
    {
        return true;
    }

    public function clear(): bool
    {
        return true;
    }

    public function remember(
        string $key,
        int|\DateInterval|null $ttl,
        callable $callback,
    ): mixed {
        return $callback();
    }

    public function forever(string $key, mixed $value): bool
    {
        return true;
    }
}
