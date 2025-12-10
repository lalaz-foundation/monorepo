# REST API Example

Caching strategies for REST API responses.

## Basic API Caching

```php
<?php

namespace App\Controllers\Api;

use Lalaz\Cache\CacheManager;

class PostsController
{
    public function __construct(
        private CacheManager $cache
    ) {}

    public function index($request, $response)
    {
        $store = $this->cache->store();
        $page = $request->getQueryParam('page', 1);
        
        // Cache paginated results
        $posts = $store->remember("api:posts:page:{$page}", 300, function () use ($page) {
            return Post::with('author')
                ->published()
                ->paginate(20, $page);
        });

        return $response->json([
            'data' => $posts->items(),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'total_pages' => $posts->lastPage(),
                'total' => $posts->total(),
            ],
        ]);
    }

    public function show($request, $response, int $id)
    {
        $store = $this->cache->store();
        
        $post = $store->remember("api:post:{$id}", 600, function () use ($id) {
            return Post::with(['author', 'tags', 'comments'])
                ->findOrFail($id);
        });

        return $response->json(['data' => $post]);
    }
}
```

## ETags and Conditional Requests

```php
<?php

namespace App\Controllers\Api;

use Lalaz\Cache\CacheManager;

class ResourceController
{
    public function __construct(
        private CacheManager $cache
    ) {}

    public function show($request, $response, int $id)
    {
        $store = $this->cache->store();
        
        // Get cached data with ETag
        $cacheKey = "api:resource:{$id}";
        $etagKey = "api:resource:{$id}:etag";
        
        $data = $store->remember($cacheKey, 3600, function () use ($id) {
            return Resource::findOrFail($id);
        });

        // Generate or retrieve ETag
        $etag = $store->remember($etagKey, 3600, function () use ($data) {
            return md5(json_encode($data));
        });

        // Check If-None-Match header
        $clientEtag = $request->getHeaderLine('If-None-Match');
        if ($clientEtag === $etag) {
            return $response->withStatus(304); // Not Modified
        }

        return $response
            ->withHeader('ETag', $etag)
            ->withHeader('Cache-Control', 'private, max-age=3600')
            ->json(['data' => $data]);
    }

    public function update($request, $response, int $id)
    {
        $resource = Resource::findOrFail($id);
        $resource->update($request->validated());
        
        // Invalidate cache
        $store = $this->cache->store();
        $store->delete("api:resource:{$id}");
        $store->delete("api:resource:{$id}:etag");

        return $response->json(['data' => $resource]);
    }
}
```

## Rate Limiting with Cache

```php
<?php

namespace App\Middleware;

use Lalaz\Cache\CacheManager;

class RateLimitMiddleware
{
    public function __construct(
        private CacheManager $cache,
        private int $maxRequests = 60,
        private int $windowSeconds = 60
    ) {}

    public function __invoke($request, $response, $next)
    {
        $store = $this->cache->store();
        $identifier = $this->getIdentifier($request);
        $key = "rate_limit:{$identifier}";
        
        $attempts = (int) $store->get($key, 0);
        
        if ($attempts >= $this->maxRequests) {
            return $response
                ->withStatus(429)
                ->withHeader('Retry-After', $this->windowSeconds)
                ->json([
                    'error' => 'Too many requests',
                    'retry_after' => $this->windowSeconds,
                ]);
        }
        
        // Increment counter
        $store->set($key, $attempts + 1, $this->windowSeconds);
        
        $response = $next($request, $response);
        
        // Add rate limit headers
        return $response
            ->withHeader('X-RateLimit-Limit', $this->maxRequests)
            ->withHeader('X-RateLimit-Remaining', $this->maxRequests - $attempts - 1);
    }

    private function getIdentifier($request): string
    {
        // Use API key or IP address
        $apiKey = $request->getHeaderLine('X-API-Key');
        return $apiKey ?: $request->getServerParam('REMOTE_ADDR');
    }
}
```

## External API Caching

