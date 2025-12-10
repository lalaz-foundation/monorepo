# Lalaz Cache Documentation

Welcome to the Lalaz Cache documentation. This guide will help you understand and implement caching in your Lalaz applications.

## What is Lalaz Cache?

Lalaz Cache is a comprehensive caching package that provides:

- **Multiple Storage Backends**: Array, File, Redis, APCu, and Null stores
- **Unified API**: Same interface across all cache stores
- **Remember Pattern**: Cache expensive computations automatically
- **Per-Request Cache**: Lightweight in-memory cache with statistics

## Table of Contents

### Getting Started
- [Quick Start](./quick-start.md) - Get caching working in 5 minutes ⚡
- [Installation](./installation.md) - How to install and configure the package
- [Core Concepts](./concepts.md) - Understanding stores, drivers, and managers
- [Glossary](./glossary.md) - Caching terminology explained

### Cache Stores
- [Stores Overview](./stores/index.md) - Introduction to cache stores
- [Array Store](./stores/array.md) - In-memory caching for development/testing
- [File Store](./stores/file.md) - File-based persistent caching
- [Redis Store](./stores/redis.md) - Distributed caching with Redis
- [APCu Store](./stores/apcu.md) - Shared memory caching
- [Null Store](./stores/null.md) - No-op store for disabling cache

### Features
- [Basic Operations](./basic-operations.md) - Core get, set, delete operations
- [Remember Pattern](./remember-pattern.md) - Caching computed values
- [Per-Request Cache](./per-request-cache.md) - Request-scoped caching
- [Configuration](./configuration.md) - Configuring cache options

### Testing
- [Testing Guide](./testing.md) - How to run and write tests

### Examples
- [Basic Usage](./examples/basic.md) - Simple caching examples
- [Web Application](./examples/web-app.md) - Full-stack example
- [REST API](./examples/api.md) - API response caching
- [Database Queries](./examples/database.md) - Query result caching

### Reference
- [API Reference](./api-reference.md) - Complete class and method reference
- [Troubleshooting](./troubleshooting.md) - Common issues and solutions

## Quick Example

Here's a simple example to get you started:

```php
<?php

use Lalaz\Cache\CacheManager;

// 1. Create cache manager
$cache = new CacheManager([
    'driver' => 'file',
    'prefix' => 'myapp_',
    'stores' => [
        'file' => ['path' => '/var/cache/myapp'],
    ],
]);

// 2. Get the default store
$store = $cache->store();

// 3. Cache a value for 1 hour
$store->set('user:123', $userData, 3600);

// 4. Retrieve cached value
$user = $store->get('user:123');

// 5. Use remember pattern for computed values
$user = $store->remember('user:123', 3600, function () {
    return User::find(123);
});
```

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                      Your Application                        │
├─────────────────────────────────────────────────────────────┤
│                      CacheManager                            │
│  ┌─────────────────────────────────────────────────────┐    │
│  │  Manages stores, configuration, and driver creation │    │
│  └───────────────────────┬─────────────────────────────┘    │
│                          │                                   │
│         ┌────────────────┼────────────────┐                 │
│         ▼                ▼                ▼                 │
│  ┌────────────┐   ┌────────────┐   ┌────────────┐          │
│  │   Array    │   │    File    │   │   Redis    │          │
│  │   Store    │   │   Store    │   │   Store    │          │
│  └────────────┘   └────────────┘   └────────────┘          │
│         │                │                │                  │
│         └────────────────┼────────────────┘                  │
│                          ▼                                    │
│              ┌─────────────────────┐                        │
│              │ CacheStoreInterface │                        │
│              │ get, set, has,      │                        │
│              │ delete, clear,      │                        │
│              │ remember, forever   │                        │
│              └─────────────────────┘                        │
├─────────────────────────────────────────────────────────────┤
│                    PerRequestCache                           │
│  ┌─────────────────────────────────────────────────────┐    │
│  │  Lightweight in-memory cache with hit/miss stats    │    │
│  └─────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────┘
```

## Key Concepts at a Glance

| Concept | Description | Example |
|---------|-------------|---------|
| **Store** | Handles how data is cached | FileStore saves to disk, RedisStore uses Redis server |
| **Manager** | Creates and manages stores | CacheManager provides configured stores |
| **TTL** | Time-to-live for cached data | `set('key', 'value', 3600)` caches for 1 hour |
| **Remember** | Cache computed values | Executes callback only on cache miss |

## Available Stores

| Store | Use Case | Persistence | Requirements |
|-------|----------|-------------|--------------|
| `array` | Testing, single request | No | None |
| `file` | Simple deployments | Yes | Writable directory |
| `redis` | Production, distributed | Yes | Redis server |
| `apcu` | Single server, shared memory | Yes* | APCu extension |
| `null` | Disabled caching | No | None |

\* APCu persists across requests but not server restarts.

## Next Steps

1. **New to Lalaz Cache?** Start with the [Quick Start](./quick-start.md) guide
2. Already familiar? Jump to [Installation](./installation.md) for setup details
3. Read [Core Concepts](./concepts.md) to understand the architecture
4. Choose your storage strategy in [Stores](./stores/index.md)
5. Learn about [Basic Operations](./basic-operations.md)
6. Check out [Examples](./examples/basic.md) for complete implementations
7. Use the [Glossary](./glossary.md) as a reference for terminology
