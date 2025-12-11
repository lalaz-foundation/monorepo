<?php

declare(strict_types=1);

namespace Lalaz\Support\Cache;

use Lalaz\Exceptions\ConfigurationException;

/**
 * Simple LRU (Least Recently Used) Cache implementation.
 *
 * This cache maintains a maximum number of entries and automatically
 * evicts the least recently used entries when the limit is reached.
 *
 * @template T
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 */
final class LruCache
{
    /**
     * Default maximum number of entries.
     */
    public const DEFAULT_MAX_SIZE = 256;

    /**
     * @var array<string, T> The cached values.
     */
    private array $cache = [];

    /**
     * Doubly-linked list pointers for O(1) LRU operations.
     * @var array<string, string|null> key => prevKey
     */
    private array $prev = [];

    /**
     * Doubly-linked list pointers for O(1) LRU operations.
     * @var array<string, string|null> key => nextKey
     */
    private array $next = [];

    /**
     * The head (least recently used) key or null if empty.
     * @var string|null
     */
    private ?string $head = null;

    /**
     * The tail (most recently used) key or null if empty.
     * @var string|null
     */
    private ?string $tail = null;

    /**
     * Current number of entries.
     * @var int
     */
    private int $size = 0;

    /**
     * @param int $maxSize Maximum number of entries to keep in cache.
     */
    public function __construct(
        private readonly int $maxSize = self::DEFAULT_MAX_SIZE,
    ) {
        if ($maxSize < 1) {
            throw ConfigurationException::invalidValue('maxSize', $maxSize, 'integer >= 1');
        }
    }

    /**
     * Get a value from the cache.
     *
     * @param string $key
     * @return T|null The cached value or null if not found.
     */
    public function get(string $key): mixed
    {
        if (!isset($this->cache[$key])) {
            return null;
        }

        // Move key to tail (most recently used)
        $this->moveToTail($key);

        return $this->cache[$key];
    }

    /**
     * Check if a key exists in the cache.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->cache[$key]);
    }

    /**
     * Store a value in the cache.
     *
     * @param string $key
     * @param T $value
     * @return T The stored value (for chaining).
     */
    public function set(string $key, mixed $value): mixed
    {
        if (isset($this->cache[$key])) {
            // Update value and move to MRU position
            $this->cache[$key] = $value;
            $this->moveToTail($key);
            return $value;
        }

        // Evict if at capacity
        if ($this->size >= $this->maxSize) {
            $this->evictLeastRecentlyUsed();
        }

        // Insert as most-recently-used (tail)
        $this->cache[$key] = $value;
        $this->appendToTail($key);

        return $value;
    }

    /**
     * Remove a key from the cache.
     *
     * @param string $key
     * @return void
     */
    public function forget(string $key): void
    {
        if (!isset($this->cache[$key])) {
            return;
        }

        // Remove pointers
        $prev = $this->prev[$key] ?? null;
        $next = $this->next[$key] ?? null;

        if ($prev !== null) {
            $this->next[$prev] = $next;
        }

        if ($next !== null) {
            $this->prev[$next] = $prev;
        }

        if ($this->head === $key) {
            $this->head = $next;
        }

        if ($this->tail === $key) {
            $this->tail = $prev;
        }

        // Remove stored value and pointers
        unset($this->cache[$key], $this->prev[$key], $this->next[$key]);
        $this->size--;
    }

    /**
     * Clear all entries from the cache.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->cache = [];
        $this->prev = [];
        $this->next = [];
        $this->head = null;
        $this->tail = null;
        $this->size = 0;
    }

    /**
     * Get the current number of entries in the cache.
     *
     * @return int
     */
    public function count(): int
    {
        return $this->size;
    }

    /**
     * Get the maximum size of the cache.
     *
     * @return int
     */
    public function maxSize(): int
    {
        return $this->maxSize;
    }

    /**
     * Evict the least recently used entry.
     *
     * @return void
     */
    private function evictLeastRecentlyUsed(): void
    {
        if ($this->head === null) {
            return;
        }

        $lruKey = $this->head;

        $next = $this->next[$lruKey] ?? null;
        if ($next !== null) {
            $this->prev[$next] = null;
        }

        $this->head = $next;

        unset($this->cache[$lruKey], $this->prev[$lruKey], $this->next[$lruKey]);
        $this->size--;
    }

    /**
     * Move a key to the tail (MRU)
     *
     * @param string $key
     * @return void
     */
    private function moveToTail(string $key): void
    {
        // If already tail or single element, no-op
        if ($this->tail === $key || $this->head === null) {
            return;
        }

        $prev = $this->prev[$key] ?? null;
        $next = $this->next[$key] ?? null;

        // Detach key
        if ($prev !== null) {
            $this->next[$prev] = $next;
        }

        if ($next !== null) {
            $this->prev[$next] = $prev;
        }

        if ($this->head === $key) {
            $this->head = $next;
        }

        // Append to tail
        $oldTail = $this->tail;
        $this->next[$key] = null;
        $this->prev[$key] = $oldTail;

        if ($oldTail !== null) {
            $this->next[$oldTail] = $key;
        }

        $this->tail = $key;

        // If list was empty before, ensure head is set
        if ($this->head === null) {
            $this->head = $key;
        }
    }

    /**
     * Append a new key to the tail (MRU)
     *
     * @param string $key
     * @return void
     */
    private function appendToTail(string $key): void
    {
        $oldTail = $this->tail;

        $this->prev[$key] = $oldTail;
        $this->next[$key] = null;

        if ($oldTail !== null) {
            $this->next[$oldTail] = $key;
        }

        $this->tail = $key;

        if ($this->head === null) {
            $this->head = $key;
        }

        $this->size++;
    }
}
