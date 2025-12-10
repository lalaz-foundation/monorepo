# Null Store

No-operation cache store for disabling caching.

## Overview

The Null store performs no caching operations. All writes succeed silently, and all reads return the default value. It's used automatically when caching is disabled.

## Configuration

The Null store is used automatically when `enabled` is `false`:

```php
$cache = new CacheManager([
    'enabled' => false,  // NullStore used for all operations
    'driver' => 'redis',
]);
```

## Direct Instantiation

```php
use Lalaz\Cache\Stores\NullStore;

$store = new NullStore();
```

## Behavior

### All Writes "Succeed"

```php
$store->set('key', 'value', 3600);  // Returns true, stores nothing
$store->forever('key', 'value');    // Returns true, stores nothing
```

### All Reads Return Default

```php
$store->get('key');              // Returns null
$store->get('key', 'default');   // Returns 'default'
$store->has('key');              // Returns false
```

### Remember Always Executes Callback

```php
$store->remember('key', 3600, function () {
    return expensiveOperation();  // Always called
});
```

### Delete/Clear Always "Succeed"

```php
$store->delete('key');  // Returns true
$store->clear();        // Returns true
```

## Use Cases

### Disable Caching Globally

```php
// config/cache.php
return [
    'enabled' => env('CACHE_ENABLED', true),
    'driver' => 'redis',
];

// .env.testing
CACHE_ENABLED=false
```

### Debugging

```php
// Temporarily disable caching to debug issues
$cache = new CacheManager([
    'enabled' => false,
]);

// All operations bypass cache
$data = $cache->store()->remember('key', 3600, fn() => fetchData());
// fetchData() is called every time
```

### Development

```php
// Skip caching in development
$cache = new CacheManager([
    'enabled' => app()->environment('development') === false,
    'driver' => 'redis',
]);
```

### Testing Without Mock

```php
class MyServiceTest extends TestCase
{
    public function testWithoutCache(): void
    {
        $cache = new NullStore();
        $service = new MyService($cache);
        
        // Service works, but nothing is cached
        $result = $service->getData();
        
        // Cache operations are no-ops
        $this->assertFalse($cache->has('data'));
    }
}
```

## Why Use Null Store?

### 1. Consistent Interface

Your code doesn't need to check if caching is enabled:

```php
// Works regardless of cache being enabled or disabled
$value = $cache->store()->remember('key', 3600, fn() => compute());
```

### 2. Easy Toggle

Switch caching on/off without code changes:

```php
// Just change configuration
'enabled' => false,
```

### 3. Performance Comparison

Measure application performance with and without caching:

```php
// .env.benchmark-no-cache
CACHE_ENABLED=false

// Compare response times
```

### 4. Cache-Related Bug Isolation

When debugging, disable cache to rule out caching issues:

```php
// Is the bug in caching or business logic?
$cache = new CacheManager(['enabled' => false]);
```

## Implementation

The Null store implementation is minimal:

```php
final class NullStore implements CacheStoreInterface
{
    public function get(string $key, mixed $default = null): mixed
    {
        return $default;
    }

    public function set(string $key, mixed $value, int|\DateInterval|null $ttl = null): bool
    {
        return true;
    }

    public function has(string $key): bool
    {
        return false;
    }

    public function delete(string $key): bool
    {
        return true;
    }

    public function clear(): bool
    {
        return true;
    }

    public function remember(string $key, int|\DateInterval|null $ttl, callable $callback): mixed
    {
        return $callback();
    }

    public function forever(string $key, mixed $value): bool
    {
        return true;
    }
}
```

## Best Practices

### Use Environment Variable

```php
'enabled' => env('CACHE_ENABLED', true),
```

### Log When Disabled

```php
if (!$config['enabled']) {
    $logger->warning('Caching is disabled');
}
```

### Don't Use in Production

```php
if (app()->isProduction() && !$config['enabled']) {
    throw new \RuntimeException('Caching should be enabled in production');
}
```
