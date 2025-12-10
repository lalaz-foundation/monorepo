---
id: framework-guide-create-app
title: "Guide — Create a tiny application"
short_description: "Step-by-step: create a tiny Lalaz HTTP application and run it locally."
tags: ["guide","create-app"]
version: "1.0"
type: "guide"
---

# Create a tiny application

1) Project layout (recommended):

```
my-app/
  composer.json
  config/
    app.php
  public/
    index.php
  src/
  vendor/
```

2) public/index.php example:

```php
<?php
require __DIR__ . '/../vendor/autoload.php';
use Lalaz\Runtime\Http\HttpApplication;

$app = HttpApplication::boot(__DIR__ . '/..', null, true);

$app->get('/', function(){
    return 'Hello Lalaz!';
});

$app->run();
```

3) Start local server and open / → you should see the response text.

Test tips:
- Use `HttpApplication::create(true)` in tests and call `$app->handle($request)` to get a Response instance for assertions.
