<?php

declare(strict_types=1);

namespace Lalaz\Cache;

use Closure;

/**
 * Lightweight per-request cache with hit/miss stats.
 */
final class PerRequestCache
{
    /** @var array<string, mixed> */
    private array $cache = [];

    /** @var array<string, int> */
    private array $hits = [];

    /** @var array<string, int> */
    private array $misses = [];

    private bool $enabled = true;

    public function remember(string $key, Closure $callback): mixed
    {
        if (!$this->enabled) {
            return $callback();
        }

        if ($this->has($key)) {
            $this->recordHit($key);
            return $this->cache[$key];
        }

        $this->recordMiss($key);
        return $this->cache[$key] = $callback();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->enabled) {
            return $default;
        }

        if ($this->has($key)) {
            $this->recordHit($key);
            return $this->cache[$key];
        }

        $this->recordMiss($key);
        return $default;
    }

    public function put(string $key, mixed $value): void
    {
        if ($this->enabled) {
            $this->cache[$key] = $value;
        }
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->cache);
    }

    public function forget(string $key): void
    {
        unset($this->cache[$key], $this->hits[$key], $this->misses[$key]);
    }

    public function flush(): void
    {
        $this->cache = [];
        $this->hits = [];
        $this->misses = [];
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->cache;
    }

    /**
     * @return array{
     *  total_items:int,
     *  total_hits:int,
     *  total_misses:int,
     *  hit_rate:float,
     *  keys: array<string>
     * }
     */
    public function stats(): array
    {
        $totalHits = array_sum($this->hits);
        $totalMisses = array_sum($this->misses);
        $total = $totalHits + $totalMisses;

        return [
            'total_items' => count($this->cache),
            'total_hits' => $totalHits,
            'total_misses' => $totalMisses,
            'hit_rate' =>
                $total > 0 ? round(($totalHits / $total) * 100, 2) : 0,
            'keys' => array_keys($this->cache),
        ];
    }

    /**
     * @return array<string, array{hits:int, misses:int, hit_rate:float}>
     */
    public function detailedStats(): array
    {
        $stats = [];
        $keys = array_unique([
            ...array_keys($this->hits),
            ...array_keys($this->misses),
        ]);

        foreach ($keys as $key) {
            $hits = $this->hits[$key] ?? 0;
            $misses = $this->misses[$key] ?? 0;
            $total = $hits + $misses;

            $stats[$key] = [
                'hits' => $hits,
                'misses' => $misses,
                'hit_rate' => $total > 0 ? round(($hits / $total) * 100, 2) : 0,
            ];
        }

        return $stats;
    }

    public function enable(): void
    {
        $this->enabled = true;
    }

    public function disable(): void
    {
        $this->enabled = false;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    private function recordHit(string $key): void
    {
        $this->hits[$key] = ($this->hits[$key] ?? 0) + 1;
    }

    private function recordMiss(string $key): void
    {
        $this->misses[$key] = ($this->misses[$key] ?? 0) + 1;
    }

    public function __isset(string $key): bool
    {
        return $this->has($key);
    }

    public function __get(string $key): mixed
    {
        return $this->get($key);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->put($key, $value);
    }

    public function __unset(string $key): void
    {
        $this->forget($key);
    }
}
