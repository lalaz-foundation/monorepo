# Configuration

Detailed configuration options for the cache system.

## Full Configuration Reference

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cache Enabled
    |--------------------------------------------------------------------------
    |
    | When disabled, all cache operations use the NullStore which performs
    | no caching. Useful for debugging or development.
    |
    */
    'enabled' => env('CACHE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Default Cache Driver
    |--------------------------------------------------------------------------
    |
    | The default cache store to use when no store is specified.
    | Supported: "array", "file", "redis", "apcu"
    |
    */
    'driver' => env('CACHE_DRIVER', 'file'),

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | A prefix added to all cache keys. Useful for avoiding collisions
    | when multiple applications share the same cache server.
    |
    */
    'prefix' => env('CACHE_PREFIX', 'lalaz_'),

    /*
    |--------------------------------------------------------------------------
    | Cache Stores
    |--------------------------------------------------------------------------
    |
    | Configuration for each available cache store.
    |
    */
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
            'password' => env('REDIS_PASSWORD'),
            'database' => env('REDIS_CACHE_DB', 1),
            'timeout' => 0.0,
        ],

        'apcu' => [
            'driver' => 'apcu',
        ],
    ],
];
```

## Store-Specific Configuration

### Array Store

No configuration required. Useful for testing.

```php
'array' => [
    'driver' => 'array',
],
```

### File Store

```php
'file' => [
    'driver' => 'file',
    
    // Directory for cache files (must be writable)
    'path' => storage_path('cache'),
],
```

### Redis Store

```php
'redis' => [
    'driver' => 'redis',
    
    // Redis server host
    'host' => '127.0.0.1',
    
    // Redis server port
    'port' => 6379,
    
    // Redis password (null for no auth)
    'password' => null,
    
    // Redis database number (0-15)
    'database' => 1,
    
    // Connection timeout in seconds
    'timeout' => 0.0,
],
```

### APCu Store

```php
'apcu' => [
    'driver' => 'apcu',
],
```

## Environment-Based Configuration

Use different configurations per environment:

```php
// config/cache.php
return [
    'driver' => env('CACHE_DRIVER', 'file'),
    
    'stores' => [
        'file' => [
            'driver' => 'file',
            'path' => env('CACHE_PATH', storage_path('cache')),
        ],
        
        'redis' => [
            'driver' => 'redis',
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', 6379),
        ],
    ],
];
```

**.env.local** (Development):
```env
CACHE_DRIVER=array
```

**.env.production**:
```env
CACHE_DRIVER=redis
REDIS_HOST=redis.internal
REDIS_PORT=6379
```

## Using Multiple Stores

Access different stores by name:

```php
$cache = new CacheManager($config);

// Default store
$default = $cache->store();

// Specific stores
$file = $cache->store('file');
$redis = $cache->store('redis');
```

## Disabling Cache

Set `enabled` to `false` to disable all caching:

```php
$cache = new CacheManager([
    'enabled' => false,
    'driver' => 'redis',
]);

// All operations now use NullStore
$cache->store()->set('key', 'value'); // Does nothing
$cache->store()->get('key');          // Returns null
```

## Prefix Strategy

The prefix helps avoid key collisions:

```php
// Application prefix
'prefix' => 'myapp_',

// Environment prefix
'prefix' => 'myapp_' . env('APP_ENV') . '_',

// Version prefix (for cache busting)
'prefix' => 'myapp_v2_',
```

## Runtime Configuration

You can also pass configuration at runtime:

```php
$cache = new CacheManager([
    'driver' => 'file',
    'prefix' => 'custom_',
    'stores' => [
        'file' => [
            'path' => '/custom/cache/path',
        ],
    ],
]);
```

## Recommended Configurations

### Development

```php
return [
    'enabled' => true,
    'driver' => 'array',  // Fast, no persistence
    'prefix' => 'dev_',
];
```

### Testing

```php
return [
    'enabled' => true,
    'driver' => 'array',
    'prefix' => 'test_',
];
```

### Staging

```php
return [
    'enabled' => true,
    'driver' => 'file',
    'prefix' => 'staging_',
    'stores' => [
        'file' => [
            'path' => storage_path('cache'),
        ],
    ],
];
```

### Production (Single Server)

```php
return [
    'enabled' => true,
    'driver' => 'apcu',  // Shared memory, very fast
    'prefix' => 'prod_',
];
```

### Production (Multiple Servers)

```php
return [
    'enabled' => true,
    'driver' => 'redis',  // Distributed cache
    'prefix' => 'prod_',
    'stores' => [
        'redis' => [
            'host' => 'redis.cluster.internal',
            'port' => 6379,
            'password' => env('REDIS_PASSWORD'),
            'database' => 1,
        ],
    ],
];
```
