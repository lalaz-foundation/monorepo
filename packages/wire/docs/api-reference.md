# API Reference

Complete API documentation for Wire.

## HttpClientBuilder

Factory for creating configured HTTP clients.

### create()

```php
public static function create(string $baseUrl = ''): self
```

Create a new builder instance.

**Parameters:**
- `$baseUrl` - Base URL for all requests

**Returns:** `HttpClientBuilder` instance

**Example:**
```php
$builder = HttpClientBuilder::create('https://api.example.com');
```

---

### withTransport()

```php
public function withTransport(TransportInterface $transport): self
```

Set a custom transport implementation.

**Parameters:**
- `$transport` - Transport implementation

**Returns:** `self` for chaining

---

### withDefaults()

```php
public function withDefaults(array $defaults): self
```

Merge additional default options.

**Parameters:**
- `$defaults` - Options to merge

**Returns:** `self` for chaining

---

### baseHeaders()

```php
public function baseHeaders(array $headers): self
```

Set default headers for all requests.

**Parameters:**
- `$headers` - Header name => value pairs

**Returns:** `self` for chaining

**Example:**
```php
->baseHeaders([
    'Authorization' => 'Bearer token',
    'Accept' => 'application/json',
])
```

---

### timeout()

```php
public function timeout(int $seconds): self
```

Set request timeout.

**Parameters:**
- `$seconds` - Timeout in seconds

**Returns:** `self` for chaining

---

### connectTimeout()

```php
public function connectTimeout(int $seconds): self
```

Set connection timeout.

**Parameters:**
- `$seconds` - Timeout in seconds

**Returns:** `self` for chaining

---

### skipSsl()

```php
public function skipSsl(bool $enabled = true): self
```

Disable SSL certificate verification.

**Parameters:**
- `$enabled` - true to skip verification

**Returns:** `self` for chaining

---

### build()

```php
public function build(): HttpClient
```

Build the configured HTTP client.

**Returns:** `HttpClient` instance

---

## HttpClient

The main HTTP client class.

### request()

```php
public function request(
    string $method,
    string $endpoint,
    array $options = []
): HttpResponse
```

Make an HTTP request.

**Parameters:**
- `$method` - HTTP method (GET, POST, etc.)
- `$endpoint` - Request endpoint
- `$options` - Request options

**Returns:** `HttpResponse`

---

### get()

```php
public function get(string $endpoint, array $options = []): HttpResponse
```

Make a GET request.

---

### post()

```php
public function post(string $endpoint, array $options = []): HttpResponse
```

Make a POST request.

---

### put()

```php
public function put(string $endpoint, array $options = []): HttpResponse
```

Make a PUT request.

---

### patch()

```php
public function patch(string $endpoint, array $options = []): HttpResponse
```

Make a PATCH request.

---

### delete()

```php
public function delete(string $endpoint, array $options = []): HttpResponse
```

Make a DELETE request.

---

### withBaseUrl()

```php
public function withBaseUrl(string $baseUrl): self
```

Create a new client with a different base URL.

**Parameters:**
- `$baseUrl` - New base URL

**Returns:** New `HttpClient` instance

---

## Request Options

Available options for request methods:

| Option | Type | Description |
|--------|------|-------------|
| `headers` | array | Request headers |
| `query` | array | Query parameters |
| `body` | mixed | Request body |
| `timeout` | int | Request timeout (seconds) |
| `connect_timeout` | int | Connection timeout (seconds) |
| `skip_ssl` | bool | Skip SSL verification |

**Example:**
```php
$client->get('/endpoint', [
    'headers' => ['Accept' => 'application/json'],
    'query' => ['page' => 1],
    'timeout' => 30,
]);
```

---

## HttpRequest

Value object representing an HTTP request.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `method` | string | HTTP method |
| `url` | string | Full URL |
| `headers` | array | Request headers |
| `query` | array | Query parameters |
| `body` | mixed | Request body |
| `timeout` | int | Request timeout |
| `connectTimeout` | int | Connection timeout |
| `skipSsl` | bool | Skip SSL verification |
| `followRedirects` | bool | Follow redirects |
| `maxRedirects` | int | Maximum redirects |

---

## HttpResponse

Value object representing an HTTP response.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `statusCode` | int | HTTP status code |
| `headers` | array | Response headers |
| `body` | mixed | Response body (decoded if JSON) |
| `durationMs` | float | Request duration in milliseconds |

**Example:**
```php
$response = $client->get('/users');

echo $response->statusCode;   // 200
print_r($response->headers);  // ['Content-Type' => 'application/json']
print_r($response->body);     // ['users' => [...]]
echo $response->durationMs;   // 123.45
```

---

## HttpException

Exception thrown for HTTP errors.

### Constructor

```php
public function __construct(
    string $message = '',
    int $code = 0,
    ?Throwable $previous = null
)
```

### Example

```php
use Lalaz\Wire\HttpClient\HttpException;

try {
    $response = $client->get('/api/data');
} catch (HttpException $e) {
    echo "Error: " . $e->getMessage();
}
```

---

## TransportInterface

Interface for HTTP transport implementations.

### send()

```php
public function send(HttpRequest $request): array
```

Send an HTTP request.

**Parameters:**
- `$request` - Request to send

**Returns:** Array with keys: `status`, `headers`, `body`

---

## CurlTransport

Default transport using PHP's cURL extension.

### Example

```php
use Lalaz\Wire\HttpClient\Transport\CurlTransport;

$transport = new CurlTransport();
$client = HttpClientBuilder::create()
    ->withTransport($transport)
    ->build();
```