```php
<?php

namespace App\Services;

use Lalaz\Cache\CacheManager;

class ExternalApiService
{
    public function __construct(
        private CacheManager $cache
    ) {}

    public function getWeather(string $city): array
    {
        $store = $this->cache->store();
        
        // Cache weather data for 30 minutes
        return $store->remember("weather:{$city}", 1800, function () use ($city) {
            $response = $this->httpClient->get("https://api.weather.com/v1/{$city}");
            
            if (!$response->ok()) {
                throw new ApiException('Weather API error');
            }
            
            return $response->json();
        });
    }

    public function getExchangeRates(): array
    {
        $store = $this->cache->store();
        
        // Cache exchange rates for 1 hour
        return $store->remember('exchange:rates', 3600, function () {
            $response = $this->httpClient->get('https://api.exchange.com/rates');
            return $response->json();
        });
    }
}
```

## API Response Wrapper

```php
<?php

namespace App\Support;

use Lalaz\Cache\CacheManager;

class ApiCache
{
    public function __construct(
        private CacheManager $cache
    ) {}

    public function cached(
        string $key,
        int $ttl,
        callable $callback,
        array $tags = []
    ): array {
        $store = $this->cache->store();
        
        $data = $store->remember($key, $ttl, $callback);
        
        // Track tags for invalidation
        foreach ($tags as $tag) {
            $tagKey = "cache_tag:{$tag}";
            $keys = $store->get($tagKey, []);
            $keys[] = $key;
            $store->forever($tagKey, array_unique($keys));
        }
        
        return $data;
    }

    public function invalidateTag(string $tag): void
    {
        $store = $this->cache->store();
        $tagKey = "cache_tag:{$tag}";
        
        $keys = $store->get($tagKey, []);
        foreach ($keys as $key) {
            $store->delete($key);
        }
        
        $store->delete($tagKey);
    }
}

// Usage
$apiCache = new ApiCache($cache);

$posts = $apiCache->cached(
    'api:posts:latest',
    300,
    fn() => Post::latest()->limit(10)->get(),
    ['posts', 'homepage']
);

// Later, invalidate all 'posts' cached data
$apiCache->invalidateTag('posts');
```

## GraphQL Caching

```php
<?php

namespace App\GraphQL;

use Lalaz\Cache\CacheManager;

class CachedResolver
{
    public function __construct(
        private CacheManager $cache
    ) {}

    public function resolveUser($root, array $args): ?User
    {
        $store = $this->cache->store();
        $id = $args['id'];
        
        return $store->remember("graphql:user:{$id}", 600, function () use ($id) {
            return User::find($id);
        });
    }

    public function resolvePosts($root, array $args): array
    {
        $store = $this->cache->store();
        
        $cacheKey = $this->buildCacheKey('graphql:posts', $args);
        
        return $store->remember($cacheKey, 300, function () use ($args) {
            $query = Post::query();
            
            if (isset($args['category'])) {
                $query->where('category_id', $args['category']);
            }
            
            if (isset($args['limit'])) {
                $query->limit($args['limit']);
            }
            
            return $query->get();
        });
    }

    private function buildCacheKey(string $prefix, array $args): string
    {
        ksort($args);
        return $prefix . ':' . md5(json_encode($args));
    }
}
```

## API Versioning with Cache

```php
<?php

namespace App\Controllers\Api;

class VersionedApiController
{
    public function __construct(
        private CacheManager $cache
    ) {}

    public function index($request, $response)
    {
        $store = $this->cache->store();
        $version = $request->getAttribute('api_version', 'v1');
        
        // Cache per API version
        $data = $store->remember("api:{$version}:posts", 300, function () use ($version) {
            return match ($version) {
                'v1' => $this->getPostsV1(),
                'v2' => $this->getPostsV2(),
                default => throw new \Exception('Unknown API version'),
            };
        });

        return $response->json($data);
    }

    private function getPostsV1(): array
    {
        return Post::all()->toArray();
    }

    private function getPostsV2(): array
    {
        return Post::with('author', 'tags')->get()->toArray();
    }
}
```

## Next Steps

- [Database Queries Example](./database.md) - Query result caching
- [Basic Usage Example](./basic.md) - Simple caching examples
- [Configuration](../configuration.md) - All configuration options
