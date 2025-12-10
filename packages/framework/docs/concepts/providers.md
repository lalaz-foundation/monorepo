---
id: framework-concept-providers
title: "Concepts — Service Providers"
short_description: "How to register and boot ServiceProvider classes."
tags: ["concept","providers"]
version: "1.0"
type: "concept"
---

# Service Providers

Short summary:
Service providers register bindings and optional runtime logic. Use ProviderRegistry to register and boot providers. Providers separate configuration from runtime code and keep the container configuration centralized.

Minimal example:

```php
class SampleProvider extends ServiceProvider {
    public function register(): void {
        $this->singleton(MyService::class, function($c) {
            return new MyService($c->resolve(SomeDependency::class));
        });
    }

    public function boot(): void {
        // Post-registration logic. e.g. register events or commands
    }
}

$registry = new ProviderRegistry($container);
$registry->register(SampleProvider::class);
$registry->boot();
```
