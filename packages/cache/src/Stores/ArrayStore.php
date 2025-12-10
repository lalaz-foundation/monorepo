<?php

declare(strict_types=1);

namespace Lalaz\Cache\Stores;

use Lalaz\Cache\Contracts\CacheStoreInterface;

/**
 * In-memory cache for a single PHP process.
 */
final class ArrayStore implements CacheStoreInterface
{
    public function __construct(private string $prefix = '')
    {
    }

    /** @var array<string, mixed> */
    private array $items = [];

    /** @var array<string, int|null> */
    private array $expirations = [];

    public function get(string $key, mixed $default = null): mixed
    {
        $internal = $this->internalKey($key);

        if (!array_key_exists($internal, $this->items)) {
            return $default;
        }

        if ($this->isExpired($internal)) {
            $this->delete($key);
            return $default;
        }

        return $this->items[$internal];
    }

    public function set(
        string $key,
        mixed $value,
        int|\DateInterval|null $ttl = null,
    ): bool {
        $internal = $this->internalKey($key);
        $expiresAt = $this->normalizeTtl($ttl);

        if ($expiresAt !== null && $expiresAt <= time()) {
            unset($this->items[$internal], $this->expirations[$internal]);
            return true;
        }

        $this->items[$internal] = $value;
        $this->expirations[$internal] = $expiresAt;
        return true;
    }

    public function has(string $key): bool
    {
        $internal = $this->internalKey($key);

        if (!array_key_exists($internal, $this->items)) {
            return false;
        }

        if ($this->isExpired($internal)) {
            $this->delete($key);
            return false;
        }

        return true;
    }

    public function delete(string $key): bool
    {
        $internal = $this->internalKey($key);
        $existed = array_key_exists($internal, $this->items);
        unset($this->items[$internal], $this->expirations[$internal]);
        return $existed;
    }

    public function clear(): bool
    {
        $this->items = [];
        $this->expirations = [];
        return true;
    }

    public function remember(
        string $key,
        int|\DateInterval|null $ttl,
        callable $callback,
    ): mixed {
        $existing = $this->get($key, null);
        if ($existing !== null || $this->has($key)) {
            return $existing;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }

    public function forever(string $key, mixed $value): bool
    {
        return $this->set($key, $value, null);
    }

    private function internalKey(string $key): string
    {
        return $this->prefix . $key;
    }

    private function isExpired(string $internalKey): bool
    {
        if (!array_key_exists($internalKey, $this->expirations)) {
            return false;
        }

        $expiresAt = $this->expirations[$internalKey];
        if ($expiresAt === null) {
            return false;
        }

        return $expiresAt < time();
    }

    private function normalizeTtl(int|\DateInterval|null $ttl): ?int
    {
        if ($ttl === null) {
            return null;
        }

        if ($ttl instanceof \DateInterval) {
            return (new \DateTimeImmutable())->add($ttl)->getTimestamp();
        }

        if ($ttl <= 0) {
            return time() - 1;
        }

        return time() + $ttl;
    }
}
