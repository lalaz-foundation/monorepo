# Lalaz Cache Documentation

A powerful and flexible caching library for PHP applications.

## Quick Start

```php
use Lalaz\Cache\CacheManager;

$cache = new CacheManager([
    'driver' => 'file',
    'stores' => [
        'file' => ['path' => '/var/cache/app'],
    ],
]);

$store = $cache->store();

// Store and retrieve
$store->set('key', 'value', 3600);
$value = $store->get('key');

// Remember pattern
$user = $store->remember('user:123', 3600, fn() => User::find(123));
```

## Documentation

### Getting Started

- [Installation](installation.md) - Setup and dependencies
- [Configuration](configuration.md) - Full configuration reference
- [Basic Operations](basic-operations.md) - Core cache operations

### Cache Stores

- [Stores Overview](stores/index.md) - Available storage backends
- [Array Store](stores/array.md) - In-memory storage
- [File Store](stores/file.md) - File-based storage
- [Redis Store](stores/redis.md) - Redis storage
- [APCu Store](stores/apcu.md) - Shared memory storage
- [Null Store](stores/null.md) - No-op storage

### Advanced

- [Per-Request Cache](per-request-cache.md) - Request-scoped caching with stats
- [Examples](examples.md) - Practical code examples
- [Testing](testing.md) - Testing strategies
- [Troubleshooting](troubleshooting.md) - Common issues and solutions

### Reference

- [API Reference](api-reference.md) - Complete API documentation

## Features

| Feature | Description |
|---------|-------------|
| **Multiple Backends** | Array, File, Redis, APCu, Null |
| **Unified API** | Same interface for all stores |
| **TTL Support** | Seconds or DateInterval |
| **Remember Pattern** | Cache expensive operations |
| **Per-Request Cache** | Lightweight with statistics |
| **Atomic Operations** | File store uses atomic writes |
| **Prefix Support** | Namespace your cache keys |

## Requirements

- PHP 8.2+
- Optional: Redis extension or Predis (for Redis store)
- Optional: APCu extension (for APCu store)

## License

MIT License
