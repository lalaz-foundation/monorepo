# Database Query Caching Examples

Strategies for caching database query results.

## Basic Query Caching

```php
<?php

use Lalaz\Cache\CacheManager;

class UserRepository
{
    public function __construct(
        private CacheManager $cache
    ) {}

    public function find(int $id): ?User
    {
        $store = $this->cache->store();
        
        return $store->remember("user:{$id}", 3600, function () use ($id) {
            return User::find($id);
        });
    }

    public function findByEmail(string $email): ?User
    {
        $store = $this->cache->store();
        $key = 'user:email:' . md5($email);
        
        return $store->remember($key, 3600, function () use ($email) {
            return User::where('email', $email)->first();
        });
    }
}
```

## Collection Caching

```php
<?php

class PostRepository
{
    public function __construct(
        private CacheManager $cache
    ) {}

    public function getLatest(int $limit = 10): Collection
    {
        $store = $this->cache->store();
        
        return $store->remember("posts:latest:{$limit}", 600, function () use ($limit) {
            return Post::with('author')
                ->published()
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();
        });
    }

    public function getByCategory(int $categoryId): Collection
    {
        $store = $this->cache->store();
        
        return $store->remember("posts:category:{$categoryId}", 600, function () use ($categoryId) {
            return Post::where('category_id', $categoryId)
                ->published()
                ->get();
        });
    }

    public function getPopular(): Collection
    {
        $store = $this->cache->store();
        
        return $store->remember('posts:popular', 3600, function () {
            return Post::orderBy('views', 'desc')
                ->limit(10)
                ->get();
        });
    }
}
```

## Paginated Results

```php
<?php

class PaginatedRepository
{
    public function __construct(
        private CacheManager $cache
    ) {}

    public function getPaginated(int $page = 1, int $perPage = 20): LengthAwarePaginator
    {
        $store = $this->cache->store();
        
        return $store->remember("items:page:{$page}:per:{$perPage}", 300, function () use ($page, $perPage) {
            return Item::paginate($perPage, ['*'], 'page', $page);
        });
    }

    public function search(string $query, int $page = 1): LengthAwarePaginator
    {
        $store = $this->cache->store();
        $key = "search:" . md5($query) . ":page:{$page}";
        
        return $store->remember($key, 300, function () use ($query, $page) {
            return Item::where('name', 'like', "%{$query}%")
                ->orWhere('description', 'like', "%{$query}%")
                ->paginate(20, ['*'], 'page', $page);
        });
    }
}
```

## Eager Loading with Cache

```php
<?php

class OrderRepository
{
    public function __construct(
        private CacheManager $cache
    ) {}

    public function findWithRelations(int $id): ?Order
    {
        $store = $this->cache->store();
        
        return $store->remember("order:{$id}:full", 1800, function () use ($id) {
            return Order::with([
                'customer',
                'items.product',
                'payments',
                'shipments',
            ])->find($id);
        });
    }

    public function getUserOrders(int $userId): Collection
    {
        $store = $this->cache->store();
        
        return $store->remember("user:{$userId}:orders", 1800, function () use ($userId) {
            return Order::where('user_id', $userId)
                ->with(['items.product'])
                ->orderBy('created_at', 'desc')
                ->get();
        });
    }
}
```

## Aggregate Queries

```php
<?php

class StatsRepository
{
    public function __construct(
        private CacheManager $cache
    ) {}

    public function getUserCount(): int
    {
        $store = $this->cache->store();
        
        return $store->remember('stats:users:count', 3600, function () {
            return User::count();
        });
    }

    public function getOrderStats(): array
    {
        $store = $this->cache->store();
        
        return $store->remember('stats:orders', 1800, function () {
            return [
                'total_orders' => Order::count(),
                'total_revenue' => Order::sum('total'),
                'average_order' => Order::avg('total'),
                'orders_today' => Order::whereDate('created_at', today())->count(),
            ];
        });
    }

    public function getTopSellers(int $limit = 10): Collection
    {
        $store = $this->cache->store();
        
        return $store->remember("stats:top_sellers:{$limit}", 3600, function () use ($limit) {
            return Product::withCount('orderItems')
                ->orderBy('order_items_count', 'desc')
                ->limit($limit)
                ->get();
        });
    }
}
```

## Cache Invalidation Strategies

### Event-Based Invalidation

