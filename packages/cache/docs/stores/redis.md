# Redis Store

Redis-backed cache store for distributed, high-performance caching.

## Overview

The Redis store uses Redis as the cache backend, providing persistent, distributed caching with excellent performance. Supports both phpredis extension and Predis library.

## Requirements

One of:
- **phpredis** extension (recommended for performance)
- **predis/predis** Composer package

## Configuration

```php
'stores' => [
    'redis' => [
        'driver' => 'redis',
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'port' => env('REDIS_PORT', 6379),
        'password' => env('REDIS_PASSWORD'),
        'database' => env('REDIS_CACHE_DB', 1),
        'timeout' => 0.0,
    ],
],
```

### Options

| Option | Type | Required | Default | Description |
|--------|------|----------|---------|-------------|
| `driver` | string | Yes | - | Must be `'redis'` |
| `host` | string | No | `127.0.0.1` | Redis server hostname |
| `port` | int | No | `6379` | Redis server port |
| `password` | string\|null | No | `null` | Redis password |
| `database` | int | No | `0` | Redis database number (0-15) |
| `timeout` | float | No | `0.0` | Connection timeout |

## Installing Redis Client

### phpredis (Recommended)

```bash
# Ubuntu/Debian
sudo apt-get install php-redis

# macOS with Homebrew
pecl install redis

# Verify installation
php -m | grep redis
```

### Predis

```bash
composer require predis/predis
```

## Features

### Distributed Cache

Cache is shared across all application instances:

```
┌─────────────┐     ┌─────────────┐
│   Server 1  │────▶│             │
└─────────────┘     │    Redis    │
                    │   Server    │
┌─────────────┐     │             │
│   Server 2  │────▶│             │
└─────────────┘     └─────────────┘
```

### Native TTL

Redis handles expiration automatically:

```php
$store->set('key', 'value', 3600);  // Redis SETEX command
```

### Atomic Operations

Redis ensures atomic read/write:

```php
// Thread-safe operations
$store->set('counter', $value);
```

### Efficient Scanning

The `clear()` method uses `SCAN` for memory efficiency:

```php
// Won't block Redis with large datasets
$store->clear();
```

## Use Cases

### Session Storage

```php
$cache = new CacheManager([
    'driver' => 'redis',
    'stores' => [
        'redis' => [
            'database' => 0,  // Dedicated database for sessions
        ],
    ],
]);
```

### Rate Limiting

```php
$key = "rate:{$userId}:{$action}";
$count = $store->get($key, 0);

if ($count >= $limit) {
    throw new RateLimitException();
}

$store->set($key, $count + 1, 60);  // Reset every minute
```

### Distributed Locks

```php
// Simple distributed lock pattern
$lockKey = "lock:{$resource}";

if (!$store->has($lockKey)) {
    $store->set($lockKey, getmypid(), 30);  // 30 second lock
    
    try {
        // Do work...
    } finally {
        $store->delete($lockKey);
    }
}
```

### Cache Warming

```php
// Pre-populate cache on deploy
foreach (User::all() as $user) {
    $store->set("user:{$user->id}", $user, 3600);
}
```

## High Availability

### Redis Sentinel

For automatic failover, configure Sentinel:

```php
// Using Predis with Sentinel
$store = new RedisStore([
    'tcp://sentinel1:26379',
    'tcp://sentinel2:26379',
    'tcp://sentinel3:26379',
], 'myapp_');
```

### Redis Cluster

For horizontal scaling:

```php
// Using Predis with Cluster
$store = new RedisStore([
    'tcp://node1:6379',
    'tcp://node2:6379',
    'tcp://node3:6379',
], 'myapp_');
```

## Best Practices

### Use Dedicated Database

Separate cache from other Redis data:

```php
'stores' => [
    'redis' => [
        'database' => 1,  // Cache on database 1
    ],
],
```

### Set Memory Policy

Configure Redis to handle memory limits:

```redis
maxmemory 256mb
maxmemory-policy allkeys-lru
```

### Monitor Memory Usage

```bash
redis-cli info memory
```

### Use Connection Pooling

In high-traffic scenarios, use persistent connections:

```php
$redis = new \Redis();
$redis->pconnect('127.0.0.1', 6379);  // Persistent connection
```

## Troubleshooting

### Connection Refused

```
CacheException: Connection refused
```

**Solution:** Verify Redis is running:

```bash
redis-cli ping
# Should return: PONG
```

### Authentication Failed

```
NOAUTH Authentication required
```

**Solution:** Provide password in configuration:

```php
'password' => env('REDIS_PASSWORD'),
```

### Memory Limit Reached

```
OOM command not allowed
```

**Solution:** Increase memory or set eviction policy:

```redis
maxmemory 512mb
maxmemory-policy allkeys-lru
```

## Example

```php
use Lalaz\Cache\Stores\RedisStore;

$store = new RedisStore([
    'host' => '127.0.0.1',
    'port' => 6379,
    'database' => 1,
], 'myapp_');

// Store value with TTL
$store->set('user:123', ['name' => 'John'], 3600);

// Retrieve
$user = $store->get('user:123');

// Check existence
if ($store->has('user:123')) {
    // Key exists in Redis
}

// Remember pattern
$stats = $store->remember('stats:daily', 86400, function () {
    return calculateDailyStats();
});

// Store forever
$store->forever('config:settings', $settings);

// Delete
$store->delete('user:123');

// Clear all keys with prefix
$store->clear();
```
