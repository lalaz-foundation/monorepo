# Core Concepts

Understanding the key concepts and architecture of Lalaz Cache.

## Overview

Lalaz Cache follows a simple but powerful architecture:

```
CacheManager → Store → Storage Backend
```

- **CacheManager**: Creates and manages cache stores
- **Store**: Implements the caching operations (get, set, delete, etc.)
- **Storage Backend**: The actual storage (memory, file, Redis, etc.)

## Cache Stores

A cache store is a class that implements `CacheStoreInterface`. It defines how data is stored and retrieved.

### Available Stores

| Store | Class | Description |
|-------|-------|-------------|
| Array | `ArrayStore` | In-memory storage, lost on request end |
| File | `FileStore` | File-based storage, persists to disk |
| Redis | `RedisStore` | Redis server storage, distributed |
| APCu | `ApcuStore` | Shared memory storage, single server |
| Null | `NullStore` | No-op store, caching disabled |

### CacheStoreInterface

All stores implement these methods:

```php
interface CacheStoreInterface
{
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value, int|DateInterval|null $ttl = null): bool;
    public function has(string $key): bool;
    public function delete(string $key): bool;
    public function clear(): bool;
    public function remember(string $key, int|DateInterval|null $ttl, callable $callback): mixed;
    public function forever(string $key, mixed $value): bool;
}
```

## CacheManager

The `CacheManager` is the entry point for caching. It:

1. Reads configuration
2. Creates store instances
3. Caches store instances for reuse

### Configuration

```php
$config = [
    'enabled' => true,          // Enable/disable caching
    'driver' => 'file',         // Default driver
    'prefix' => 'myapp_',       // Key prefix
    'ttl' => 3600,              // Default TTL (optional)
    'stores' => [
        'array' => [
            'driver' => 'array',
        ],
        'file' => [
            'driver' => 'file',
            'path' => '/var/cache/myapp',
        ],
        'redis' => [
            'driver' => 'redis',
            'host' => '127.0.0.1',
            'port' => 6379,
        ],
    ],
];

$manager = new CacheManager($config);
```

### Getting Stores

```php
// Get default store
$store = $manager->store();

// Get named store
$fileStore = $manager->store('file');
$redisStore = $manager->store('redis');
```

## Time-to-Live (TTL)

TTL determines how long a cached value remains valid.

### TTL Formats

```php
// Seconds (integer)
$store->set('key', 'value', 3600); // 1 hour

// DateInterval
$store->set('key', 'value', new DateInterval('PT1H')); // 1 hour
$store->set('key', 'value', new DateInterval('P1D')); // 1 day

// No expiration (null)
$store->set('key', 'value', null); // Never expires
$store->forever('key', 'value');   // Same as null TTL
```

### TTL Behavior

- **Positive TTL**: Value expires after specified seconds
- **Zero/Negative TTL**: Value is immediately deleted
- **Null TTL**: Value never expires (until manually deleted or cache cleared)

## Key Prefixing

Prefixes help namespace your cache keys:

```php
$manager = new CacheManager([
    'prefix' => 'myapp_',
    'driver' => 'array',
]);

$store = $manager->store();
$store->set('user:123', $data);
// Actual key: "myapp_user:123"
```

### Benefits of Prefixing

1. **Avoid collisions**: Multiple apps can share same cache backend
2. **Easy clearing**: Clear all keys with a specific prefix
3. **Organization**: Group related keys together

## Remember Pattern

The `remember` method combines "get or set" in one operation:

```php
$value = $store->remember('key', 3600, function () {
    // This only runs if key doesn't exist
    return expensiveOperation();
});
```

### How It Works

```
remember('key', $ttl, $callback)
         │
         ▼
    ┌─────────┐
    │has(key)?│
    └────┬────┘
         │
    ┌────┴────┐
    │         │
   Yes       No
    │         │
    ▼         ▼
 get(key)  callback()
    │         │
    │         ▼
    │    set(key, result)
    │         │
    └────┬────┘
         │
         ▼
    return value
```

## Per-Request Cache

`PerRequestCache` is a lightweight in-memory cache that:

- Lives only for the current request
- Tracks hit/miss statistics
- Has no TTL (everything expires at request end)

```php
$cache = new PerRequestCache();

// Cache expensive operations within a request
$user = $cache->remember('current_user', function () {
    return auth()->user();
});

// Check statistics
$stats = $cache->stats();
// [
//     'total_items' => 5,
//     'total_hits' => 12,
//     'total_misses' => 5,
//     'hit_rate' => 70.59,
//     'keys' => ['current_user', 'config', ...]
// ]
```

## Cache Invalidation

### Single Key

```php
$store->delete('user:123');
```

### Multiple Keys

```php
foreach (['user:123', 'user:123:posts', 'user:123:profile'] as $key) {
    $store->delete($key);
}
```

### Clear All

```php
$store->clear();
```

### Best Practices

1. **Use meaningful key names**: `user:123:profile` instead of `u123p`
2. **Invalidate on updates**: Clear cache when underlying data changes
3. **Use appropriate TTL**: Balance freshness vs performance
4. **Consider cache stampede**: Multiple requests regenerating same cache

## Next Steps

- [Basic Operations](./basic-operations.md) - Detailed operation reference
- [Stores](./stores/index.md) - Deep dive into each store type
- [Configuration](./configuration.md) - All configuration options
- [Examples](./examples/basic.md) - Real-world examples
