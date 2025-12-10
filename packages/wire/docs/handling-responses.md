# Handling Responses

Working with HTTP responses in Wire.

## Response Structure

Every request returns an `HttpResponse` object:

```php
$response = $client->get('/users');

echo $response->statusCode;   // 200
print_r($response->headers);  // ['Content-Type' => 'application/json', ...]
print_r($response->body);     // Decoded JSON or raw body
echo $response->durationMs;   // 123.45
```

## Properties

### statusCode

The HTTP status code as an integer:

```php
$response = $client->get('/users');

if ($response->statusCode === 200) {
    // Success
}

if ($response->statusCode >= 400) {
    // Client or server error
}
```

Common status codes:

| Code | Meaning |
|------|---------|
| 200 | OK |
| 201 | Created |
| 204 | No Content |
| 301 | Moved Permanently |
| 400 | Bad Request |
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Not Found |
| 500 | Internal Server Error |

### headers

Response headers as an associative array:

```php
$response = $client->get('/api/data');

$contentType = $response->headers['Content-Type'] ?? null;
$requestId = $response->headers['X-Request-Id'] ?? null;
```

### body

The response body, automatically decoded if JSON:

```php
// JSON response is decoded to array
$response = $client->get('/api/users');
$users = $response->body;  // array

// Non-JSON response remains string
$response = $client->get('/page.html');
$html = $response->body;  // string
```

### durationMs

Request duration in milliseconds:

```php
$response = $client->get('/api/slow');

echo "Request took {$response->durationMs}ms";

if ($response->durationMs > 1000) {
    log_slow_request($response->durationMs);
}
```

## Working with JSON

Wire automatically decodes JSON response bodies:

```php
$response = $client->get('/api/user/123');

// Body is already an array
$name = $response->body['name'];
$email = $response->body['email'];
```

### Nested Data

```php
$response = $client->get('/api/order/456');

// Access nested structure
$items = $response->body['data']['items'];
$total = $response->body['data']['total'];
$meta = $response->body['meta'];
```

### Arrays

```php
$response = $client->get('/api/users');

foreach ($response->body as $user) {
    echo $user['name'] . "\n";
}

$count = count($response->body);
```

## Status Code Helpers

Create helper functions for common checks:

```php
function isSuccess(HttpResponse $response): bool
{
    return $response->statusCode >= 200 && $response->statusCode < 300;
}

function isRedirect(HttpResponse $response): bool
{
    return $response->statusCode >= 300 && $response->statusCode < 400;
}

function isClientError(HttpResponse $response): bool
{
    return $response->statusCode >= 400 && $response->statusCode < 500;
}

function isServerError(HttpResponse $response): bool
{
    return $response->statusCode >= 500;
}

// Usage
if (isSuccess($response)) {
    return $response->body;
}
```

## Error Handling

### By Status Code

```php
$response = $client->get('/api/resource');

switch ($response->statusCode) {
    case 200:
        return $response->body;
    case 404:
        throw new ResourceNotFoundException();
    case 401:
        throw new UnauthorizedException();
    case 500:
        throw new ServerException($response->body['message'] ?? 'Server error');
    default:
        throw new HttpException("Unexpected status: {$response->statusCode}");
}
```

### With Try-Catch

```php
use Lalaz\Wire\HttpClient\HttpException;

try {
    $response = $client->get('/api/data');
    
    if ($response->statusCode !== 200) {
        throw new ApiException(
            $response->body['error'] ?? 'Unknown error',
            $response->statusCode
        );
    }
    
    return $response->body;
    
} catch (HttpException $e) {
    // Connection error, timeout, etc.
    log_error('HTTP request failed: ' . $e->getMessage());
    throw new ServiceUnavailableException($e->getMessage(), 0, $e);
}
```

## Response Patterns

### Check Before Access

```php
$response = $client->get('/api/users');

if ($response->statusCode === 200 && is_array($response->body)) {
    return $response->body;
}

return [];
```

### Default Values

```php
$response = $client->get('/api/config');

$settings = [
    'theme' => $response->body['theme'] ?? 'default',
    'language' => $response->body['language'] ?? 'en',
    'timezone' => $response->body['timezone'] ?? 'UTC',
];
```

### Transform Response

```php
$response = $client->get('/api/users');

$users = array_map(function ($data) {
    return new User(
        id: $data['id'],
        name: $data['name'],
        email: $data['email'],
    );
}, $response->body);
```

## Debugging

### Log Response Details

```php
$response = $client->get('/api/data');

$logger->debug('API Response', [
    'status' => $response->statusCode,
    'duration' => $response->durationMs,
    'headers' => $response->headers,
    'body_size' => strlen(json_encode($response->body)),
]);
```

### Inspect Full Response

```php
function debugResponse(HttpResponse $response): void
{
    echo "=== Response Debug ===\n";
    echo "Status: {$response->statusCode}\n";
    echo "Duration: {$response->durationMs}ms\n";
    echo "Headers:\n";
    foreach ($response->headers as $name => $value) {
        echo "  {$name}: {$value}\n";
    }
    echo "Body:\n";
    print_r($response->body);
}
```

## Next Steps

- [Configuration](configuration.md) - Client configuration
- [Error Handling](examples/error-handling.md) - Error handling patterns
- [API Reference](api-reference.md) - Complete API docs