```php
<?php

class Post extends Model
{
    protected static function booted()
    {
        static::saved(function (Post $post) {
            $cache = app(CacheManager::class)->store();
            
            // Clear specific post cache
            $cache->delete("post:{$post->id}");
            
            // Clear list caches
            $cache->delete('posts:latest:10');
            $cache->delete("posts:category:{$post->category_id}");
        });

        static::deleted(function (Post $post) {
            $cache = app(CacheManager::class)->store();
            
            $cache->delete("post:{$post->id}");
            $cache->delete('posts:latest:10');
            $cache->delete("posts:category:{$post->category_id}");
        });
    }
}
```

### Repository-Based Invalidation

```php
<?php

class CachedUserRepository
{
    public function __construct(
        private CacheManager $cache
    ) {}

    public function find(int $id): ?User
    {
        $store = $this->cache->store();
        return $store->remember("user:{$id}", 3600, fn() => User::find($id));
    }

    public function update(int $id, array $data): User
    {
        $user = User::findOrFail($id);
        $user->update($data);
        
        // Invalidate all related caches
        $this->invalidateUser($id);
        
        return $user;
    }

    public function delete(int $id): void
    {
        User::destroy($id);
        $this->invalidateUser($id);
    }

    private function invalidateUser(int $id): void
    {
        $store = $this->cache->store();
        
        $store->delete("user:{$id}");
        $store->delete("user:{$id}:profile");
        $store->delete("user:{$id}:posts");
        $store->delete("user:{$id}:orders");
    }
}
```

## Query Cache Service

```php
<?php

class QueryCacheService
{
    public function __construct(
        private CacheManager $cache
    ) {}

    /**
     * Cache a query result with automatic key generation.
     */
    public function query(
        string $model,
        callable $query,
        int $ttl = 3600,
        ?string $suffix = null
    ): mixed {
        $store = $this->cache->store();
        
        // Generate cache key from query closure
        $key = $this->generateKey($model, $query, $suffix);
        
        return $store->remember($key, $ttl, $query);
    }

    /**
     * Invalidate all cache entries for a model.
     */
    public function invalidateModel(string $model): void
    {
        $store = $this->cache->store();
        $tagKey = "cache_tag:model:{$model}";
        
        $keys = $store->get($tagKey, []);
        foreach ($keys as $key) {
            $store->delete($key);
        }
        
        $store->delete($tagKey);
    }

    private function generateKey(string $model, callable $query, ?string $suffix): string
    {
        $hash = md5(serialize($query));
        $key = "query:{$model}:{$hash}";
        
        if ($suffix) {
            $key .= ":{$suffix}";
        }
        
        // Track for invalidation
        $store = $this->cache->store();
        $tagKey = "cache_tag:model:{$model}";
        $keys = $store->get($tagKey, []);
        $keys[] = $key;
        $store->forever($tagKey, array_unique($keys));
        
        return $key;
    }
}

// Usage
$cacheService = new QueryCacheService($cache);

$users = $cacheService->query(
    'User',
    fn() => User::where('active', true)->get(),
    3600
);

// After user update
$cacheService->invalidateModel('User');
```

## Complex Query Caching

```php
<?php

class ReportRepository
{
    public function __construct(
        private CacheManager $cache
    ) {}

    public function getSalesReport(Carbon $startDate, Carbon $endDate): array
    {
        $store = $this->cache->store();
        
        $key = sprintf(
            'report:sales:%s:%s',
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );
        
        return $store->remember($key, 7200, function () use ($startDate, $endDate) {
            return [
                'total_sales' => Order::whereBetween('created_at', [$startDate, $endDate])
                    ->sum('total'),
                    
                'order_count' => Order::whereBetween('created_at', [$startDate, $endDate])
                    ->count(),
                    
                'average_order' => Order::whereBetween('created_at', [$startDate, $endDate])
                    ->avg('total'),
                    
                'top_products' => $this->getTopProducts($startDate, $endDate),
                
                'sales_by_day' => $this->getSalesByDay($startDate, $endDate),
            ];
        });
    }

    private function getTopProducts(Carbon $startDate, Carbon $endDate): Collection
    {
        return OrderItem::whereBetween('created_at', [$startDate, $endDate])
            ->select('product_id', DB::raw('SUM(quantity) as total_sold'))
            ->groupBy('product_id')
            ->orderBy('total_sold', 'desc')
            ->limit(10)
            ->with('product')
            ->get();
    }

    private function getSalesByDay(Carbon $startDate, Carbon $endDate): Collection
    {
        return Order::whereBetween('created_at', [$startDate, $endDate])
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(total) as total'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }
}
```

## Next Steps

- [Basic Usage Example](./basic.md) - Simple caching examples
- [Web Application Example](./web-app.md) - Full-stack example
- [API Reference](../api-reference.md) - Complete method reference
