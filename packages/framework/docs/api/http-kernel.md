---
id: framework-api-http-kernel
title: "API — HttpKernel"
short_description: "Core request dispatcher responsible for middleware and controller dispatch."
tags: ["api","http","kernel"]
version: "1.0"
type: "api"
---

# HttpKernel

Short summary:
HttpKernel is the low-level component that handles request dispatching, middleware pipeline execution and controller invocation. HttpApplication uses HttpKernel internally to handle requests.

Key methods (conceptual):
- handle(Request $request, Response $response, array $middlewares): Response — dispatch the HTTP request through middleware and route handling.

Example:
```php
$kernel = new HttpKernel($container, $router, $exceptionHandler);
$response = $kernel->handle($request, $response, $middlewares);
```

Notes:
- HttpKernel is usually not used directly; prefer `HttpApplication` for simple apps.
