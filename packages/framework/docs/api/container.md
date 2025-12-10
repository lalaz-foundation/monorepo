---
id: framework-api-container
title: "API — Container"
short_description: "Dependency injection Container with auto-wiring, singletons and scoped instances."
tags: ["api","container"]
version: "1.0"
type: "api"
---

# Container (DI)

Short summary:
The Container auto-wires classes by reading type-hints and supports:
- bind() — transient bindings
- singleton() — single instance
- scoped() — one instance per request/scope
- instance() — register an already-created instance
- resolve()/get() — obtain instances

Methods (high-level):
- bind(string $abstract, mixed $concrete = null): void
- singleton(string $abstract, mixed $concrete = null): void
- scoped(string $abstract, mixed $concrete = null): void
- instance(string $abstract, mixed $instance): void
- alias(string $abstract, string $alias): void
- get(string $id): mixed
- resolve(string $abstract, array $parameters = []): mixed
- call(callable $callback, array $parameters = []): mixed

Example (resolve with method injection):

```php
$container->when(MyController::class)
    ->bindMethod('setLogger', MyLogger::class);

$controller = $container->resolve(MyController::class);
```
