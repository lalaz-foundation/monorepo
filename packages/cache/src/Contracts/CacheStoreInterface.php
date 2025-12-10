<?php

declare(strict_types=1);

namespace Lalaz\Cache\Contracts;

interface CacheStoreInterface
{
    public function get(string $key, mixed $default = null): mixed;

    public function set(
        string $key,
        mixed $value,
        int|\DateInterval|null $ttl = null,
    ): bool;

    public function has(string $key): bool;

    public function delete(string $key): bool;

    public function clear(): bool;

    public function remember(
        string $key,
        int|\DateInterval|null $ttl,
        callable $callback,
    ): mixed;

    public function forever(string $key, mixed $value): bool;
}
