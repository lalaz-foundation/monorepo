# APCu Store

Shared memory cache using PHP's APCu extension.

## Overview

The APCu store uses PHP's APCu (APC User Cache) extension for shared memory caching. It provides extremely fast cache access by storing data in shared memory segments.

## Requirements

- APCu PHP extension

```bash
# Ubuntu/Debian
sudo apt-get install php-apcu

# macOS with Homebrew
pecl install apcu

# Enable in php.ini
apc.enable_cli=1  # For CLI scripts
```

## Configuration

```php
'stores' => [
    'apcu' => [
        'driver' => 'apcu',
    ],
],
```

No additional configuration required.

## Direct Instantiation

```php
use Lalaz\Cache\Stores\ApcuStore;

$store = new ApcuStore('myprefix_');
```

## Features

### Shared Memory

Data is stored in shared memory, accessible by all PHP processes:

```
┌──────────────────────────────────────┐
│           Shared Memory              │
│  ┌────────────────────────────────┐  │
│  │         APCu Cache             │  │
│  │                                │  │
│  │  key1 => value1                │  │
│  │  key2 => value2                │  │
│  │  key3 => value3                │  │
│  │                                │  │
│  └────────────────────────────────┘  │
└──────────────────────────────────────┘
        ▲           ▲           ▲
        │           │           │
   ┌────┴──┐   ┌────┴──┐   ┌────┴──┐
   │ PHP   │   │ PHP   │   │ PHP   │
   │Worker1│   │Worker2│   │Worker3│
   └───────┘   └───────┘   └───────┘
```

### No Serialization Overhead

APCu stores PHP values directly (for simple types):

```php
// Arrays and objects are stored efficiently
$store->set('config', ['debug' => true]);
```

### Atomic Operations

APCu provides atomic operations:

```php
$store->set('counter', $value);  // Atomic write
```

## Performance

APCu is typically the fastest caching option:

| Operation | APCu | Redis | File |
|-----------|------|-------|------|
| Read | ~0.01ms | ~0.1ms | ~1ms |
| Write | ~0.01ms | ~0.1ms | ~5ms |

## Use Cases

### High-Performance Single Server

```php
// Best for single-server deployments
$cache = new CacheManager([
    'driver' => 'apcu',
]);
```

### OPcache Complement

```php
// OPcache for code, APCu for data
$config = $store->remember('config', null, function () {
    return parseConfigFiles();
});
```

### Frequently Accessed Data

```php
// Perfect for hot data
$store->forever('countries', getCountryList());
$store->forever('currencies', getCurrencyList());
```

## Configuration (php.ini)

```ini
; Enable APCu
apc.enabled=1

; Memory size (adjust based on your needs)
apc.shm_size=64M

; Number of shared memory segments
apc.shm_segments=1

; TTL for garbage collection
apc.ttl=0

; Enable for CLI (useful for testing/cron)
apc.enable_cli=1
```

## Monitoring

### Check APCu Status

```php
$info = apcu_cache_info();

echo "Memory: " . $info['mem_size'] . " bytes\n";
echo "Entries: " . $info['num_entries'] . "\n";
echo "Hits: " . $info['num_hits'] . "\n";
echo "Misses: " . $info['num_misses'] . "\n";
```

### Memory Usage

```php
$sma = apcu_sma_info();

$total = $sma['seg_size'];
$available = $sma['avail_mem'];
$used = $total - $available;

echo "Used: " . round($used / 1024 / 1024, 2) . " MB\n";
echo "Available: " . round($available / 1024 / 1024, 2) . " MB\n";
```

## Limitations

- **Single Server** - Not shared across servers
- **Memory Bound** - Limited by shared memory size
- **Lost on Restart** - Data cleared when PHP/server restarts
- **CLI Separate** - CLI and web have separate caches (unless `apc.enable_cli=1`)

## When to Use

✅ Single server deployments  
✅ High-performance caching needs  
✅ Frequently accessed, small data  
✅ Web application caching

❌ Multi-server deployments  
❌ Large datasets  
❌ Data that must survive restarts  
❌ CLI-heavy applications

## Best Practices

### Monitor Memory

```php
// Check before operations
$sma = apcu_sma_info();
if ($sma['avail_mem'] < 1024 * 1024) {  // < 1MB available
    // Memory low, consider clearing old entries
    $store->clear();
}
```

### Use Appropriate TTLs

```php
// Don't fill memory with long-lived entries
$store->set('temporary', $value, 300);  // 5 minutes

// Reserve forever() for small, essential data
$store->forever('config', $config);
```

### Graceful Degradation

```php
function getCached(string $key, callable $fallback): mixed
{
    if (!extension_loaded('apcu') || !apcu_enabled()) {
        return $fallback();
    }
    
    return $store->remember($key, 3600, $fallback);
}
```

## Troubleshooting

### APCu Not Enabled

```
CacheException: APCu extension is required
```

**Solution:** Install and enable APCu:

```bash
sudo apt-get install php-apcu
sudo systemctl restart php-fpm
```

### Memory Full

```
Warning: apcu_store(): Unable to allocate memory
```

**Solution:** Increase memory or clear cache:

```ini
; php.ini
apc.shm_size=128M
```

Or clear programmatically:

```php
$store->clear();
```

### CLI Cache Empty

**Solution:** Enable CLI caching:

```ini
; php.ini
apc.enable_cli=1
```

## Example

```php
use Lalaz\Cache\Stores\ApcuStore;

$store = new ApcuStore('myapp_');

// Store user session data
$store->set('session:abc123', [
    'user_id' => 123,
    'permissions' => ['read', 'write'],
], 1800);  // 30 minutes

// Retrieve
$session = $store->get('session:abc123');

// Remember pattern
$navigation = $store->remember('nav:main', 3600, function () {
    return buildNavigationMenu();
});

// Forever for static data
$store->forever('timezones', DateTimeZone::listIdentifiers());

// Clear all keys with prefix
$store->clear();
```
