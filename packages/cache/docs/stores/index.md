# Cache Stores

Lalaz Cache supports multiple storage backends, each optimized for different use cases.

## Available Stores

| Store | Type | Persistence | Shared | Use Case |
|-------|------|-------------|--------|----------|
| [Array](array.md) | Memory | No | No | Testing, single request |
| [File](file.md) | Disk | Yes | No | Simple deployments |
| [Redis](redis.md) | Network | Yes | Yes | Production, distributed |
| [APCu](apcu.md) | Memory | Yes* | Yes | Single server production |
| [Null](null.md) | None | No | N/A | Disabled caching |

\* APCu persists across requests but not server restarts.

## Choosing a Store

### For Development

Use **Array** store:
- Fast (in-memory)
- No cleanup needed
- Fresh cache each request

### For Testing

Use **Array** store:
- Isolated per test
- No file system pollution
- Predictable behavior

### For Simple Production

Use **File** store:
- No external dependencies
- Survives server restarts
- Good for single-server deployments

### For Scaling

Use **Redis** store:
- Shared across servers
- High performance
- Rich data structures

### For High Performance (Single Server)

Use **APCu** store:
- Shared memory (fastest)
- No serialization overhead
- No network latency

## Store Comparison

### Performance

```
Fastest ─────────────────────────────────────────── Slowest
APCu → Array → Redis → File
```

### Persistence

```
None ──────────────────────────────────────────── Full
Array → APCu → Redis/File
       (request)  (server)
```

### Scalability

```
Single Process ──────────────────────────── Distributed
Array → APCu → File → Redis
```

## Common Interface

All stores implement `CacheStoreInterface`:

```php
interface CacheStoreInterface
{
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value, int|DateInterval|null $ttl = null): bool;
    public function has(string $key): bool;
    public function delete(string $key): bool;
    public function clear(): bool;
    public function remember(string $key, int|DateInterval|null $ttl, callable $callback): mixed;
    public function forever(string $key, mixed $value): bool;
}
```

This ensures you can switch stores without changing your application code:

```php
// Works with any store
function cacheUser(CacheStoreInterface $cache, User $user): void
{
    $cache->set("user:{$user->id}", $user, 3600);
}
```

## Next Steps

- [Array Store](array.md) - In-memory store
- [File Store](file.md) - File-based store
- [Redis Store](redis.md) - Redis-backed store
- [APCu Store](apcu.md) - APCu store
- [Null Store](null.md) - No-op store
