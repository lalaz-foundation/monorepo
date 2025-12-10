# Lalaz Flash

Pluggable caching with array, file, APCu and Redis stores plus a per-request helper.

## Stores

- `array`: in-memory per-process with TTL.
- `file`: atomic file cache with TTL (default path from config).
- `apcu`: in-memory APCu (requires `ext-apcu`).
- `redis`: Redis via `ext-redis` or `predis/predis` if available.
- `null`: returned automatically when cache is disabled.

## Configuration

`config/flash.php` (published by the package manager):

```php
return [
    'enabled' => true,
    'driver' => 'array', // array | file | apcu | redis
    'prefix' => 'lalaz_',
    'stores' => [
        'file' => ['path' => sys_get_temp_dir() . '/lalaz-flash'],
        'redis' => [
            'host' => '127.0.0.1',
            'port' => 6379,
            'database' => 0,
            'password' => null,
            'timeout' => 0.0,
        ],
    ],
];
```

If no config is present the manager defaults to the array store with prefix `lalaz_`.

## Service Provider

`Lalaz\Flash\Providers\FlashServiceProvider` registers:

- `Lalaz\Flash\CacheManager` singleton, seeded from `Config::getArray('flash')` when available.
- `Lalaz\Flash\Contracts\CacheStoreInterface` binding to the default store.

## Usage

```php
use Lalaz\Flash\CacheManager;
use Lalaz\Flash\Contracts\CacheStoreInterface;

$manager = new CacheManager(); // or resolve from container
$cache = $manager->store(); // default driver

$cache->set('token', 'abc', 300);
$token = $cache->get('token', 'fallback');
$cached = $cache->remember('user:1', 600, fn () => $repo->find(1));
```

### Per-request cache

`Lalaz\Flash\PerRequestCache` is a lightweight in-memory cache with hit/miss stats:

```php
$local = new PerRequestCache();
$value = $local->remember('expensive', fn () => compute());
$stats = $local->stats(); // hits, misses, hit_rate, keys
```
