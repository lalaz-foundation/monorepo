# Basic Usage Examples

Simple examples to get started with Lalaz Cache.

## Setup

```php
<?php

use Lalaz\Cache\CacheManager;

$cache = new CacheManager([
    'driver' => 'array', // or 'file', 'redis', 'apcu'
    'prefix' => 'myapp_',
]);

$store = $cache->store();
```

## Storing and Retrieving Values

### Simple String

```php
$store->set('greeting', 'Hello, World!');
$greeting = $store->get('greeting');
echo $greeting; // "Hello, World!"
```

### With TTL

```php
// Cache for 1 hour
$store->set('token', 'abc123', 3600);

// Check later
if ($store->has('token')) {
    $token = $store->get('token');
}
```

### Arrays

```php
$user = [
    'id' => 123,
    'name' => 'John Doe',
    'email' => 'john@example.com',
];

$store->set('user:123', $user, 3600);

$cached = $store->get('user:123');
echo $cached['name']; // "John Doe"
```

### Objects

```php
$user = User::find(123);
$store->set('user:123', $user, 3600);

$cached = $store->get('user:123');
echo $cached->name; // "John Doe"
```

## Checking and Deleting

### Check Existence

```php
if ($store->has('user:123')) {
    echo "User is cached";
} else {
    echo "User not in cache";
}
```

### Delete Single Key

```php
$store->delete('user:123');
```

### Clear All

```php
$store->clear();
```

## Remember Pattern

### Basic Remember

```php
$user = $store->remember('user:123', 3600, function () {
    echo "Fetching from database...";
    return User::find(123);
});
```

### Remember with Dependencies

```php
$userId = 123;
$withPosts = true;

$user = $store->remember("user:{$userId}:full", 3600, function () use ($userId, $withPosts) {
    $query = User::query()->where('id', $userId);
    
    if ($withPosts) {
        $query->with('posts');
    }
    
    return $query->first();
});
```

### Remember Forever

```php
$config = $store->remember('app:config', null, function () {
    return Config::all();
});
```

## Forever Storage

```php
// Store permanently (no expiration)
$store->forever('settings:site', [
    'name' => 'My Site',
    'theme' => 'dark',
]);

// Retrieve anytime
$settings = $store->get('settings:site');
```

## Default Values

```php
// Returns null if not found
$value = $store->get('nonexistent');
var_dump($value); // null

// Returns default if not found
$value = $store->get('nonexistent', 'default');
echo $value; // "default"

// Default with complex types
$users = $store->get('users:list', []);
```

## Multiple Stores

```php
$cache = new CacheManager([
    'driver' => 'array',
    'stores' => [
        'array' => ['driver' => 'array'],
        'file' => [
            'driver' => 'file',
            'path' => '/tmp/cache',
        ],
    ],
]);

// Fast in-memory cache
$arrayStore = $cache->store('array');
$arrayStore->set('temp', 'value');

// Persistent file cache
$fileStore = $cache->store('file');
$fileStore->set('persistent', 'value');
```

## Per-Request Cache

```php
use Lalaz\Cache\PerRequestCache;

$requestCache = new PerRequestCache();

// First call: executes callback
$user = $requestCache->remember('current_user', function () {
    return Auth::user();
});

// Second call: returns cached value
$user = $requestCache->remember('current_user', function () {
    return Auth::user(); // Not executed
});

// Check stats
$stats = $requestCache->stats();
echo "Hits: {$stats['total_hits']}";   // 1
echo "Misses: {$stats['total_misses']}"; // 1
```

## Error Handling

```php
try {
    $data = $store->remember('external:api', 300, function () {
        $response = Http::get('https://api.example.com/data');
        
        if (!$response->ok()) {
            throw new RuntimeException('API error');
        }
        
        return $response->json();
    });
} catch (RuntimeException $e) {
    // Use fallback data
    $data = $store->get('external:api:fallback', []);
}
```

## Next Steps

- [Web Application Example](./web-app.md) - Full-stack example
- [REST API Example](./api.md) - API response caching
- [Database Queries Example](./database.md) - Query result caching
