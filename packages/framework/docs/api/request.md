---
id: framework-api-request
title: "API — Request"
short_description: "HTTP Request object used by the framework (factory produces instances)."
tags: ["api","http","request"]
version: "1.0"
type: "api"
---

# Request

Short summary:
Request objects represent an incoming HTTP request. Use the request factory to build Request instances from globals or test harnesses.

Key responsibilities:
- Parse HTTP method, headers, query parameters and body.
- Provide helpers to inspect path, headers, method, and parsed body.
- Typically created by RequestFactory implementations (SapiRequestFactory).

Common usage:
```php
$request = $app->requestFactory()->fromGlobals();
// in tests: create a crafted Request and pass to $app->handle($request)
```

When to use:
- Use Request in controllers and middleware to access incoming data and metadata.

Common pitfalls:
- Never rely on raw superglobals in application code — prefer Request helpers.
