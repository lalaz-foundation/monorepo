# Examples

Practical examples for common caching scenarios.

## Database Query Caching

```php
class UserRepository
{
    public function __construct(
        private CacheStoreInterface $cache,
        private PDO $db
    ) {}
    
    public function find(int $id): ?array
    {
        return $this->cache->remember("user:{$id}", 3600, function () use ($id) {
            $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ?');
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        });
    }
    
    public function update(int $id, array $data): void
    {
        // Update database
        $stmt = $this->db->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?');
        $stmt->execute([$data['name'], $data['email'], $id]);
        
        // Invalidate cache
        $this->cache->delete("user:{$id}");
    }
    
    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);
        
        $this->cache->delete("user:{$id}");
    }
}
```

## API Response Caching

```php
class WeatherService
{
    public function __construct(
        private CacheStoreInterface $cache,
        private HttpClient $http
    ) {}
    
    public function getCurrentWeather(string $city): array
    {
        $key = "weather:{$city}:" . date('Y-m-d-H');  // Cache per hour
        
        return $this->cache->remember($key, 3600, function () use ($city) {
            $response = $this->http->get("https://api.weather.com/v1/current", [
                'query' => ['city' => $city]
            ]);
            
            return json_decode($response->getBody(), true);
        });
    }
    
    public function getForecast(string $city, int $days = 7): array
    {
        $key = "forecast:{$city}:{$days}:" . date('Y-m-d');  // Cache per day
        
        return $this->cache->remember($key, 86400, function () use ($city, $days) {
            $response = $this->http->get("https://api.weather.com/v1/forecast", [
                'query' => ['city' => $city, 'days' => $days]
            ]);
            
            return json_decode($response->getBody(), true);
        });
    }
}
```

## Configuration Caching

```php
class ConfigManager
{
    public function __construct(
        private CacheStoreInterface $cache,
        private string $configPath
    ) {}
    
    public function get(string $key, mixed $default = null): mixed
    {
        $config = $this->loadConfig();
        return $this->getNestedValue($config, $key, $default);
    }
    
    public function all(): array
    {
        return $this->loadConfig();
    }
    
    public function refresh(): void
    {
        $this->cache->delete('config:compiled');
    }
    
    private function loadConfig(): array
    {
        return $this->cache->remember('config:compiled', null, function () {
            $config = [];
            
            foreach (glob($this->configPath . '/*.php') as $file) {
                $name = basename($file, '.php');
                $config[$name] = require $file;
            }
            
            return $config;
        });
    }
    
    private function getNestedValue(array $array, string $key, mixed $default): mixed
    {
        $keys = explode('.', $key);
        $value = $array;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
}
```

## Session Management

```php
class CacheSession
{
    private string $prefix = 'session:';
    private int $ttl = 7200;  // 2 hours
    
    public function __construct(
        private CacheStoreInterface $cache
    ) {}
    
    public function start(): string
    {
        $sessionId = bin2hex(random_bytes(32));
        $this->cache->set($this->prefix . $sessionId, [
            'created_at' => time(),
            'data' => [],
        ], $this->ttl);
        
        return $sessionId;
    }
    
    public function get(string $sessionId, string $key, mixed $default = null): mixed
    {
        $session = $this->cache->get($this->prefix . $sessionId);
        
        if ($session === null) {
            return $default;
        }
        
        return $session['data'][$key] ?? $default;
    }
    
    public function set(string $sessionId, string $key, mixed $value): void
    {
        $session = $this->cache->get($this->prefix . $sessionId, [
            'created_at' => time(),
            'data' => [],
        ]);
        
        $session['data'][$key] = $value;
        
        $this->cache->set($this->prefix . $sessionId, $session, $this->ttl);
    }
    
    public function destroy(string $sessionId): void
    {
        $this->cache->delete($this->prefix . $sessionId);
    }
    
    public function regenerate(string $oldSessionId): string
    {
        $data = $this->cache->get($this->prefix . $oldSessionId);
        $this->destroy($oldSessionId);
        
        $newSessionId = bin2hex(random_bytes(32));
        
        if ($data !== null) {
            $this->cache->set($this->prefix . $newSessionId, $data, $this->ttl);
        }
        
        return $newSessionId;
    }
}
```

## Rate Limiting

```php
class RateLimiter
{
    public function __construct(
        private CacheStoreInterface $cache
    ) {}
    
    public function attempt(string $key, int $maxAttempts, int $windowSeconds): bool
    {
        $attempts = $this->cache->get($key, 0);
        
        if ($attempts >= $maxAttempts) {
            return false;
        }
        
        $this->cache->set($key, $attempts + 1, $windowSeconds);
        
        return true;
    }
    
    public function remaining(string $key, int $maxAttempts): int
    {
        $attempts = $this->cache->get($key, 0);
        return max(0, $maxAttempts - $attempts);
    }
    
    public function reset(string $key): void
    {
        $this->cache->delete($key);
    }
    
    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return $this->cache->get($key, 0) >= $maxAttempts;
    }
}

// Usage
$limiter = new RateLimiter($cache);

$key = "rate:{$userId}:api";

if (!$limiter->attempt($key, 60, 60)) {  // 60 requests per minute
    throw new RateLimitException('Too many requests');
}
```

## Feature Flags

