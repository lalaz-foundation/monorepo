---
id: framework-api-provider-registry
title: "API — ProviderRegistry"
short_description: "Registry for registering and booting ServiceProvider classes."
tags: ["api","providers","registry"]
version: "1.0"
type: "api"
---

# ProviderRegistry

Short summary:
ProviderRegistry manages service provider registration and booting. Register providers during bootstrap and call boot() to run provider boot methods once all providers are registered.

Primary methods:
- register(string|ServiceProvider $provider): ServiceProvider — registers and calls register() on the provider. Accepts class name or instance.
- registerProviders(array $providers): void — register multiple providers.
- boot(): void — boots every registered provider (ensures each boots once).
- getProviders(): array — returns registered provider instances.

Example:
```php
$registry = new ProviderRegistry($container);
$registry->register(App\Providers\DatabaseProvider::class);
$registry->register(new App\Providers\AuthProvider($container));
$registry->boot(); // calls boot() on each provider
```

When to use:
- Use ProviderRegistry inside your application bootstrap to centralize service provider lifecycle.

Common pitfalls:
- Do not rely on provider boot order unless explicitly controlled by your application.
