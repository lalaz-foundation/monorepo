# Per-Request Cache

Lightweight in-memory cache with hit/miss statistics for single request optimization.

## Overview

The Per-Request Cache (`PerRequestCache`) provides a simple in-memory cache designed for caching data within a single HTTP request. Unlike the main cache stores, it includes built-in statistics for monitoring cache effectiveness.

## Features

- **Request-Scoped** - Data only lives for the current request
- **Hit/Miss Statistics** - Track cache effectiveness
- **Zero Configuration** - No setup required
- **Magic Properties** - Object-style access
- **Enable/Disable Toggle** - Easily bypass caching

## Basic Usage

```php
use Lalaz\Cache\PerRequestCache;

$cache = new PerRequestCache();

// Store value
$cache->put('user', $user);

// Retrieve value
$user = $cache->get('user');

// Check existence
if ($cache->has('user')) {
    // Key exists
}

// Remove value
$cache->forget('user');

// Clear all
$cache->flush();
```

## Remember Pattern

Cache expensive operations:

```php
$user = $cache->remember('user:123', function () {
    return User::find(123);  // Only called on cache miss
});
```

## Statistics

### Basic Stats

```php
$stats = $cache->stats();

// Returns:
[
    'total_items' => 5,
    'total_hits' => 12,
    'total_misses' => 3,
    'hit_rate' => 80.0,
    'keys' => ['user', 'config', 'permissions', ...]
]
```

### Detailed Stats

```php
$detailed = $cache->detailedStats();

// Returns per-key statistics:
[
    'user' => [
        'hits' => 5,
        'misses' => 1,
        'hit_rate' => 83.33
    ],
    'config' => [
        'hits' => 7,
        'misses' => 2,
        'hit_rate' => 77.78
    ],
]
```

## Enable/Disable

```php
$cache = new PerRequestCache();

// Disable caching
$cache->disable();

$cache->put('key', 'value');   // Does nothing
$cache->get('key');            // Returns null
$cache->remember('key', fn() => compute());  // Always executes callback

// Re-enable
$cache->enable();

// Check status
if ($cache->isEnabled()) {
    // Caching is active
}
```

## Magic Properties

Access cache like an object:

```php
$cache = new PerRequestCache();

// Set
$cache->user = $user;

// Get
$user = $cache->user;

// Check
if (isset($cache->user)) {
    // Key exists
}

// Delete
unset($cache->user);
```

## All Data

Get all cached data:

```php
$all = $cache->all();

// Returns:
[
    'user' => User {...},
    'config' => ['debug' => true],
    'permissions' => ['read', 'write'],
]
```

## Use Cases

### Avoid Duplicate Database Queries

```php
class UserRepository
{
    public function __construct(
        private PerRequestCache $cache
    ) {}
    
    public function find(int $id): ?User
    {
        return $this->cache->remember("user:{$id}", function () use ($id) {
            return User::find($id);
        });
    }
}

// Multiple calls, single query
$user = $repo->find(123);  // Database query
$user = $repo->find(123);  // From cache
$user = $repo->find(123);  // From cache
```

### Cache Computed Values

```php
$permissions = $cache->remember('permissions', function () {
    return array_merge(
        $user->permissions,
        $user->role->permissions,
        $user->team->permissions
    );
});
```

### Debug Cache Effectiveness

```php
// At end of request
$stats = $cache->stats();

if ($stats['hit_rate'] < 50) {
    Log::warning('Low cache hit rate', $stats);
}
```

### Middleware Integration

```php
class PerRequestCacheMiddleware
{
    public function handle($request, $next)
    {
        app()->singleton(PerRequestCache::class);
        
        $response = $next($request);
        
        // Log cache stats
        $stats = app(PerRequestCache::class)->stats();
        Log::debug('Request cache stats', $stats);
        
        return $response;
    }
}
```

## Comparison with Main Cache

| Feature | PerRequestCache | CacheManager |
|---------|-----------------|--------------|
| Persistence | No (request only) | Yes (configurable) |
| Shared | No | Yes (Redis, APCu) |
| TTL | No | Yes |
| Statistics | Yes | No |
| Configuration | None | Required |
| Use Case | Request optimization | General caching |

## Best Practices

### Use for Request-Local Data

```php
// Good - data needed multiple times in same request
$cache->remember('current_user', fn() => Auth::user());

// Bad - use main cache for data needed across requests
$cache->remember('settings', fn() => Settings::all());  // Use CacheManager instead
```

### Monitor Hit Rate

```php
register_shutdown_function(function () use ($cache) {
    $stats = $cache->stats();
    
    if ($stats['total_items'] > 0 && $stats['hit_rate'] < 20) {
        // Cache not being effective
        // Items cached but rarely reused
    }
});
```

### Clear Between Tests

```php
protected function tearDown(): void
{
    $this->cache->flush();
}
```

## Example: Full Request Lifecycle

```php
$cache = new PerRequestCache();

// Early in request - load user
$user = $cache->remember('user', fn() => Auth::user());

// In controller
$permissions = $cache->remember('permissions', function () use ($user) {
    return $user->getAllPermissions();
});

// In service
$user = $cache->get('user');  // Cache hit
$permissions = $cache->get('permissions');  // Cache hit

// In view helper
if ($cache->has('user')) {
    $user = $cache->get('user');  // Cache hit
}

// End of request
$stats = $cache->stats();
// ['total_items' => 2, 'total_hits' => 4, 'total_misses' => 0, 'hit_rate' => 100.0]
```
