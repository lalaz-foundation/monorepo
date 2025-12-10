# Array Store

In-memory cache store for a single PHP process.

## Overview

The Array store keeps cached data in a PHP array during script execution. Data is not persisted between requests.

## Configuration

```php
'stores' => [
    'array' => [
        'driver' => 'array',
    ],
],
```

No additional configuration required.

## Direct Instantiation

```php
use Lalaz\Cache\Stores\ArrayStore;

$store = new ArrayStore('myprefix_');
```

## Features

### TTL Support

Items can have expiration times:

```php
$store->set('key', 'value', 60);  // Expires in 60 seconds

// After 60 seconds...
$store->get('key');  // Returns null
$store->has('key');  // Returns false
```

### Immediate Expiration

Zero or negative TTL immediately removes the item:

```php
$store->set('key', 'value', 0);   // Item not stored
$store->set('key', 'value', -1);  // Item not stored
```

### Forever Storage

```php
$store->forever('key', 'value');
// or
$store->set('key', 'value', null);
```

## Use Cases

### Testing

```php
class UserServiceTest extends TestCase
{
    public function testCachesUser(): void
    {
        $cache = new ArrayStore();
        $service = new UserService($cache);
        
        $user = $service->find(123);
        
        $this->assertTrue($cache->has('user:123'));
    }
}
```

### Development

```php
// Fresh cache each request helps catch caching issues
$cache = new CacheManager([
    'driver' => 'array',
]);
```

### Request-Scoped Caching

```php
// Cache expensive operations within a single request
$store->remember('expensive:query', null, function () {
    return DB::query('SELECT * FROM large_table');
});
```

## Limitations

- **No Persistence** - Data lost when script ends
- **No Sharing** - Each process has its own cache
- **Memory Bound** - Large caches consume memory

## When to Use

✅ Unit and integration testing  
✅ Development environments  
✅ Single-request caching  
✅ CI/CD pipelines

❌ Production with persistence needs  
❌ Shared cache across processes  
❌ Large datasets

## Example

```php
use Lalaz\Cache\Stores\ArrayStore;

$store = new ArrayStore();

// Store values
$store->set('user:1', ['name' => 'John'], 3600);
$store->set('user:2', ['name' => 'Jane'], 3600);

// Retrieve
$user = $store->get('user:1');  // ['name' => 'John']

// Check existence
$store->has('user:1');  // true
$store->has('user:99'); // false

// Remember pattern
$config = $store->remember('app:config', 3600, function () {
    return loadConfigFromFile();
});

// Clear all
$store->clear();
```
