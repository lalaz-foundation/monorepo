---
id: framework-api-request-factory
title: "API — RequestFactory"
short_description: "Factory interface and implementations for creating Request objects."
tags: ["api","http","factory"]
version: "1.0"
type: "api"
---

# RequestFactory

Short summary:
RequestFactory implementations build Request objects from globals or programmatic data. Common implementation: SapiRequestFactory.

Key methods:
- fromGlobals(): Request — build a Request from PHP global state.

Example:
```php
$factory = new SapiRequestFactory();
$request = $factory->fromGlobals();
```
