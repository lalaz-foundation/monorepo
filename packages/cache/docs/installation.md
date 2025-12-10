# Installation

## Requirements

- PHP 8.2 or higher
- Optional: Redis extension or Predis library (for Redis store)
- Optional: APCu extension (for APCu store)

## Installation via Composer

```bash
composer require lalaz/cache
```

## Basic Configuration

Create a configuration file `config/cache.php`:

```php
<?php

return [
    // Enable/disable caching globally
    'enabled' => env('CACHE_ENABLED', true),
    
    // Default cache driver
    'driver' => env('CACHE_DRIVER', 'file'),
    
    // Key prefix for all cache items
    'prefix' => env('CACHE_PREFIX', 'lalaz_'),
    
    // Store configurations
    'stores' => [
        'array' => [
            'driver' => 'array',
        ],
        
        'file' => [
            'driver' => 'file',
            'path' => storage_path('cache'),
        ],
        
        'redis' => [
            'driver' => 'redis',
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD', null),
            'database' => env('REDIS_CACHE_DB', 1),
            'timeout' => 0.0,
        ],
        
        'apcu' => [
            'driver' => 'apcu',
        ],
    ],
];
```

## Environment Variables

```env
CACHE_ENABLED=true
CACHE_DRIVER=file
CACHE_PREFIX=myapp_

# Redis Configuration
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=
REDIS_CACHE_DB=1
```

## Quick Setup

### Minimal Setup

```php
use Lalaz\Cache\CacheManager;

// Array store (no configuration needed)
$cache = new CacheManager(['driver' => 'array']);

// File store
$cache = new CacheManager([
    'driver' => 'file',
    'stores' => [
        'file' => [
            'path' => '/tmp/cache',
        ],
    ],
]);
```

### Production Setup

```php
use Lalaz\Cache\CacheManager;

$cache = new CacheManager([
    'driver' => 'redis',
    'prefix' => 'myapp_',
    'stores' => [
        'redis' => [
            'host' => '127.0.0.1',
            'port' => 6379,
            'database' => 1,
        ],
    ],
]);
```

## Service Provider Registration

If using the Lalaz framework, register the service provider:

```php
// config/app.php
return [
    'providers' => [
        Lalaz\Cache\CacheServiceProvider::class,
    ],
];
```

The service provider will automatically configure the cache manager based on your configuration.

## Verify Installation

```php
$store = $cache->store();

// Test write
$store->set('test', 'Hello Cache!', 60);

// Test read
echo $store->get('test'); // "Hello Cache!"

// Test delete
$store->delete('test');

// Verify deleted
var_dump($store->has('test')); // false
```

## Next Steps

- [Basic Operations](basic-operations.md) - Learn core cache operations
- [Configuration](configuration.md) - Advanced configuration options
- [Stores](stores/index.md) - Available cache stores
