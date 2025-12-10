---
id: framework-api-response
title: "API — Response"
short_description: "HTTP Response object created by ResponseFactory and returned from handlers."
tags: ["api","http","response"]
version: "1.0"
type: "api"
---

# Response

Short summary:
Response objects represent the outgoing HTTP response. They include status, headers, and a body that will be emitted by the ResponseEmitter.

Responsibilities:
- Hold status code, headers, and body content.
- Provide helper methods to set status, headers and JSON/text bodies.
- Created by ResponseFactory implementations (SimpleResponseFactory) typically in the ServiceBootstrapper.

Typical usage:
```php
$response = $app->responseFactory()->create($request);
$response->setStatus(200);
$response->setBody('Hello');
```

Notes:
- The ResponseEmitter is responsible for converting a Response object into HTTP headers and printed body.
