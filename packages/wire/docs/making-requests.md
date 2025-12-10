# Making Requests

Complete guide to making HTTP requests with Wire.

## HTTP Methods

Wire supports all common HTTP methods:

### GET

```php
$response = $client->get('/users');
$response = $client->get('/users/123');
```

### POST

```php
$response = $client->post('/users', [
    'body' => json_encode(['name' => 'John']),
]);
```

### PUT

```php
$response = $client->put('/users/123', [
    'body' => json_encode(['name' => 'Jane']),
]);
```

### PATCH

```php
$response = $client->patch('/users/123', [
    'body' => json_encode(['name' => 'Updated']),
]);
```

### DELETE

```php
$response = $client->delete('/users/123');
```

### Generic Request

Use `request()` for any method:

```php
$response = $client->request('OPTIONS', '/resource');
```

## Request Options

All methods accept an options array:

```php
$response = $client->get('/endpoint', [
    'headers' => [...],
    'query' => [...],
    'body' => '...',
    'timeout' => 30,
    'connect_timeout' => 10,
    'skip_ssl' => false,
]);
```

### Headers

```php
$response = $client->get('/data', [
    'headers' => [
        'Authorization' => 'Bearer token',
        'Accept' => 'application/json',
        'X-Custom-Header' => 'value',
    ],
]);
```

Headers merge with base headers (request headers take precedence):

```php
$client = HttpClientBuilder::create()
    ->baseHeaders(['Accept' => 'text/plain'])
    ->build();

// Accept will be 'application/json' (request overrides)
$client->get('/data', [
    'headers' => ['Accept' => 'application/json'],
]);
```

### Query Parameters

```php
$response = $client->get('/search', [
    'query' => [
        'q' => 'search term',
        'page' => 1,
        'limit' => 20,
        'filters' => ['active', 'verified'],
    ],
]);
```

The query array is URL-encoded automatically.

### Body

Send a string body:

```php
// JSON
$response = $client->post('/users', [
    'headers' => ['Content-Type' => 'application/json'],
    'body' => json_encode(['name' => 'John']),
]);

// Form data
$response = $client->post('/form', [
    'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
    'body' => http_build_query(['field' => 'value']),
]);

// Raw text
$response = $client->post('/text', [
    'headers' => ['Content-Type' => 'text/plain'],
    'body' => 'Plain text content',
]);
```

### Timeout

Override request timeout:

```php
$response = $client->get('/slow-endpoint', [
    'timeout' => 60,  // 60 seconds
]);
```

### Connect Timeout

Override connection timeout:

```php
$response = $client->get('/endpoint', [
    'connect_timeout' => 3,  // 3 seconds
]);
```

### Skip SSL

Disable SSL verification (development only):

```php
$response = $client->get('https://self-signed.example.com/api', [
    'skip_ssl' => true,
]);
```

## URL Building

### With Base URL

```php
$client = HttpClientBuilder::create('https://api.example.com')
    ->build();

$client->get('/users');      // https://api.example.com/users
$client->get('/posts/1');    // https://api.example.com/posts/1
```

### Without Base URL

```php
$client = HttpClientBuilder::create()->build();

$client->get('https://api.example.com/users');
$client->get('https://other-api.com/data');
```

### Changing Base URL

```php
$v1 = HttpClientBuilder::create('https://api.example.com/v1')->build();
$v2 = $v1->withBaseUrl('https://api.example.com/v2');

$v1->get('/users');  // /v1/users
$v2->get('/users');  // /v2/users
```

## Common Patterns

### JSON API Client

```php
$client = HttpClientBuilder::create('https://api.example.com')
    ->baseHeaders([
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ])
    ->build();

// GET with query
$response = $client->get('/users', [
    'query' => ['status' => 'active'],
]);

// POST with JSON body
$response = $client->post('/users', [
    'body' => json_encode($userData),
]);
```

### Authenticated Requests

```php
$client = HttpClientBuilder::create('https://api.example.com')
    ->baseHeaders([
        'Authorization' => 'Bearer ' . $token,
    ])
    ->build();
```

### File Download

```php
$response = $client->get('/files/document.pdf');

file_put_contents('document.pdf', $response->body);
```

### API Versioning

```php
$client = HttpClientBuilder::create('https://api.example.com')
    ->baseHeaders([
        'Accept' => 'application/vnd.api+json; version=2',
    ])
    ->build();
```

## Next Steps

- [Handling Responses](handling-responses.md) - Work with responses
- [Configuration](configuration.md) - Client configuration
- [Examples](examples/basic.md) - More examples
