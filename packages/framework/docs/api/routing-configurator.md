---
id: framework-api-routing-configurator
title: "API — RoutingConfigurator"
short_description: "Manages route files, controller discovery and route caching."
tags: ["api","routing","route-cache"]
version: "1.0"
type: "api"
---

# RoutingConfigurator

Short summary:
RoutingConfigurator manages registration of route files, controllers (attribute-based), controller discovery, and optional route caching.

Key methods:
- addRouteFiles(array $files): void — add route file paths.
- addControllers(array $controllers): void — register controller classes.
- addControllerDiscovery(array $discovery): void — register discovery paths.
- enableCache(string $file, bool $autoWarm = false): void — enable route caching with optional auto-warm.
- configure(RouterInterface $router): void — idempotent route configuration method.

Example:
```php
$rc = new RoutingConfigurator('/path/to/project');
$rc->addRouteFiles(['routes/api.php']);
$rc->addControllerDiscovery([['path' => 'src/Controllers']]);
$rc->configure($router);
```
