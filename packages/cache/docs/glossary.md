# Glossary

A reference guide to caching terminology used in Lalaz Cache.

## A

### APCu
Alternative PHP Cache (user). A PHP extension that provides shared memory caching. Data is stored in memory and shared between PHP processes on the same server.

### Array Store
An in-memory cache store that uses PHP arrays. Data is lost when the request ends. Useful for testing and development.

## C

### Cache
A temporary storage layer that keeps frequently accessed data in a fast-access location to reduce load on slower data sources (like databases).

### Cache Hit
When requested data is found in the cache. No need to regenerate or fetch from the original source.

### Cache Miss
When requested data is not found in the cache. The data must be fetched from the original source and optionally stored in cache.

### Cache Invalidation
The process of removing or updating cached data when the underlying data changes. One of the hardest problems in computer science.

### Cache Key
A unique identifier used to store and retrieve cached data. Example: `user:123:profile`.

### Cache Manager
The central class that creates and manages cache stores based on configuration.

### Cache Stampede
A situation where many requests simultaneously try to regenerate the same expired cache entry, potentially overwhelming the data source.

### Cache Store
A class that implements the caching operations (get, set, delete, etc.) for a specific storage backend.

### Cache Warming
The process of pre-populating the cache with data before it's needed, to avoid cache misses during peak traffic.

### Clear
Removing all data from the cache.

## D

### Default Value
The value returned by `get()` when the key doesn't exist in cache. Defaults to `null`.

### Delete
Removing a specific key from the cache.

### Driver
The storage backend type (array, file, redis, apcu, null).

## E

### Expiration
When a cached value's TTL has elapsed and it's no longer valid.

## F

### File Store
A cache store that persists data to files on disk. Data survives request end and server restarts.

### Forever
Storing a value with no expiration time. It remains in cache until manually deleted or cache is cleared.

## G

### Get
Retrieving a value from cache by its key.

## H

### Has
Checking if a key exists in cache without retrieving its value.

### Hit Rate
The percentage of requests that result in cache hits. Calculated as: `hits / (hits + misses) * 100`.

## K

### Key Prefix
A string prepended to all cache keys to namespace them. Helps avoid collisions when multiple applications share the same cache backend.

## M

### Miss Rate
The percentage of requests that result in cache misses. Calculated as: `misses / (hits + misses) * 100`.

## N

### Null Store
A no-op cache store that doesn't actually cache anything. Useful for disabling caching without changing code.

## P

### Per-Request Cache
A lightweight in-memory cache that only lives for the duration of a single HTTP request. Useful for avoiding redundant operations within a request.

### Persistence
Whether cached data survives beyond the current request or server restart.

### Prefix
See [Key Prefix](#key-prefix).

## R

### Redis
An in-memory data structure store, used as a database, cache, message broker, and queue. Supports distributed caching across multiple servers.

### Redis Store
A cache store that uses Redis server for storage. Supports distributed caching and persistence.

### Remember
A cache operation that returns cached value if exists, otherwise executes a callback, caches the result, and returns it.

## S

### Serialization
The process of converting PHP values (arrays, objects) to a string format for storage. Lalaz Cache handles this automatically.

### Set
Storing a value in cache with a specific key.

### Singleton
A design pattern where only one instance of a class exists. CacheManager maintains singleton instances of stores.

### Store
See [Cache Store](#cache-store).

## T

### TTL (Time-to-Live)
The duration (in seconds or as DateInterval) that a cached value remains valid before it expires.

## V

### Value
The data stored in cache, associated with a key. Can be any serializable PHP value.

## Common Cache Key Patterns

| Pattern | Example | Use Case |
|---------|---------|----------|
| `entity:id` | `user:123` | Single entity |
| `entity:id:relation` | `user:123:posts` | Related data |
| `list:filter` | `posts:recent` | Filtered lists |
| `count:entity` | `count:users` | Aggregate counts |
| `config:key` | `config:settings` | Configuration |
| `hash:value` | `query:abc123` | Query results |

## TTL Common Values

| Duration | Seconds | Use Case |
|----------|---------|----------|
| 1 minute | 60 | Rapidly changing data |
| 5 minutes | 300 | Frequently updated data |
| 15 minutes | 900 | Semi-static data |
| 1 hour | 3600 | Infrequently updated data |
| 1 day | 86400 | Static data |
| 1 week | 604800 | Very static data |
| Forever | null | Configuration, constants |

## See Also

- [Core Concepts](./concepts.md) - Detailed explanation of cache architecture
- [Basic Operations](./basic-operations.md) - How to use cache operations
- [Configuration](./configuration.md) - All configuration options
