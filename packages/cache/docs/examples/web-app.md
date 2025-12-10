# Web Application Example

A complete example of using Lalaz Cache in a web application.

## Setup

### Configuration

```php
// config/cache.php
return [
    'enabled' => true,
    'driver' => 'file',
    'prefix' => 'myapp_',
    'ttl' => 3600,
    'stores' => [
        'file' => [
            'driver' => 'file',
            'path' => storage_path('cache'),
        ],
        'array' => [
            'driver' => 'array',
        ],
    ],
];
```

### Service Provider

```php
// In your bootstrap
use Lalaz\Cache\CacheManager;

$container->singleton(CacheManager::class, function () {
    $config = require 'config/cache.php';
    return new CacheManager($config);
});
```

## Controller Example

```php
<?php

namespace App\Controllers;

use Lalaz\Cache\CacheManager;

class BlogController
{
    public function __construct(
        private CacheManager $cache
    ) {}

    public function index($request, $response)
    {
        $store = $this->cache->store();
        
        // Cache posts list for 10 minutes
        $posts = $store->remember('posts:latest', 600, function () {
            return Post::with('author')
                ->published()
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get();
        });

        // Cache sidebar data for 1 hour
        $sidebar = $store->remember('sidebar:data', 3600, function () {
            return [
                'categories' => Category::withCount('posts')->get(),
                'tags' => Tag::popular()->limit(20)->get(),
                'archive' => Post::archive()->get(),
            ];
        });

        return $response->view('blog.index', [
            'posts' => $posts,
            'sidebar' => $sidebar,
        ]);
    }

    public function show($request, $response, int $id)
    {
        $store = $this->cache->store();
        
        // Cache individual post for 1 hour
        $post = $store->remember("post:{$id}", 3600, function () use ($id) {
            return Post::with(['author', 'comments.author', 'tags'])
                ->findOrFail($id);
        });

        // Increment view count (don't cache this)
        $post->incrementViews();

        return $response->view('blog.show', ['post' => $post]);
    }

    public function store($request, $response)
    {
        $post = Post::create($request->validated());
        
        // Invalidate related caches
        $this->invalidatePostCaches();

        return $response->redirect("/posts/{$post->id}");
    }

    public function update($request, $response, int $id)
    {
        $post = Post::findOrFail($id);
        $post->update($request->validated());
        
        // Invalidate specific post cache
        $store = $this->cache->store();
        $store->delete("post:{$id}");
        
        // Invalidate list caches
        $this->invalidatePostCaches();

        return $response->redirect("/posts/{$id}");
    }

    public function destroy($request, $response, int $id)
    {
        $post = Post::findOrFail($id);
        $post->delete();
        
        $store = $this->cache->store();
        $store->delete("post:{$id}");
        $this->invalidatePostCaches();

        return $response->redirect('/posts');
    }

    private function invalidatePostCaches(): void
    {
        $store = $this->cache->store();
        $store->delete('posts:latest');
        $store->delete('sidebar:data');
    }
}
```

## User Profile with Cache

```php
<?php

namespace App\Controllers;

use Lalaz\Cache\CacheManager;
use Lalaz\Cache\PerRequestCache;

class UserController
{
    public function __construct(
        private CacheManager $cache,
        private PerRequestCache $requestCache
    ) {}

    public function profile($request, $response, int $id)
    {
        $store = $this->cache->store();
        
        // Cache user profile for 30 minutes
        $user = $store->remember("user:{$id}:profile", 1800, function () use ($id) {
            return User::with(['posts', 'followers', 'following'])
                ->findOrFail($id);
        });

        // Use request cache for current viewer
        $currentUser = $this->requestCache->remember('current_user', function () {
            return auth()->user();
        });

        // Check if following (cached per request)
        $isFollowing = $this->requestCache->remember("following:{$id}", function () use ($currentUser, $id) {
            return $currentUser?->isFollowing($id) ?? false;
        });

        return $response->view('user.profile', [
            'user' => $user,
            'isFollowing' => $isFollowing,
        ]);
    }

    public function updateProfile($request, $response)
    {
        $user = auth()->user();
        $user->update($request->validated());
        
        // Clear user caches
        $store = $this->cache->store();
        $store->delete("user:{$user->id}:profile");
        $store->delete("user:{$user->id}:settings");

        return $response->redirect('/profile');
    }
}
```

## Middleware for Cache Headers

```php
<?php

namespace App\Middleware;

class CacheMiddleware
{
    public function __invoke($request, $response, $next)
    {
        $response = $next($request, $response);
        
        // Add cache headers for static pages
        if ($request->getMethod() === 'GET') {
            $response = $response->withHeader('Cache-Control', 'public, max-age=3600');
        }
        
        return $response;
    }
}
```

## View Helper

```php
<?php

// helpers.php
function cache(): \Lalaz\Cache\Contracts\CacheStoreInterface
{
    static $store = null;
    
    if ($store === null) {
        $manager = app(\Lalaz\Cache\CacheManager::class);
        $store = $manager->store();
    }
    
    return $store;
}

// Usage in views
$settings = cache()->remember('site:settings', 3600, fn() => Settings::all());
```

## Complete Application Structure

```
app/
├── Controllers/
│   ├── BlogController.php
│   ├── UserController.php
│   └── ApiController.php
├── Middleware/
│   └── CacheMiddleware.php
├── Services/
│   └── CacheService.php
config/
│   └── cache.php
storage/
│   └── cache/
│       └── .gitkeep
```

### Cache Service

```php
<?php

namespace App\Services;

use Lalaz\Cache\CacheManager;
use Lalaz\Cache\PerRequestCache;

class CacheService
{
    private $store;
    private $requestCache;

    public function __construct(
        CacheManager $manager,
        PerRequestCache $requestCache
    ) {
        $this->store = $manager->store();
        $this->requestCache = $requestCache;
    }

    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        return $this->store->remember($key, $ttl, $callback);
    }

    public function rememberRequest(string $key, callable $callback): mixed
    {
        return $this->requestCache->remember($key, $callback);
    }

    public function forget(string $key): bool
    {
        return $this->store->delete($key);
    }

    public function forgetPattern(string $pattern): void
    {
        // Note: This is a simplified example
        // For production, consider using Redis SCAN or similar
        $keys = $this->getKeysMatchingPattern($pattern);
        foreach ($keys as $key) {
            $this->store->delete($key);
        }
    }

    public function flush(): bool
    {
        return $this->store->clear();
    }

    public function stats(): array
    {
        return $this->requestCache->stats();
    }
}
```

## Next Steps

- [REST API Example](./api.md) - API response caching
- [Database Queries Example](./database.md) - Query result caching
- [Configuration](../configuration.md) - All configuration options
