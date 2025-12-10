# Concepts

Understanding Wire's architecture and design.

## Overview

Wire is a lightweight HTTP client built around simplicity and fluency. It provides a clean API for making HTTP requests without the complexity of larger HTTP libraries.

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                      HttpClientBuilder                       │
│  - Fluent configuration                                      │
│  - Creates HttpClient instances                              │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                        HttpClient                            │
│  - HTTP method shortcuts (get, post, put, patch, delete)    │
│  - Request building                                          │
│  - Response processing                                       │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                    TransportInterface                        │
│  - Actual HTTP communication                                 │
│  - CurlTransport (default)                                   │
└─────────────────────────────────────────────────────────────┘
```

## Core Components

### HttpClientBuilder

The builder pattern provides a fluent interface for configuring HTTP clients:

```php
$client = HttpClientBuilder::create('https://api.example.com')
    ->baseHeaders(['Authorization' => 'Bearer token'])
    ->timeout(30)
    ->connectTimeout(10)
    ->build();
```

**Responsibilities:**
- Configure base URL
- Set default headers
- Configure timeouts
- Build HttpClient instances

### HttpClient

The main client class that makes HTTP requests:

```php
$response = $client->get('/users');
$response = $client->post('/users', ['body' => $json]);
```

**Responsibilities:**
- Build HTTP requests
- Send requests via transport
- Process responses
- Decode JSON automatically

### HttpRequest

A value object representing an HTTP request:

```php
HttpRequest(
    method: 'GET',
    url: 'https://api.example.com/users',
    headers: ['Accept' => 'application/json'],
    query: ['page' => 1],
    body: null,
    timeout: 10,
    connectTimeout: 5,
    skipSsl: false,
    followRedirects: true,
    maxRedirects: 5,
)
```

### HttpResponse

A value object representing an HTTP response:

```php
HttpResponse(
    statusCode: 200,
    headers: ['Content-Type' => 'application/json'],
    body: ['id' => 1, 'name' => 'John'],  // Decoded JSON
    durationMs: 123.45,
)
```

### TransportInterface

The transport layer handles actual HTTP communication:

```php
interface TransportInterface
{
    public function send(HttpRequest $request): array;
}
```

**Default Implementation:** `CurlTransport` - Uses PHP's cURL extension.

## Request Flow

```
1. Client receives request
   └── $client->get('/users', ['query' => ['page' => 1]])

2. Build HttpRequest
   └── URL: https://api.example.com/users
   └── Query: ['page' => 1]
   └── Headers merged with defaults

3. Transport sends request
   └── CurlTransport executes cURL

4. Process response
   └── Parse headers
   └── Decode JSON body
   └── Calculate duration

5. Return HttpResponse
   └── statusCode: 200
   └── body: decoded array
   └── durationMs: timing
```

## Design Principles

### 1. Immutability

The `withBaseUrl()` method returns a new client instance:

```php
$client1 = HttpClientBuilder::create('https://api.v1.com')->build();
$client2 = $client1->withBaseUrl('https://api.v2.com');

// $client1 and $client2 are independent
```

### 2. Fluent Interface

Methods chain naturally:

```php
$client = HttpClientBuilder::create('https://api.example.com')
    ->baseHeaders(['Accept' => 'application/json'])
    ->timeout(30)
    ->connectTimeout(10)
    ->skipSsl(false)
    ->build();
```

### 3. Sensible Defaults

Wire provides reasonable defaults out of the box:

| Option | Default |
|--------|---------|
| Timeout | 10 seconds |
| Connect Timeout | 5 seconds |
| Skip SSL | false |
| Follow Redirects | true |
| Max Redirects | 5 |

### 4. Separation of Concerns

- **Builder** - Configuration
- **Client** - Request orchestration
- **Transport** - HTTP communication
- **Request/Response** - Data containers

## Extending Wire

### Custom Transport

Create a custom transport for testing or special requirements:

```php
final class MockTransport implements TransportInterface
{
    public function send(HttpRequest $request): array
    {
        return [
            'status' => 200,
            'headers' => [],
            'body' => json_encode(['mocked' => true]),
        ];
    }
}

$client = HttpClientBuilder::create()
    ->withTransport(new MockTransport())
    ->build();
```

## Next Steps

- [Making Requests](making-requests.md) - Learn all request options
- [Configuration](configuration.md) - All configuration options
- [Testing](testing.md) - Testing with Wire
