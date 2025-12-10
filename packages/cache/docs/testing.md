# Testing

Strategies for testing code that uses caching.

## Using ArrayStore

The `ArrayStore` is ideal for testing:

```php
use Lalaz\Cache\Stores\ArrayStore;
use PHPUnit\Framework\TestCase;

class UserServiceTest extends TestCase
{
    private ArrayStore $cache;
    private UserService $service;
    
    protected function setUp(): void
    {
        $this->cache = new ArrayStore();
        $this->service = new UserService($this->cache);
    }
    
    public function testCachesUser(): void
    {
        $user = $this->service->find(123);
        
        $this->assertTrue($this->cache->has('user:123'));
        $this->assertEquals($user, $this->cache->get('user:123'));
    }
    
    protected function tearDown(): void
    {
        $this->cache->clear();
    }
}
```

## Using NullStore

Test behavior without caching:

```php
use Lalaz\Cache\Stores\NullStore;

public function testServiceWorksWithoutCache(): void
{
    $cache = new NullStore();
    $service = new UserService($cache);
    
    // Service still works, just doesn't cache
    $user1 = $service->find(123);
    $user2 = $service->find(123);  // Fetches again (not cached)
    
    $this->assertEquals($user1, $user2);
}
```

## Testing Cache Hits/Misses

```php
public function testRememberCachesResult(): void
{
    $cache = new ArrayStore();
    $callCount = 0;
    
    $result1 = $cache->remember('key', 3600, function () use (&$callCount) {
        $callCount++;
        return 'computed';
    });
    
    $result2 = $cache->remember('key', 3600, function () use (&$callCount) {
        $callCount++;
        return 'computed';
    });
    
    $this->assertEquals(1, $callCount);  // Callback only called once
    $this->assertEquals('computed', $result1);
    $this->assertEquals('computed', $result2);
}
```

## Testing TTL Expiration

```php
public function testItemExpires(): void
{
    $cache = new ArrayStore();
    
    $cache->set('key', 'value', 1);  // 1 second TTL
    
    $this->assertTrue($cache->has('key'));
    
    sleep(2);  // Wait for expiration
    
    $this->assertFalse($cache->has('key'));
    $this->assertNull($cache->get('key'));
}
```

## Testing PerRequestCache

```php
use Lalaz\Cache\PerRequestCache;

public function testPerRequestCacheStats(): void
{
    $cache = new PerRequestCache();
    
    // First access - miss
    $cache->remember('user', fn() => 'John');
    
    // Subsequent accesses - hits
    $cache->get('user');
    $cache->get('user');
    $cache->get('user');
    
    $stats = $cache->stats();
    
    $this->assertEquals(1, $stats['total_items']);
    $this->assertEquals(3, $stats['total_hits']);
    $this->assertEquals(1, $stats['total_misses']);
    $this->assertEquals(75.0, $stats['hit_rate']);
}
```

## Mocking Cache Store

```php
use Lalaz\Cache\Contracts\CacheStoreInterface;

public function testHandlesCacheFailure(): void
{
    $cache = $this->createMock(CacheStoreInterface::class);
    $cache->method('get')->willThrowException(new \Exception('Connection failed'));
    
    $service = new UserService($cache);
    
    // Service should handle cache failure gracefully
    $user = $service->findWithFallback(123);
    
    $this->assertNotNull($user);
}
```

## Testing Cache Invalidation

```php
public function testDeleteInvalidatesCache(): void
{
    $cache = new ArrayStore();
    $repo = new UserRepository($cache);
    
    // Cache the user
    $user = $repo->find(123);
    $this->assertTrue($cache->has('user:123'));
    
    // Delete should invalidate cache
    $repo->delete(123);
    $this->assertFalse($cache->has('user:123'));
}
```

## Testing with CacheManager

```php
use Lalaz\Cache\CacheManager;

public function testMultipleStores(): void
{
    $manager = new CacheManager([
        'driver' => 'array',
        'stores' => [
            'array' => ['driver' => 'array'],
            'secondary' => ['driver' => 'array'],
        ],
    ]);
    
    $manager->store('array')->set('key', 'value1');
    $manager->store('secondary')->set('key', 'value2');
    
    $this->assertEquals('value1', $manager->store('array')->get('key'));
    $this->assertEquals('value2', $manager->store('secondary')->get('key'));
}
```

## Testing Cache Disabled

```php
public function testCacheDisabled(): void
{
    $manager = new CacheManager([
        'enabled' => false,
        'driver' => 'array',
    ]);
    
    $store = $manager->store();
    
    // Operations succeed but don't cache
    $store->set('key', 'value');
    $this->assertFalse($store->has('key'));
    
    // Remember always executes callback
    $callCount = 0;
    $store->remember('key', 3600, function () use (&$callCount) {
        $callCount++;
        return 'result';
    });
    $store->remember('key', 3600, function () use (&$callCount) {
        $callCount++;
        return 'result';
    });
    
    $this->assertEquals(2, $callCount);
}
```

## Integration Testing with File Store

```php
public function testFileStoreIntegration(): void
{
    $tempDir = sys_get_temp_dir() . '/cache_test_' . uniqid();
    
    try {
        $store = new FileStore($tempDir, 'test_');
        
        $store->set('key', ['data' => 'value'], 3600);
        
        // Simulate new process - create fresh store
        $newStore = new FileStore($tempDir, 'test_');
        
        $this->assertEquals(['data' => 'value'], $newStore->get('key'));
    } finally {
        // Cleanup
        array_map('unlink', glob($tempDir . '/*'));
        rmdir($tempDir);
    }
}
```

## Test Helpers

Create reusable test traits:

```php
trait CacheTestHelpers
{
    protected function createTestCache(): ArrayStore
    {
        return new ArrayStore('test_');
    }
    
    protected function assertCached(CacheStoreInterface $cache, string $key): void
    {
        $this->assertTrue($cache->has($key), "Key '{$key}' should be cached");
    }
    
    protected function assertNotCached(CacheStoreInterface $cache, string $key): void
    {
        $this->assertFalse($cache->has($key), "Key '{$key}' should not be cached");
    }
    
    protected function assertCacheEquals(
        CacheStoreInterface $cache,
        string $key,
        mixed $expected
    ): void {
        $this->assertEquals($expected, $cache->get($key));
    }
}
```

## Best Practices

| Practice | Description |
|----------|-------------|
| **Use ArrayStore** | Fast, no cleanup needed |
| **Clear between tests** | Prevent test pollution |
| **Test cache misses** | Verify callback execution |
| **Test expiration** | Verify TTL behavior |
| **Test disabled state** | Ensure graceful degradation |
| **Avoid real Redis/APCu** | Use mocks or ArrayStore |
