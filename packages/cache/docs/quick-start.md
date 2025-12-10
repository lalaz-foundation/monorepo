# Quick Start

Get Lalaz Cache working in 5 minutes.

## Prerequisites

- PHP 8.3 or higher
- Composer installed

## Step 1: Install the Package

```bash
composer require lalaz/cache
```

## Step 2: Create a Cache Manager

```php
<?php

use Lalaz\Cache\CacheManager;

// Simple setup with array store (for testing)
$cache = new CacheManager();

// Or with file store (for production)
$cache = new CacheManager([
    'driver' => 'file',
    'prefix' => 'myapp_',
    'stores' => [
        'file' => [
            'path' => '/var/cache/myapp',
        ],
    ],
]);
```

## Step 3: Basic Operations

```php
// Get the default store
$store = $cache->store();

// Store a value
$store->set('greeting', 'Hello, World!');

// Retrieve a value
$greeting = $store->get('greeting');
echo $greeting; // "Hello, World!"

// Store with TTL (1 hour)
$store->set('user:123', $userData, 3600);

// Check if key exists
if ($store->has('user:123')) {
    echo "User is cached!";
}

// Delete a key
$store->delete('greeting');

// Clear all cache
$store->clear();
```

## Step 4: Remember Pattern

The `remember` method is perfect for caching expensive operations:

```php
// First call: executes callback, caches result
$user = $store->remember('user:123', 3600, function () {
    return User::find(123); // Database query
});

// Second call: returns cached value (no database query)
$user = $store->remember('user:123', 3600, function () {
    return User::find(123);
});
```

## Step 5: Per-Request Cache

For lightweight, request-scoped caching:

```php
use Lalaz\Cache\PerRequestCache;

$requestCache = new PerRequestCache();

// Cache for the current request only
$user = $requestCache->remember('current_user', function () {
    return auth()->user();
});

// Check stats
$stats = $requestCache->stats();
echo "Hits: {$stats['total_hits']}, Misses: {$stats['total_misses']}";
```

## Complete Example

```php
<?php

require 'vendor/autoload.php';

use Lalaz\Cache\CacheManager;

// Initialize cache
$cache = new CacheManager([
    'driver' => 'file',
    'prefix' => 'blog_',
    'stores' => [
        'file' => [
            'path' => __DIR__ . '/cache',
        ],
    ],
]);

$store = $cache->store();

// Cache blog posts for 10 minutes
$posts = $store->remember('posts:latest', 600, function () {
    return Post::orderBy('created_at', 'desc')
        ->limit(10)
        ->get();
});

// Cache user profile for 1 hour
$profile = $store->remember("user:{$userId}", 3600, function () use ($userId) {
    return User::with('posts', 'comments')->find($userId);
});

// Invalidate cache when data changes
public function updatePost(int $id, array $data): void
{
    $post = Post::find($id);
    $post->update($data);
    
    // Clear related caches
    $store->delete('posts:latest');
    $store->delete("post:{$id}");
}
```

## What's Next?

- [Installation](./installation.md) - Detailed installation and configuration
- [Core Concepts](./concepts.md) - Understanding stores, drivers, and managers
- [Basic Operations](./basic-operations.md) - All cache operations explained
- [Stores](./stores/index.md) - Choose the right store for your needs
