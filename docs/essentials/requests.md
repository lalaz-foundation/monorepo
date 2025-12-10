# Requests

The `Request` class provides a clean interface for accessing all HTTP request data including route parameters, query strings, form data, JSON bodies, headers, cookies, and uploaded files.

## Accessing the Request

Inject the `Request` object into your controller method:

```php
use Lalaz\Web\Http\Request;

public function store(Request $request): array
{
    $name = $request->input('name');
    return ['name' => $name];
}
```

## Request Method and Path

### Getting the HTTP Method

```php
$method = $request->method(); // "GET", "POST", "PUT", etc.
```

### Getting the Request Path

```php
// For URL: /users/123?page=2
$request->path();  // "/users/123"
$request->uri();   // "/users/123?page=2"
```

## Route Parameters

Route parameters are values extracted from the URL path defined in your route:

```php
// Route: /users/{id}/posts/{postId}
// URL:   /users/42/posts/7

$userId = $request->routeParam('id');       // "42"
$postId = $request->routeParam('postId');   // "7"

// With default value
$format = $request->routeParam('format', 'json');
```

## Query Parameters

Access query string parameters:

```php
// URL: /users?page=2&limit=10&sort=name

$page = $request->queryParam('page');           // "2"
$limit = $request->queryParam('limit', 25);     // "10" (or 25 if not present)
$sort = $request->queryParam('sort');           // "name"
$filter = $request->queryParam('filter', null); // null (not present)
```

## Request Input

### Getting Input Values

The `input()` method retrieves data from the request body:

```php
// From POST form data or JSON body
$name = $request->input('name');
$email = $request->input('email', 'default@example.com');
```

### Getting All Input

```php
// Get all request data (params + body merged)
$all = $request->all();
```

### Combined Parameters

The `param()` method searches route params, query params, and body:

```php
$value = $request->param('key');
$value = $request->param('key', 'default');
```

### Checking for Input

```php
if ($request->has('email')) {
    // The 'email' field exists
}
```

## JSON Request Body

### Accessing JSON Data

```php
// Get the entire JSON body as an array
$data = $request->json();

// Get a specific key from JSON body
$name = $request->json('name');
$email = $request->json('email', 'default@example.com');
```

### Checking if Request is JSON

```php
if ($request->isJson()) {
    $data = $request->json();
}
```

### Getting Raw Body

```php
$raw = $request->body();
```

## Headers

### Getting a Header

```php
// Header names are case-insensitive
$contentType = $request->header('Content-Type');
$auth = $request->header('Authorization', '');
```

### Getting All Headers

```php
$headers = $request->headers();
// ['Content-Type' => 'application/json', 'Accept' => '*/*', ...]
```

## Cookies

```php
// Get a cookie value
$sessionId = $request->cookie('session_id');

// Check if a cookie exists
if ($request->hasCookie('session_id')) {
    // Cookie exists
}
```

## File Uploads

```php
// Get an uploaded file
$file = $request->file('avatar');

if ($file) {
    $name = $file['name'];
    $tmpPath = $file['tmp_name'];
    $size = $file['size'];
    $type = $file['type'];
}
```

## Client Information

### IP Address

```php
// Gets IP from proxy headers (X-Forwarded-For, etc.) or REMOTE_ADDR
$ip = $request->ip();
```

### User Agent

```php
$userAgent = $request->userAgent();
```

### Checking for HTTPS

```php
if ($request->isSecure()) {
    // Request is over HTTPS
}
```

## Content Negotiation

### Check if Client Wants JSON

```php
if ($request->wantsJson()) {
    return json(['data' => $data]);
}

return $this->view('page', ['data' => $data]);
```

This returns `true` if the request has a JSON Content-Type or accepts JSON in the Accept header.

## Boolean Values

Parse boolean-like values from requests:

```php
// Interprets "1", "true", "yes", "on" as true
// Interprets "0", "false", "no", "off" as false
$active = $request->boolean('active', false);
$notify = $request->boolean('notify', true);
```

## Request Attributes

Store and retrieve custom attributes on the request object:

```php
// Set an attribute (e.g., in middleware)
$request->setAttribute('user', $user);

// Get an attribute
$user = $request->getAttribute('user');

// Check if attribute exists
if ($request->hasAttribute('user')) {
    // ...
}
```

## Complete Example

```php
<?php declare(strict_types=1);

namespace App\Controllers;

use Lalaz\Web\Http\Request;

class UserController
{
    public function index(Request $request): array
    {
        $page = (int) $request->queryParam('page', 1);
        $limit = (int) $request->queryParam('limit', 10);
        $sort = $request->queryParam('sort', 'created_at');

        return [
            'users' => [],
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'sort' => $sort,
            ],
        ];
    }

    public function show(Request $request): array
    {
        $id = (int) $request->routeParam('id');
        
        return ['user' => ['id' => $id]];
    }

    public function store(Request $request): mixed
    {
        if (!$request->isJson()) {
            return json_error('Content-Type must be application/json', 415);
        }

        $data = $request->json();
        
        $name = $data['name'] ?? null;
        $email = $data['email'] ?? null;

        if (!$name || !$email) {
            return json_error('Name and email are required', 422);
        }

        return json([
            'user' => [
                'id' => 1,
                'name' => $name,
                'email' => $email,
            ],
        ], 201);
    }

    public function update(Request $request): array
    {
        $id = (int) $request->routeParam('id');
        $data = $request->json();

        return [
            'user' => array_merge(['id' => $id], $data),
        ];
    }
}
```

## Method Summary

| Method | Description |
|--------|-------------|
| `method()` | Get HTTP method (GET, POST, etc.) |
| `path()` | Get request path without query string |
| `uri()` | Get full URI with query string |
| `routeParam($key, $default)` | Get route parameter |
| `queryParam($key, $default)` | Get query string parameter |
| `param($key, $default)` | Get from route, query, or body |
| `input($key, $default)` | Get from request body |
| `all()` | Get all input data merged |
| `json($key, $default)` | Get JSON body or specific key |
| `body()` | Get raw request body |
| `has($key)` | Check if input exists |
| `boolean($key, $default)` | Get boolean value |
| `header($name, $default)` | Get header value |
| `headers()` | Get all headers |
| `cookie($name)` | Get cookie value |
| `hasCookie($name)` | Check if cookie exists |
| `file($key)` | Get uploaded file |
| `ip()` | Get client IP address |
| `userAgent()` | Get user agent string |
| `isJson()` | Check if request is JSON |
| `wantsJson()` | Check if client wants JSON |
| `isSecure()` | Check if request is HTTPS |

## Next Steps

- [Responses](/essentials/responses) - Sending HTTP responses
- [Controllers](/essentials/controllers) - Controller patterns
- [Routing](/essentials/routing) - Route definitions
