---
id: framework-api-http-kernel-deep
title: "API — HttpKernel (deep dive)"
short_description: "Detailed behavior of HttpKernel: middleware, dispatch lifecycle and error handling."
tags: ["api","http","kernel","deep-dive"]
version: "1.0"
type: "api"
---

# HttpKernel — deep dive

Short summary:
HttpKernel dispatches HTTP requests through the middleware pipeline and resolves route handlers. It handles error propagation, response normalization and final return to the caller.

Responsibilities (in order):
- Accept Request and Response objects and a middleware stack.
- Execute middleware sequentially (first to last), each middleware may call the next to continue processing.
- Resolve the matched route and invoke the route handler (closure or controller method).
- Catch exceptions and pass them to the ExceptionHandler (configured via ServiceBootstrapper).
- Return a Response object to the caller.

Middleware & pipeline:
- Middleware runs in-order and may short-circuit the pipeline (returning a Response early).
- Scoped container middleware is typically prepended so per-request scoped instances are created before route handling.

Typical invocation:
```php
$kernel = new HttpKernel($container, $router, $exceptionHandler);
$response = $kernel->handle($request, $response, $middlewares);
```

Notes and tips:
- Exceptions are logged by the ExceptionHandler; during development (debug mode) the application prints stack traces.
- Keep middleware responsibilities small (authentication, logging, rate limit, etc.).

Common pitfalls:
- Mutating request/response bodies in middleware without returning them can cause unexpected results.
- Rely on Request immutability (create new instances for modified values) when possible.
