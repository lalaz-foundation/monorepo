---
id: framework-api-httpapplication
title: "API — HttpApplication"
short_description: "High-level façade for booting and running HTTP apps."
tags: ["api","http","application"]
version: "1.0"
type: "api"
---

# HttpApplication

Short summary:
HttpApplication wires ServiceBootstrapper, RoutingConfigurator and HttpKernel. It can boot from a base path, register global middleware, register providers, register routes and run the application.

Common operations:
- HttpApplication::boot(string $basePath, ?array $routerConfig = null, bool $debug = false): self
- create(bool $debug = false): self
- handle(?Request $request = null, ?Response $response = null): Response
- run(): void
- registerProvider(string|ServiceProvider $provider): self
- middleware(callable|string|MiddlewareInterface $middleware): self

Example (programmatic):

```php
$app = HttpApplication::create(true);
$app->middleware(MyAuthMiddleware::class);
$app->get('/users', UserController::class . '::list');
$response = $app->handle();
```
