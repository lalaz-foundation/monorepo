---
id: framework-quick-start
title: "Quick Start — HTTP app"
short_description: "Start a minimal HTTP app using HttpApplication"
tags: ["getting-started","example"]
version: "1.0"
type: "guide"
---

# Quick Start — Minimal HTTP app

1) Create a minimal app file (for example: public/index.php):

```php
<?php
require __DIR__ . '/../vendor/autoload.php';

use Lalaz\Runtime\Http\HttpApplication;

// Boot the framework using your project base path (where config/ and public/ live)
$app = HttpApplication::boot(__DIR__ . '/..', null, true);

// Register a quick route
$app->get('/hello', function () {
    return "Hello from Lalaz!";
});

// Run the app (this will handle the request and emit the response)
$app->run();
```

2) Run a PHP built-in server in the project root:

```bash
php -S 127.0.0.1:8000 -t public
```

Open http://127.0.0.1:8000/hello → Should respond "Hello from Lalaz!"

Short summary: Boot → Register routes or providers → Run

FAQ:
Q: How do I enable route caching?
A: Use `$app->enableRouteCache('/path/to/cache', true)` before routes are warmed.
