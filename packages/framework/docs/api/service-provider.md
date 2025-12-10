---
id: framework-api-service-provider
title: "API — ServiceProvider"
short_description: "Base class for registering container bindings and boot-time actions."
tags: ["api","providers","service-provider"]
version: "1.0"
type: "api"
---

# ServiceProvider

Short summary:
ServiceProvider is an abstract base used to register bindings with the container and optionally perform boot-time actions.

Key methods:
- register(): void (abstract) — bind things into the container.
- boot(): void — optional; run after all providers are registered.
- bind()/singleton()/scoped()/instance()/alias() — convenience helpers that proxy to the container.
- commands(...$commands): void — register console commands to the Registry (handles late-binding if the Registry isn't available yet).

Example:
```php
class CacheProvider extends ServiceProvider {
    public function register(): void {
        $this->singleton(Cache::class, fn($c) => new Cache(config('cache')));
    }

    public function boot(): void {
        // optional boot logic
    }
}
```

Notes:
- Use `pendingCommands` / `bootCommands()` when registering commands before the Console Registry is ready.
