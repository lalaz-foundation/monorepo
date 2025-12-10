<?php

declare(strict_types=1);

namespace Lalaz\Cache\Stores;

use Lalaz\Cache\CacheException;
use Lalaz\Cache\Contracts\CacheStoreInterface;

final class ApcuStore implements CacheStoreInterface
{
    public function __construct(private string $prefix = 'lalaz_')
    {
        if (!extension_loaded('apcu')) {
            throw new CacheException(
                'APCu extension is required for apcu cache store.',
            );
        }

        if (!function_exists('apcu_enabled') || !apcu_enabled()) {
            throw new CacheException('APCu is not enabled.');
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = apcu_fetch($this->prefix . $key, $success);
        return $success ? $value : $default;
    }

    public function set(
        string $key,
        mixed $value,
        int|\DateInterval|null $ttl = null,
    ): bool {
        $seconds = $ttl === null ? 0 : $this->ttlSeconds($ttl);
        if ($seconds < 0) {
            return $this->delete($key);
        }

        return (bool) apcu_store($this->prefix . $key, $value, $seconds);
    }

    public function has(string $key): bool
    {
        return apcu_exists($this->prefix . $key);
    }

    public function delete(string $key): bool
    {
        return (bool) apcu_delete($this->prefix . $key);
    }

    public function clear(): bool
    {
        $iterator = new \APCUIterator(
            '/^' . preg_quote($this->prefix, '/') . '/',
        );

        foreach ($iterator as $entry) {
            apcu_delete($entry['key']);
        }

        return true;
    }

    public function remember(
        string $key,
        int|\DateInterval|null $ttl,
        callable $callback,
    ): mixed {
        $value = $this->get($key);
        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }

    public function forever(string $key, mixed $value): bool
    {
        return $this->set($key, $value, null);
    }

    private function ttlSeconds(int|\DateInterval $ttl): int
    {
        if ($ttl instanceof \DateInterval) {
            $now = new \DateTimeImmutable();
            return $now->add($ttl)->getTimestamp() - $now->getTimestamp();
        }

        return $ttl;
    }
}
