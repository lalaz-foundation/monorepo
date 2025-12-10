# Configuration

All configuration options for Wire HTTP client.

## Builder Configuration

Configure clients using `HttpClientBuilder`:

```php
$client = HttpClientBuilder::create('https://api.example.com')
    ->baseHeaders([...])
    ->timeout(30)
    ->connectTimeout(10)
    ->skipSsl(false)
    ->build();
```

## Options Reference

### Base URL

Set the root URL for all requests:

```php
HttpClientBuilder::create('https://api.example.com')
```

The base URL:
- Is prepended to all request endpoints
- Trailing slashes are automatically removed
- Can be changed later with `withBaseUrl()`

### Base Headers

Set default headers for all requests:

```php
->baseHeaders([
    'Authorization' => 'Bearer token',
    'Accept' => 'application/json',
    'User-Agent' => 'MyApp/1.0',
])
```

Base headers:
- Are included in every request
- Can be overridden per-request
- Merge with request-specific headers

### Timeout

Maximum time to wait for complete response:

```php
->timeout(30)  // 30 seconds
```

Default: 10 seconds

### Connect Timeout

Maximum time to wait for connection:

```php
->connectTimeout(10)  // 10 seconds
```

Default: 5 seconds

### Skip SSL

Disable SSL certificate verification:

```php
->skipSsl()      // Disable verification
->skipSsl(true)  // Same as above
->skipSsl(false) // Enable verification (default)
```

Default: false (SSL verification enabled)

> ⚠️ **Warning:** Only use `skipSsl()` in development. Never in production!

### Custom Transport

Provide a custom transport implementation:

```php
->withTransport(new CustomTransport())
```

Default: `CurlTransport`

### With Defaults

Merge additional default options:

```php
->withDefaults([
    'timeout' => 30,
    'headers' => ['X-Custom' => 'value'],
])
```

## Default Values

| Option | Default |
|--------|---------|
| timeout | 10 seconds |
| connect_timeout | 5 seconds |
| skip_ssl | false |
| follow_redirects | true |
| max_redirects | 5 |

## Per-Request Options

Override defaults for individual requests:

```php
$response = $client->get('/slow-endpoint', [
    'timeout' => 60,
    'connect_timeout' => 15,
    'skip_ssl' => true,
    'headers' => ['X-Override' => 'value'],
]);
```

## Configuration Patterns

### Development vs Production

```php
$builder = HttpClientBuilder::create($apiUrl)
    ->baseHeaders(['Accept' => 'application/json'])
    ->timeout(30);

if ($environment === 'development') {
    $builder->skipSsl();
}

$client = $builder->build();
```

### Environment-Based Configuration

```php
$config = [
    'base_url' => getenv('API_BASE_URL'),
    'timeout' => (int) getenv('API_TIMEOUT') ?: 30,
    'token' => getenv('API_TOKEN'),
];

$client = HttpClientBuilder::create($config['base_url'])
    ->baseHeaders(['Authorization' => "Bearer {$config['token']}"])
    ->timeout($config['timeout'])
    ->build();
```

### Multiple API Clients

```php
// Main API
$mainApi = HttpClientBuilder::create('https://api.example.com/v1')
    ->baseHeaders(['Authorization' => "Bearer {$mainToken}"])
    ->timeout(30)
    ->build();

// External Service
$externalApi = HttpClientBuilder::create('https://external-service.com')
    ->baseHeaders(['X-API-Key' => $apiKey])
    ->timeout(60)
    ->build();

// Internal Service (may use self-signed certs)
$internalApi = HttpClientBuilder::create('https://internal.local')
    ->skipSsl()
    ->timeout(10)
    ->build();
```

### Service Provider Registration

```php
// In a service provider
$this->singleton(HttpClient::class, function () {
    return HttpClientBuilder::create(config('api.base_url'))
        ->baseHeaders([
            'Authorization' => 'Bearer ' . config('api.token'),
            'Accept' => 'application/json',
        ])
        ->timeout(config('api.timeout', 30))
        ->build();
});
```

## Next Steps

- [Making Requests](making-requests.md) - Request options
- [Testing](testing.md) - Testing with Wire
- [API Reference](api-reference.md) - Complete API
