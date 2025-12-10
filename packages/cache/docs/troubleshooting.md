# Troubleshooting

Common issues and solutions when using Lalaz Cache.

## Connection Issues

### Redis Connection Refused

```
CacheException: Connection refused
```

**Causes:**
1. Redis server not running
2. Wrong host/port configuration
3. Firewall blocking connection

**Solutions:**

```bash
# Check if Redis is running
redis-cli ping
# Should return: PONG

# Check Redis is listening
netstat -tlnp | grep 6379

# Start Redis
sudo systemctl start redis
```

### Redis Authentication Failed

```
NOAUTH Authentication required
```

**Solution:** Add password to configuration:

```php
'redis' => [
    'driver' => 'redis',
    'password' => env('REDIS_PASSWORD'),
]
```

### APCu Not Available

```
CacheException: APCu extension is required
```

**Solution:** Install and enable APCu:

```bash
# Install
sudo apt-get install php-apcu

# Verify
php -m | grep apcu

# Restart PHP
sudo systemctl restart php-fpm
```

---

## Performance Issues

### Slow Cache Performance

**Symptoms:**
- High response times
- Cache operations taking >100ms

**Solutions:**

1. **File Store:** Switch to Redis or APCu

```php
'driver' => 'redis',  // Instead of 'file'
```

2. **Redis Latency:** Use local Redis or reduce network hops

```php
'host' => '127.0.0.1',  // Local is faster than remote
```

3. **Large Values:** Compress or split large cached items

```php
// Before caching
$compressed = gzcompress(serialize($largeData));
$cache->set('key', $compressed);

// When retrieving
$data = unserialize(gzuncompress($cache->get('key')));
```

### Memory Exhaustion

**Symptoms:**
- "Allowed memory size exhausted" errors
- Server running out of memory

**Solutions:**

1. **APCu:** Increase shared memory

```ini
; php.ini
apc.shm_size=128M
```

2. **Redis:** Set memory limit and eviction policy

```redis
maxmemory 256mb
maxmemory-policy allkeys-lru
```

3. **Application:** Use shorter TTLs

```php
$cache->set('key', $value, 300);  // 5 minutes instead of 1 hour
```

---

## Data Issues

### Stale Data

**Symptoms:**
- Changes not reflected immediately
- Old data being served

**Solutions:**

1. **Invalidate on update:**

```php
function updateUser(int $id, array $data): void
{
    // Update database
    DB::update('users', $data, $id);
    
    // Invalidate cache
    $cache->delete("user:{$id}");
}
```

2. **Use shorter TTLs:**

```php
// For frequently changing data
$cache->set('stats', $stats, 60);  // 1 minute
```

3. **Version keys:**

```php
$version = $cache->get('users:version', 1);
$key = "user:{$id}:v{$version}";

// On update, increment version
$cache->set('users:version', $version + 1);
```

### Serialization Errors

```
unserialize(): Error at offset X
```

**Causes:**
1. Cached object class definition changed
2. Corruption during write

**Solutions:**

1. **Clear affected cache:**

```php
$cache->delete('problematic:key');
// or
$cache->clear();
```

2. **Use arrays instead of objects:**

```php
// Instead of caching objects
$cache->set('user', $user);

// Cache arrays
$cache->set('user', $user->toArray());
```

3. **Handle errors gracefully:**

```php
try {
    $value = $cache->get('key');
} catch (\Throwable $e) {
    $cache->delete('key');
    $value = computeFreshValue();
}
```

---

## File Store Issues

### Permission Denied

```
Unable to create cache directory
```

**Solution:**

```bash
# Create directory
sudo mkdir -p /var/cache/myapp

# Set ownership
sudo chown -R www-data:www-data /var/cache/myapp

# Set permissions
sudo chmod -R 755 /var/cache/myapp
```

### Disk Full

```
file_put_contents(): failed to open stream
```

**Solutions:**

1. **Clear cache:**

```bash
rm -rf /var/cache/myapp/*
```

2. **Add disk space or use smaller TTLs**

3. **Implement garbage collection:**

```php
// Cron job to clear old files
find /var/cache/myapp -mtime +7 -delete
```

### Too Many Files

**Symptoms:**
- Slow file operations
- "Too many open files" errors

**Solutions:**

1. **Use Redis instead of File store**

2. **Implement cache namespacing:**

```php
// Subdirectories for organization
$path = sprintf('%s/%s/%s', $baseDir, substr($hash, 0, 2), $hash);
```

3. **Regular cleanup:**

```php
$cache->clear();  // Periodically clear all
```

---

## Configuration Issues

### Driver Not Found

```
CacheException: Unsupported cache driver 'memcached'
```

**Solution:** Use supported driver:

```php
// Supported: array, file, redis, apcu
'driver' => 'redis',
```

### Wrong Configuration Structure

```php
// Wrong
'stores' => [
    'driver' => 'redis',
    'host' => '127.0.0.1',
]

// Correct
'stores' => [
    'redis' => [
        'driver' => 'redis',
        'host' => '127.0.0.1',
    ],
]
```

---

## Testing Issues

### Cache Persists Between Tests

**Solution:** Clear cache in tearDown:

```php
protected function tearDown(): void
{
    $this->cache->clear();
}
```

### Different Behavior in Tests

**Cause:** Using different store in tests vs production

**Solution:** Use ArrayStore for tests:

```php
// phpunit.xml
<env name="CACHE_DRIVER" value="array"/>
```

---

## Debugging Tips

### Check Cache Contents (Redis)

```bash
# List all keys
redis-cli KEYS "lalaz_*"

# Get specific key
redis-cli GET "lalaz_user:123"

# Check TTL
redis-cli TTL "lalaz_user:123"
```

### Check Cache Contents (File)

```bash
# List cache files
ls -la /var/cache/myapp/

# View file contents
cat /var/cache/myapp/lalaz_user_123_*.php
```

### Log Cache Operations

```php
class LoggingCache implements CacheStoreInterface
{
    public function __construct(
        private CacheStoreInterface $store,
        private LoggerInterface $logger
    ) {}
    
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->store->get($key, $default);
        $this->logger->debug('Cache GET', [
            'key' => $key,
            'hit' => $value !== $default,
        ]);
        return $value;
    }
    
    // ... wrap other methods similarly
}
```

### Monitor Hit Rate

```php
// Use PerRequestCache for statistics
$stats = $cache->stats();

if ($stats['hit_rate'] < 50) {
    $logger->warning('Low cache hit rate', $stats);
}
```
