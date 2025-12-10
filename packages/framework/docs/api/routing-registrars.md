---
id: framework-api-routing-registrars
title: "API — Routing Registrars"
short_description: "Overview of registrar components used by RoutingConfigurator: route files, attribute controllers and discovery."
tags: ["api","routing","registrar"]
version: "1.0"
type: "api"
---

# Routing Registrars

Short summary:
RoutingConfigurator uses small registrar classes to build routes for the router. These registrars isolate responsibilities: loading route files, scanning controller attributes, and discovering controllers in directories.

Common registrar types:
- RouteFileRegistrar — load route definitions from PHP files that return a registrar closure.
- ControllerAttributeRegistrar — registers routes declared with PHP 8 attributes on controller methods.
- ControllerDiscoveryRegistrar — auto-discovers controller classes by scanning given directories and registers them in the router.

Example (how RoutingConfigurator composes registrars):
```php
$rc->addRouteFiles(['routes/web.php']);
$rc->addControllers([MyController::class]);
$rc->addControllerDiscovery([['path' => 'app/Http/Controllers']]);
$rc->configure($router);
```

Notes:
- Registrars keep route building modular and enable caching in production to avoid repeated scans.
