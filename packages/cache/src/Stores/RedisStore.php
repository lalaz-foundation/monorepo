<?php

declare(strict_types=1);

namespace Lalaz\Cache\Stores;

use Lalaz\Cache\CacheException;
use Lalaz\Cache\Contracts\CacheStoreInterface;

/**
 * Redis-backed cache store (supports phpredis or predis when available).
 */
final class RedisStore implements CacheStoreInterface
{
    private \Redis|\Predis\Client $client;
    private string $prefix;

    public function __construct(array $config, string $prefix = 'lalaz_')
    {
        $this->prefix = $prefix;

        if (extension_loaded('redis')) {
            $this->client = $this->createPhpRedis($config);
            return;
        }

        if (class_exists(\Predis\Client::class)) {
            $this->client = $this->createPredis($config);
            return;
        }

        throw new CacheException(
            'Redis store requires ext-redis or predis/predis.',
        );
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->client->get($this->prefix . $key);
        if ($value === false || $value === null) {
            return $default;
        }

        return $this->unserialize($value);
    }

    public function set(
        string $key,
        mixed $value,
        int|\DateInterval|null $ttl = null,
    ): bool {
        $key = $this->prefix . $key;
        $payload = $this->serialize($value);

        if ($ttl === null) {
            return (bool) $this->client->set($key, $payload);
        }

        $seconds = $this->ttlSeconds($ttl);
        if ($seconds <= 0) {
            return $this->delete($key);
        }

        return (bool) $this->client->setex($key, $seconds, $payload);
    }

    public function has(string $key): bool
    {
        return (bool) $this->client->exists($this->prefix . $key);
    }

    public function delete(string $key): bool
    {
        return (bool) $this->client->del($this->prefix . $key);
    }

    public function clear(): bool
    {
        $pattern = $this->prefix . '*';

        if ($this->client instanceof \Redis) {
            $it = null;
            while (
                ($keys = $this->client->scan($it, $pattern, 1000)) !== false
            ) {
                if (!empty($keys)) {
                    $this->client->del(...$keys);
                }
            }
            return true;
        }

        $iterator = $this->client->scan($pattern, 1000);
        foreach ($iterator as $keys) {
            if (!empty($keys)) {
                $this->client->del($keys);
            }
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

    private function createPhpRedis(array $config): \Redis
    {
        $redis = new \Redis();
        $redis->connect(
            $config['host'] ?? '127.0.0.1',
            $config['port'] ?? 6379,
            $config['timeout'] ?? 0.0,
        );

        if (!empty($config['password'])) {
            $redis->auth($config['password']);
        }

        if (isset($config['database'])) {
            $redis->select((int) $config['database']);
        }

        return $redis;
    }

    private function createPredis(array $config): \Predis\Client
    {
        return new \Predis\Client([
            'scheme' => 'tcp',
            'host' => $config['host'] ?? '127.0.0.1',
            'port' => $config['port'] ?? 6379,
            'password' => $config['password'] ?? null,
            'database' => $config['database'] ?? 0,
            'timeout' => $config['timeout'] ?? 0.0,
        ]);
    }

    private function serialize(mixed $value): string
    {
        return serialize($value);
    }

    private function unserialize(string $value): mixed
    {
        return unserialize($value);
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
