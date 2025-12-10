# API Reference

Complete API documentation for Lalaz Cache.

## CacheStoreInterface

All cache stores implement this interface.

### get()

```php
public function get(string $key, mixed $default = null): mixed
```

Retrieve an item from the cache.

**Parameters:**
- `$key` - The cache key
- `$default` - Value to return if key doesn't exist

**Returns:** The cached value or default

**Example:**
```php
$value = $store->get('key');
$value = $store->get('key', 'default');
```

---

### set()

```php
public function set(
    string $key,
    mixed $value,
    int|\DateInterval|null $ttl = null
): bool
```

Store an item in the cache.

**Parameters:**
- `$key` - The cache key
- `$value` - The value to store
- `$ttl` - Time-to-live in seconds, DateInterval, or null for forever

**Returns:** `true` on success

**Example:**
```php
$store->set('key', 'value', 3600);
$store->set('key', 'value', new DateInterval('PT1H'));
$store->set('key', 'value');  // Forever
```

---

### has()

```php
public function has(string $key): bool
```

Check if a key exists in the cache.

**Parameters:**
- `$key` - The cache key

**Returns:** `true` if key exists and is not expired

**Example:**
```php
if ($store->has('key')) {
    // Key exists
}
```

---

### delete()

```php
public function delete(string $key): bool
```

Remove an item from the cache.

**Parameters:**
- `$key` - The cache key

**Returns:** `true` if key existed and was deleted

**Example:**
```php
$store->delete('key');
```

---

### clear()

```php
public function clear(): bool
```

Remove all items with the configured prefix.

**Returns:** `true` on success

**Example:**
```php
$store->clear();
```

---

### remember()

```php
public function remember(
    string $key,
    int|\DateInterval|null $ttl,
    callable $callback
): mixed
```

Get an item from cache, or execute callback and store the result.

**Parameters:**
- `$key` - The cache key
- `$ttl` - Time-to-live
- `$callback` - Function to execute on cache miss

**Returns:** The cached or computed value

**Example:**
```php
$value = $store->remember('key', 3600, function () {
    return expensiveOperation();
});
```

---

### forever()

```php
public function forever(string $key, mixed $value): bool
```

Store an item in the cache indefinitely.

**Parameters:**
- `$key` - The cache key
- `$value` - The value to store

**Returns:** `true` on success

**Example:**
```php
$store->forever('key', 'value');
```

---

## CacheManager

Manages cache store instances.

### store()

```php
public function store(?string $name = null): CacheStoreInterface
```

Get a cache store instance.

**Parameters:**
- `$name` - Store name, or null for default

**Returns:** `CacheStoreInterface` instance

**Example:**
```php
$default = $manager->store();
$redis = $manager->store('redis');
```

---

## PerRequestCache

In-memory cache for single request with statistics.

### remember()

```php
public function remember(string $key, Closure $callback): mixed
```

Get item or execute callback and store result.

**Example:**
```php
$user = $cache->remember('user', fn() => User::find(1));
```

---

### get()

```php
public function get(string $key, mixed $default = null): mixed
```

Get an item from the cache.

---

### put()

```php
public function put(string $key, mixed $value): void
```

Store an item in the cache.

---

### has()

```php
public function has(string $key): bool
```

Check if key exists.

---

### forget()

```php
public function forget(string $key): void
```

Remove an item.

---

### flush()

```php
public function flush(): void
```

Remove all items and reset statistics.

---

### all()

```php
public function all(): array
```

Get all cached items.

---

### stats()

```php
public function stats(): array
```

Get cache statistics.

**Returns:**
```php
[
    'total_items' => int,
    'total_hits' => int,
    'total_misses' => int,
    'hit_rate' => float,
    'keys' => array<string>
]
```

---

### detailedStats()

```php
public function detailedStats(): array
```

Get per-key statistics.

**Returns:**
```php
[
    'key' => [
        'hits' => int,
        'misses' => int,
        'hit_rate' => float
    ]
]
```

---

### enable()

```php
public function enable(): void
```

Enable caching.

---

### disable()

```php
public function disable(): void
```

Disable caching (all operations become no-ops).

---

### isEnabled()

```php
public function isEnabled(): bool
```

Check if caching is enabled.

---

## CacheException

Exception thrown for cache errors.

```php
throw new CacheException('Unsupported cache driver');
```

---

## Store Classes

### ArrayStore

```php
use Lalaz\Cache\Stores\ArrayStore;

$store = new ArrayStore(string $prefix = '');
```

In-memory array storage.

---

### FileStore

```php
use Lalaz\Cache\Stores\FileStore;

$store = new FileStore(string $directory, string $prefix = 'lalaz_');
```

File-based storage with atomic writes.

---

### RedisStore

```php
use Lalaz\Cache\Stores\RedisStore;

$store = new RedisStore(array $config, string $prefix = 'lalaz_');
```

Redis-backed storage.

**Config options:**
- `host` - Redis host (default: 127.0.0.1)
- `port` - Redis port (default: 6379)
- `password` - Redis password
- `database` - Database number (default: 0)
- `timeout` - Connection timeout

---

### ApcuStore

```php
use Lalaz\Cache\Stores\ApcuStore;

$store = new ApcuStore(string $prefix = 'lalaz_');
```

APCu shared memory storage.

---

### NullStore

```php
use Lalaz\Cache\Stores\NullStore;

$store = new NullStore();
```

No-operation store for disabled caching.
