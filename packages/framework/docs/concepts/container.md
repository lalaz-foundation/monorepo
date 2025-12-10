---
id: framework-concept-container
title: "Concepts — Container"
short_description: "Dependency Injection container with auto-wiring and scoped instances."
tags: ["concept","container","di"]
version: "1.0"
type: "concept"
---

# Container (Dependency Injection)

Short summary:
The Lalaz container resolves class dependencies using PHP reflection. It supports: bind, singleton, scoped, instance, aliases, and method binding for setter injection.

Key features:
- Auto-wiring by reading constructor type-hints and resolving from the container.
- Singleton vs scoped lifetimes.
- Method injection via `when(...)->bindMethod()` for setter-style injections.
- Tagging services and resolving grouped services via `tag()` / `tagged()`.

Common pitfalls:
- Circular dependencies will throw ContainerException — break cycles by using factories or setter injection.
- Scalar constructor parameters with no default will cause resolution failure.

Example (registering singleton):

```php
$container->singleton(MyService::class, function($c) {
    return new MyService($c->resolve(Dependency::class));
});

$service = $container->get(MyService::class);
```
