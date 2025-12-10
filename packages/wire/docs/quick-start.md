# Quick Start

Get up and running with Wire in 5 minutes.

## Installation

```bash
composer require lalaz/wire
```

## Your First Request

```php
use Lalaz\Wire\HttpClient\HttpClientBuilder;

// Create a client
$client = HttpClientBuilder::create()->build();

// Make a GET request
$response = $client->get('https://api.github.com/users/octocat');

// Access the response
echo $response->statusCode;  // 200
echo $response->body['login'];  // "octocat"
```

## Using a Base URL

```php
$client = HttpClientBuilder::create('https://api.example.com')
    ->build();

// Now all requests are relative to the base URL
$client->get('/users');      // https://api.example.com/users
$client->get('/products');   // https://api.example.com/products
```

## Adding Headers

```php
$client = HttpClientBuilder::create('https://api.example.com')
    ->baseHeaders([
        'Authorization' => 'Bearer your-token',
        'Accept' => 'application/json',
    ])
    ->build();

$response = $client->get('/protected-resource');
```

## Making POST Requests

```php
$response = $client->post('/users', [
    'headers' => ['Content-Type' => 'application/json'],
    'body' => json_encode([
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]),
]);

if ($response->statusCode === 201) {
    echo "User created with ID: " . $response->body['id'];
}
```

## Query Parameters

```php
$response = $client->get('/search', [
    'query' => [
        'q' => 'php',
        'page' => 1,
        'limit' => 20,
    ],
]);

// Request URL: https://api.example.com/search?q=php&page=1&limit=20
```

## Handling Errors

```php
use Lalaz\Wire\HttpClient\HttpException;

try {
    $response = $client->get('/resource');
    
    if ($response->statusCode >= 400) {
        echo "Error: " . $response->statusCode;
    }
} catch (HttpException $e) {
    echo "Request failed: " . $e->getMessage();
}
```

## Configuration Options

```php
$client = HttpClientBuilder::create('https://api.example.com')
    ->timeout(30)           // Request timeout in seconds
    ->connectTimeout(10)    // Connection timeout in seconds
    ->skipSsl()             // Skip SSL verification (dev only!)
    ->build();
```

## Complete Example

```php
use Lalaz\Wire\HttpClient\HttpClientBuilder;
use Lalaz\Wire\HttpClient\HttpException;

// Configure client
$client = HttpClientBuilder::create('https://jsonplaceholder.typicode.com')
    ->baseHeaders(['Accept' => 'application/json'])
    ->timeout(30)
    ->build();

try {
    // Get all posts
    $posts = $client->get('/posts');
    echo "Found " . count($posts->body) . " posts\n";

    // Get a single post
    $post = $client->get('/posts/1');
    echo "Post title: " . $post->body['title'] . "\n";

    // Create a new post
    $newPost = $client->post('/posts', [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => json_encode([
            'title' => 'My Post',
            'body' => 'Content here',
            'userId' => 1,
        ]),
    ]);
    echo "Created post ID: " . $newPost->body['id'] . "\n";

    // Update a post
    $updated = $client->put('/posts/1', [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => json_encode(['title' => 'Updated Title']),
    ]);

    // Delete a post
    $deleted = $client->delete('/posts/1');
    echo "Delete status: " . $deleted->statusCode . "\n";

} catch (HttpException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
```

## Next Steps

- [Concepts](concepts.md) - Understand how Wire works
- [Making Requests](making-requests.md) - Learn all request options
- [API Reference](api-reference.md) - Full API documentation
