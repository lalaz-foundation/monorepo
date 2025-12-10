---
id: framework-api-service-bootstrapper
title: "API — ServiceBootstrapper"
short_description: "Internal bootstrapping of core services into the container."
tags: ["api","bootstrapper","runtime"]
version: "1.0"
type: "api"
---

# ServiceBootstrapper

Short summary:
ServiceBootstrapper sets up core services (container, router, exception handler, request/response factories, logging) and registers optional providers.

Key methods:
- bootstrap(HttpApplication $app): void — register core bindings and providers into the container.
- container(): ContainerInterface — returns the DI container instance.
- router(): RouterInterface — returns the router instance.
- providers(): ProviderRegistry — returns the provider registry.

Example:
```php
$bootstrapper = new ServiceBootstrapper();
$bootstrapper->bootstrap($app);
```

Notes:
- This class is an internal helper used by HttpApplication — you typically interact with HttpApplication instead.
