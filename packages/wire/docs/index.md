# Lalaz Wire

A lightweight, fluent HTTP client for PHP applications.

## Overview

Wire provides a clean, intuitive API for making HTTP requests with support for:

- **Fluent Builder** - Chain methods to configure requests
- **All HTTP Methods** - GET, POST, PUT, PATCH, DELETE
- **Custom Headers** - Set request and default headers
- **Query Parameters** - Build URLs with query strings
- **Timeout Control** - Configure request and connection timeouts
- **SSL Options** - Skip certificate verification for development
- **JSON Handling** - Automatic JSON encoding/decoding

## Quick Example

```php
use Lalaz\Wire\HttpClient\HttpClientBuilder;

// Create a client
$client = HttpClientBuilder::create('https://api.example.com')
    ->baseHeaders(['Authorization' => 'Bearer token'])
    ->timeout(30)
    ->build();

// Make requests
$response = $client->get('/users');
$response = $client->post('/users', [
    'body' => json_encode(['name' => 'John']),
]);

// Access response
echo $response->statusCode;  // 200
print_r($response->body);    // Decoded JSON array
```

## Installation

```bash
composer require lalaz/wire
```

## Requirements

- PHP 8.2+
- cURL extension

## Documentation

### Getting Started

- [Quick Start](quick-start.md) - Get up and running in 5 minutes
- [Concepts](concepts.md) - Core concepts and architecture
- [Glossary](glossary.md) - Terminology reference

### Usage

- [Making Requests](making-requests.md) - HTTP methods and options
- [Handling Responses](handling-responses.md) - Response structure and data
- [Configuration](configuration.md) - Client configuration options

### Examples

- [Basic Usage](examples/basic.md) - Simple request examples
- [API Client](examples/api-client.md) - Building an API client
- [File Upload](examples/file-upload.md) - Uploading files
- [Error Handling](examples/error-handling.md) - Handling failures

### Reference

- [API Reference](api-reference.md) - Complete API documentation
- [Testing](testing.md) - Testing with Wire

## Features at a Glance

| Feature | Description |
|---------|-------------|
| **Builder Pattern** | Fluent interface for client configuration |
| **HTTP Methods** | GET, POST, PUT, PATCH, DELETE support |
| **Base URL** | Set once, use for all requests |
| **Headers** | Default and per-request headers |
| **Query Params** | Automatic URL building |
| **Timeouts** | Request and connection timeouts |
| **SSL Skip** | Disable SSL verification |
| **JSON Auto** | Automatic JSON decode |
| **Duration** | Response timing in milliseconds |

## License

MIT License
