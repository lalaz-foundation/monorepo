# Remember Pattern

The remember pattern is one of the most powerful features of Lalaz Cache. It combines checking, fetching, and storing in a single operation.

## Basic Usage

```php
$value = $store->remember('key', $ttl, function () {
    return expensiveOperation();
});
```

### How It Works

1. **Check**: Is the key in cache?
2. **Return**: If yes, return cached value
3. **Execute**: If no, execute the callback
4. **Store**: Cache the callback result
5. **Return**: Return the result

```
┌─────────────────────────────────────┐
│         remember('key', ttl, fn)    │
└─────────────────┬───────────────────┘
                  │
                  ▼
          ┌───────────────┐
          │  has('key')?  │
          └───────┬───────┘
                  │
         ┌───────┴───────┐
         │               │
        Yes              No
         │               │
         ▼               ▼
    ┌─────────┐   ┌──────────────┐
    │get('key')│   │  callback()  │
    └────┬────┘   └──────┬───────┘
         │               │
         │               ▼
         │        ┌──────────────┐
         │        │set('key', v) │
         │        └──────┬───────┘
         │               │
         └───────┬───────┘
                 │
                 ▼
          ┌──────────────┐
          │ return value │
          └──────────────┘
```

## Examples

### Caching Database Queries

```php
// Cache user data for 1 hour
$user = $store->remember("user:{$id}", 3600, function () use ($id) {
    return User::find($id);
});
```

### Caching API Responses

```php
// Cache external API response for 5 minutes
$weather = $store->remember('weather:nyc', 300, function () {
    $response = Http::get('https://api.weather.com/nyc');
    return $response->json();
});
```

### Caching Computed Values

```php
// Cache expensive calculation for 1 day
$report = $store->remember('report:monthly', 86400, function () {
    return generateMonthlyReport();
});
```

### Caching with Dynamic Keys

```php
// Cache per-user data
$preferences = $store->remember("user:{$userId}:preferences", 3600, function () use ($userId) {
    return UserPreference::where('user_id', $userId)->get();
});
```

## TTL Options

### With Seconds

```php
$store->remember('key', 3600, $callback); // 1 hour
```

### With DateInterval

```php
$store->remember('key', new DateInterval('PT1H'), $callback); // 1 hour
$store->remember('key', new DateInterval('P1D'), $callback);  // 1 day
```

### Forever (No Expiration)

```php
$store->remember('key', null, $callback); // Never expires
```

## Caching Null Values

The remember pattern correctly handles `null` values:

```php
// First call: callback returns null, it gets cached
$result = $store->remember('nullable', 3600, function () {
    return null;
});

// Second call: returns cached null (callback NOT called)
$result = $store->remember('nullable', 3600, function () {
    return 'fallback'; // This won't be executed
});

echo $result; // null
```

## Callback Behavior

### Single Execution

The callback is only executed on cache miss:

```php
$callCount = 0;

// First call: callback executes
$store->remember('key', 3600, function () use (&$callCount) {
    $callCount++;
    return 'value';
});

// Second call: callback NOT executed
$store->remember('key', 3600, function () use (&$callCount) {
    $callCount++;
    return 'new_value';
});

echo $callCount; // 1
```

### Closures with Dependencies

```php
$userId = 123;
$includeProfile = true;

$user = $store->remember("user:{$userId}", 3600, function () use ($userId, $includeProfile) {
    $query = User::query()->where('id', $userId);
    
    if ($includeProfile) {
        $query->with('profile');
    }
    
    return $query->first();
});
```

## Common Patterns

### Cache with Fallback

```php
function getUserWithCache(int $id): ?User
{
    return $this->cache->remember("user:{$id}", 3600, function () use ($id) {
        return User::find($id);
    });
}
```

### Cache Collection

```php
$posts = $store->remember('posts:featured', 600, function () {
    return Post::where('featured', true)
        ->orderBy('created_at', 'desc')
        ->limit(10)
        ->get();
});
```

### Cache with Tags (Manual)

```php
// Store with a tag reference
$user = $store->remember("user:{$id}", 3600, function () use ($id) {
    return User::find($id);
});

// Track tagged keys for invalidation
$store->remember('tags:user', null, fn() => []);
$tags = $store->get('tags:user');
$tags[] = "user:{$id}";
$store->set('tags:user', array_unique($tags));
```

### Cache Warming

```php
// Pre-populate cache during off-peak hours
function warmCache(): void
{
    $users = User::popular()->get();
    
    foreach ($users as $user) {
        $this->cache->remember("user:{$user->id}", 3600, fn() => $user);
    }
}
```

## Best Practices

### 1. Use Descriptive Keys

```php
// Good
$store->remember('user:123:profile', ...);
$store->remember('posts:category:tech:page:1', ...);

// Avoid
$store->remember('u123', ...);
$store->remember('data', ...);
```

### 2. Set Appropriate TTL

```php
// Frequently changing: short TTL
$store->remember('user:123:status', 60, ...); // 1 minute

// Stable data: longer TTL
$store->remember('config:site_settings', 86400, ...); // 1 day
```

### 3. Handle Exceptions

```php
try {
    $data = $store->remember('key', 3600, function () {
        return riskyOperation();
    });
} catch (Exception $e) {
    // Don't cache failures
    return fallbackValue();
}
```

### 4. Invalidate When Data Changes

```php
public function updateUser(int $id, array $data): User
{
    $user = User::find($id);
    $user->update($data);
    
    // Clear cached data
    $this->cache->delete("user:{$id}");
    $this->cache->delete("user:{$id}:profile");
    
    return $user;
}
```

## See Also

- [Basic Operations](./basic-operations.md) - All cache operations
- [Per-Request Cache](./per-request-cache.md) - Request-scoped caching
- [Examples](./examples/basic.md) - More usage examples
