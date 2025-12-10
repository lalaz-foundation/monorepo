# Glossary

Key terms and definitions for Wire.

## A

### API Client
A class that wraps HttpClient to provide domain-specific methods for interacting with an API.

## B

### Base URL
The root URL prepended to all request endpoints. Set via `HttpClientBuilder::create($baseUrl)`.

```php
$client = HttpClientBuilder::create('https://api.example.com')->build();
$client->get('/users');  // https://api.example.com/users
```

### Body
The content sent with POST, PUT, or PATCH requests. Can be a string (JSON, form data) or other content.

```php
$client->post('/users', [
    'body' => json_encode(['name' => 'John']),
]);
```

### Builder Pattern
A design pattern that provides a fluent interface for constructing objects. Used by `HttpClientBuilder`.

## C

### Connect Timeout
The maximum time to wait for a connection to be established. Default: 5 seconds.

```php
HttpClientBuilder::create()->connectTimeout(10)->build();
```

### cURL
PHP extension used by `CurlTransport` for HTTP communication.

### CurlTransport
The default transport implementation that uses PHP's cURL extension.

## D

### Duration
The time taken for a request in milliseconds. Available in `HttpResponse::$durationMs`.

## E

### Endpoint
The path portion of a URL, appended to the base URL.

```php
$client->get('/users/123');  // endpoint: /users/123
```

## F

### Fluent Interface
An API design where methods return `$this` to allow chaining.

```php
HttpClientBuilder::create()
    ->timeout(30)
    ->skipSsl()
    ->build();
```

### Follow Redirects
Whether to automatically follow HTTP redirects (3xx responses). Default: true.

## H

### Headers
HTTP headers sent with requests or received in responses.

```php
// Request headers
$client->get('/data', [
    'headers' => ['Authorization' => 'Bearer token'],
]);

// Response headers
echo $response->headers['Content-Type'];
```

### HttpClient
The main class for making HTTP requests.

### HttpClientBuilder
Factory class for creating configured HttpClient instances.

### HttpException
Exception thrown when HTTP requests fail (connection errors, timeouts).

### HttpRequest
Value object representing an outgoing HTTP request.

### HttpResponse
Value object representing an HTTP response with status, headers, body, and duration.

## I

### Immutability
Property of objects that cannot be changed after creation. `HttpRequest` and `HttpResponse` are immutable.

## J

### JSON
JavaScript Object Notation. Wire automatically decodes JSON response bodies.

## M

### Max Redirects
Maximum number of redirects to follow. Default: 5.

### Method
HTTP method (GET, POST, PUT, PATCH, DELETE).

## Q

### Query Parameters
URL parameters added to the request URL.

```php
$client->get('/search', [
    'query' => ['q' => 'test', 'page' => 1],
]);
// URL: /search?q=test&page=1
```

## R

### Response
See HttpResponse.

### Redirect
HTTP 3xx status indicating the resource has moved.

## S

### Skip SSL
Option to disable SSL certificate verification. Use only in development!

```php
HttpClientBuilder::create()->skipSsl()->build();
```

### Status Code
HTTP status code (200, 404, 500, etc.). Available in `HttpResponse::$statusCode`.

## T

### Timeout
Maximum time to wait for a complete response. Default: 10 seconds.

```php
HttpClientBuilder::create()->timeout(30)->build();
```

### Transport
Interface for HTTP communication. Default implementation: `CurlTransport`.

### TransportInterface
Contract that all transports must implement.

```php
interface TransportInterface
{
    public function send(HttpRequest $request): array;
}
```

## U

### URL
Full address of the HTTP resource (base URL + endpoint + query string).

## V

### Value Object
An immutable object defined by its values. `HttpRequest` and `HttpResponse` are value objects.

## W

### Wire
The Lalaz HTTP client package.

### withBaseUrl()
Method to create a new client with a different base URL.

```php
$newClient = $client->withBaseUrl('https://api.v2.com');
```