```php
class FeatureFlags
{
    public function __construct(
        private CacheStoreInterface $cache,
        private FeatureFlagRepository $repository
    ) {}
    
    public function isEnabled(string $feature, ?int $userId = null): bool
    {
        $flags = $this->loadFlags();
        
        if (!isset($flags[$feature])) {
            return false;
        }
        
        $flag = $flags[$feature];
        
        // Globally disabled
        if (!$flag['enabled']) {
            return false;
        }
        
        // 100% rollout
        if ($flag['percentage'] === 100) {
            return true;
        }
        
        // User-specific check
        if ($userId !== null) {
            // Consistent hashing for stable user experience
            $hash = crc32("{$feature}:{$userId}") % 100;
            return $hash < $flag['percentage'];
        }
        
        return false;
    }
    
    public function refresh(): void
    {
        $this->cache->delete('feature_flags');
    }
    
    private function loadFlags(): array
    {
        return $this->cache->remember('feature_flags', 300, function () {
            return $this->repository->all();
        });
    }
}

// Usage
if ($features->isEnabled('new_checkout', $user->id)) {
    return $this->newCheckout($cart);
} else {
    return $this->legacyCheckout($cart);
}
```

## Computed Statistics

```php
class StatsService
{
    public function __construct(
        private CacheStoreInterface $cache,
        private PDO $db
    ) {}
    
    public function getDashboardStats(): array
    {
        return $this->cache->remember('stats:dashboard', 300, function () {
            return [
                'users' => $this->countUsers(),
                'orders' => $this->countOrders(),
                'revenue' => $this->calculateRevenue(),
                'generated_at' => date('c'),
            ];
        });
    }
    
    public function getHourlyStats(): array
    {
        $hour = date('Y-m-d-H');
        
        return $this->cache->remember("stats:hourly:{$hour}", 3600, function () {
            return [
                'requests' => $this->countRequests(),
                'errors' => $this->countErrors(),
                'avg_response_time' => $this->avgResponseTime(),
            ];
        });
    }
    
    private function countUsers(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }
    
    private function countOrders(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM orders')->fetchColumn();
    }
    
    private function calculateRevenue(): float
    {
        return (float) $this->db->query('SELECT SUM(amount) FROM orders')->fetchColumn();
    }
}
```

## Multi-Level Caching

```php
class MultiLevelCache implements CacheStoreInterface
{
    public function __construct(
        private PerRequestCache $l1,    // Fastest - per request
        private CacheStoreInterface $l2  // Persistent - Redis/File
    ) {}
    
    public function get(string $key, mixed $default = null): mixed
    {
        // Try L1 first
        if ($this->l1->has($key)) {
            return $this->l1->get($key);
        }
        
        // Try L2
        $value = $this->l2->get($key);
        
        if ($value !== null) {
            // Populate L1 for subsequent calls
            $this->l1->put($key, $value);
        }
        
        return $value ?? $default;
    }
    
    public function set(
        string $key,
        mixed $value,
        int|\DateInterval|null $ttl = null
    ): bool {
        // Write to both levels
        $this->l1->put($key, $value);
        return $this->l2->set($key, $value, $ttl);
    }
    
    public function delete(string $key): bool
    {
        $this->l1->forget($key);
        return $this->l2->delete($key);
    }
    
    public function has(string $key): bool
    {
        return $this->l1->has($key) || $this->l2->has($key);
    }
    
    public function clear(): bool
    {
        $this->l1->flush();
        return $this->l2->clear();
    }
    
    public function remember(
        string $key,
        int|\DateInterval|null $ttl,
        callable $callback
    ): mixed {
        $marker = new \stdClass();
        $value = $this->get($key, $marker);
        
        if ($value !== $marker) {
            return $value;
        }
        
        $value = $callback();
        $this->set($key, $value, $ttl);
        
        return $value;
    }
    
    public function forever(string $key, mixed $value): bool
    {
        return $this->set($key, $value, null);
    }
}
```

## Cache Tags Pattern

```php
class TaggedCache
{
    public function __construct(
        private CacheStoreInterface $cache
    ) {}
    
    public function tags(array $tags): self
    {
        return new class($this->cache, $tags) {
            public function __construct(
                private CacheStoreInterface $cache,
                private array $tags
            ) {}
            
            public function set(string $key, mixed $value, ?int $ttl = null): bool
            {
                // Store the actual value
                $this->cache->set($key, $value, $ttl);
                
                // Track key in each tag
                foreach ($this->tags as $tag) {
                    $tagKey = "tag:{$tag}";
                    $keys = $this->cache->get($tagKey, []);
                    $keys[] = $key;
                    $this->cache->forever($tagKey, array_unique($keys));
                }
                
                return true;
            }
            
            public function flush(): bool
            {
                foreach ($this->tags as $tag) {
                    $tagKey = "tag:{$tag}";
                    $keys = $this->cache->get($tagKey, []);
                    
                    foreach ($keys as $key) {
                        $this->cache->delete($key);
                    }
                    
                    $this->cache->delete($tagKey);
                }
                
                return true;
            }
        };
    }
}

// Usage
$cache = new TaggedCache($store);

// Store with tags
$cache->tags(['users', 'admin'])->set('user:1', $user, 3600);
$cache->tags(['users'])->set('user:2', $user2, 3600);

// Flush all 'users' tagged items
$cache->tags(['users'])->flush();
```
