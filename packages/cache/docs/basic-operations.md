# Basic Operations

The cache interface provides a simple and consistent API for all cache stores.

## Storing Values

### set()

Store a value with an optional TTL (time-to-live):

```php
// Store for 1 hour (3600 seconds)
$store->set('key', 'value', 3600);

// Store with DateInterval
$store->set('key', 'value', new DateInterval('PT1H'));

// Store forever (no expiration)
$store->set('key', 'value');
$store->set('key', 'value', null);
```

### forever()

Store a value that never expires:

```php
$store->forever('config:settings', $settings);
```

This is equivalent to `set()` with `null` TTL.

## Retrieving Values

### get()

Retrieve a cached value:

```php
// Get value or null
$value = $store->get('key');

// Get value or default
$value = $store->get('key', 'default');

// Get with complex default
$value = $store->get('missing', ['empty' => true]);
```

### has()

Check if a key exists (and is not expired):

```php
if ($store->has('key')) {
    $value = $store->get('key');
}
```

## Removing Values

### delete()

Remove a single key:

```php
$deleted = $store->delete('key');
// true if key existed and was deleted
// false if key didn't exist
```

### clear()

Remove all keys with the configured prefix:

```php
$store->clear();
```

**Warning:** This removes all cache entries for this store.

## Remember Pattern

### remember()

Cache expensive computations automatically:

```php
$user = $store->remember('user:123', 3600, function () {
    // This only runs if key doesn't exist
    return User::find(123);
});
```

The callback is only executed when:
- The key doesn't exist
- The key has expired

This pattern is perfect for:
- Database queries
- API calls
- Complex calculations

### Examples

```php
// Cache database query
$users = $store->remember('users:active', 300, function () {
    return DB::table('users')->where('active', true)->get();
});

// Cache API response
$weather = $store->remember('weather:nyc', 1800, function () {
    return $http->get('https://api.weather.com/nyc');
});

// Cache computed result
$stats = $store->remember('stats:daily', 86400, function () {
    return [
        'total_users' => User::count(),
        'total_orders' => Order::count(),
        'revenue' => Order::sum('amount'),
    ];
});
```

## Working with Complex Data

The cache stores handle serialization automatically:

```php
// Store array
$store->set('config', ['debug' => true, 'timezone' => 'UTC']);

// Store object
$store->set('user', new User(['name' => 'John']));

// Retrieve (automatically unserialized)
$config = $store->get('config'); // array
$user = $store->get('user');     // User object
```

## TTL Options

TTL can be specified as:

### Seconds (int)

```php
$store->set('key', 'value', 60);       // 1 minute
$store->set('key', 'value', 3600);     // 1 hour
$store->set('key', 'value', 86400);    // 1 day
$store->set('key', 'value', 604800);   // 1 week
```

### DateInterval

```php
$store->set('key', 'value', new DateInterval('PT30M'));  // 30 minutes
$store->set('key', 'value', new DateInterval('P1D'));    // 1 day
$store->set('key', 'value', new DateInterval('P1W'));    // 1 week
```

### Null (Forever)

```php
$store->set('key', 'value', null);  // Never expires
$store->forever('key', 'value');     // Same as above
```

### Zero or Negative (Immediate Expiration)

```php
$store->set('key', 'value', 0);   // Immediately expired (deleted)
$store->set('key', 'value', -1);  // Immediately expired (deleted)
```

## Common Patterns

### Cache-Aside Pattern

```php
function getUser(int $id): ?User
{
    $key = "user:{$id}";
    
    // Try cache first
    $user = $store->get($key);
    
    if ($user === null) {
        // Cache miss - load from database
        $user = User::find($id);
        
        if ($user !== null) {
            // Store in cache for next time
            $store->set($key, $user, 3600);
        }
    }
    
    return $user;
}
```

### Write-Through Pattern

```php
function updateUser(int $id, array $data): User
{
    // Update database
    $user = User::find($id);
    $user->update($data);
    
    // Update cache
    $store->set("user:{$id}", $user, 3600);
    
    return $user;
}
```

### Cache Invalidation

```php
function deleteUser(int $id): void
{
    // Delete from database
    User::destroy($id);
    
    // Remove from cache
    $store->delete("user:{$id}");
}
```

### Grouped Keys

```php
// Store user-related data with consistent naming
$store->set("user:{$id}:profile", $profile, 3600);
$store->set("user:{$id}:settings", $settings, 3600);
$store->set("user:{$id}:permissions", $permissions, 3600);

// Clear all user data
$store->delete("user:{$id}:profile");
$store->delete("user:{$id}:settings");
$store->delete("user:{$id}:permissions");
```

## Error Handling

Cache operations are designed to be fault-tolerant:

```php
try {
    $value = $store->get('key', 'fallback');
} catch (CacheException $e) {
    // Handle cache failure
    $value = 'fallback';
}
```

For critical data, always have a fallback:

```php
$user = $store->get("user:{$id}");

if ($user === null) {
    // Cache miss or error - load from source
    $user = User::find($id);
}
```
