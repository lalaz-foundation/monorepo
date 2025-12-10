---
id: framework-api-response-factory
title: "API — ResponseFactory"
short_description: "Factory interface and implementations for creating Response objects."
tags: ["api","http","factory"]
version: "1.0"
type: "api"
---

# ResponseFactory

Short summary:
ResponseFactory implementations create Response objects pre-populated for handling requests. The default SimpleResponseFactory produces minimal Response instances ready to be filled by handlers.

Key methods:
- create(Request $request): Response — create a fresh Response instance for the given request.

Example:
```php
$factory = new SimpleResponseFactory();
$response = $factory->create($request);
```
