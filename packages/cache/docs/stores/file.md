# File Store

File-based cache with atomic writes and TTL support.

## Overview

The File store persists cache data to the filesystem. Each cache key is stored as a separate PHP file, enabling op-code caching and atomic operations.

## Configuration

```php
'stores' => [
    'file' => [
        'driver' => 'file',
        'path' => storage_path('cache'),
    ],
],
```

### Options

| Option | Type | Required | Description |
|--------|------|----------|-------------|
| `driver` | string | Yes | Must be `'file'` |
| `path` | string | No | Directory for cache files (defaults to system temp) |

## Direct Instantiation

```php
use Lalaz\Cache\Stores\FileStore;

$store = new FileStore('/var/cache/myapp', 'myprefix_');
```

## Features

### Atomic Writes

Files are written atomically using temporary files and rename:

```php
// This is safe from corruption during concurrent writes
$store->set('key', 'value');
```

### OPcache Integration

Cache files are PHP files, enabling OPcache acceleration:

```php
// Automatically invalidates OPcache when updating
$store->set('config', $config);
```

### File Locking

Read operations use shared locks for consistency:

```php
// Safe concurrent reads
$value = $store->get('key');
```

### Directory Creation

The cache directory is created automatically if it doesn't exist:

```php
$store = new FileStore('/new/cache/directory');
// Directory is created with 0755 permissions
```

## File Format

Cache files are PHP files returning the payload:

```php
<?php

return array (
  'value' => 'cached data here',
  'expires_at' => 1699999999,
);
```

This format:
- Enables OPcache optimization
- Provides type safety
- Makes debugging easy

## Use Cases

### Simple Deployments

```php
// No external dependencies needed
$cache = new CacheManager([
    'driver' => 'file',
    'stores' => [
        'file' => [
            'path' => '/var/www/app/storage/cache',
        ],
    ],
]);
```

### Configuration Caching

```php
$config = $store->remember('config:compiled', null, function () {
    return compileAllConfigFiles();
});
```

### Template Caching

```php
$compiled = $store->remember("template:{$name}", 86400, function () use ($name) {
    return compileTemplate($name);
});
```

## Directory Structure

```
/var/cache/myapp/
├── lalaz_user_1_abc123.php
├── lalaz_user_2_def456.php
├── lalaz_config_settings_789xyz.php
└── lalaz_session_abc_111222.php
```

File names include:
- Prefix (`lalaz_`)
- Sanitized key
- SHA1 hash for uniqueness

## Limitations

- **Slower than Memory** - Disk I/O is slower than RAM
- **Single Server** - Not shared across servers
- **Cleanup Required** - Expired files remain until accessed

## Best Practices

### Set Appropriate Permissions

```bash
# Ensure web server can write
chown -R www-data:www-data /var/cache/myapp
chmod -R 755 /var/cache/myapp
```

### Use Dedicated Directory

```php
// Good - dedicated cache directory
'path' => storage_path('cache'),

// Bad - mixed with other files
'path' => storage_path(),
```

### Implement Garbage Collection

Expired files are only deleted when accessed. Implement periodic cleanup:

```php
// Cron job or scheduled task
function cleanupCache(string $cacheDir): void
{
    $files = glob($cacheDir . '/lalaz_*');
    
    foreach ($files as $file) {
        // Reading triggers expiration check
        include $file;
    }
}
```

## Troubleshooting

### Permission Denied

```
CacheException: Unable to create cache directory
```

**Solution:** Check directory permissions:

```bash
sudo chown -R www-data:www-data /var/cache/myapp
sudo chmod -R 755 /var/cache/myapp
```

### Disk Full

```
file_put_contents(): failed to open stream
```

**Solution:** Clear old cache files or increase disk space:

```bash
rm -rf /var/cache/myapp/*
```

### Slow Performance

**Solution:** 
1. Enable OPcache
2. Use SSD storage
3. Consider Redis for high-traffic sites

## Example

```php
use Lalaz\Cache\Stores\FileStore;

$store = new FileStore('/var/cache/app', 'myapp_');

// Store user data
$store->set('user:123', [
    'id' => 123,
    'name' => 'John Doe',
    'email' => 'john@example.com',
], 3600);

// Retrieve (even after process restart)
$user = $store->get('user:123');

// Remember pattern for expensive operations
$report = $store->remember('report:daily', 86400, function () {
    return generateDailyReport();
});

// Clear all cache files
$store->clear();
```
