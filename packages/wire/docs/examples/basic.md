# Basic Usage Examples

Common patterns for using Wire HTTP client.

## Simple GET Request

```php
use Lalaz\Wire\HttpClient\HttpClientBuilder;

$client = HttpClientBuilder::create()->build();

$response = $client->get('https://api.github.com/users/octocat');

echo "Status: {$response->statusCode}\n";
echo "Name: {$response->body['name']}\n";
echo "Duration: {$response->durationMs}ms\n";
```

## GET with Query Parameters

```php
$client = HttpClientBuilder::create('https://api.example.com')
    ->build();

$response = $client->get('/search', [
    'query' => [
        'q' => 'php framework',
        'page' => 1,
        'per_page' => 20,
        'sort' => 'stars',
    ],
]);

// URL: /search?q=php+framework&page=1&per_page=20&sort=stars
```

## POST with JSON Body

```php
$client = HttpClientBuilder::create('https://api.example.com')
    ->baseHeaders(['Content-Type' => 'application/json'])
    ->build();

$response = $client->post('/users', [
    'body' => json_encode([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'role' => 'admin',
    ]),
]);

if ($response->statusCode === 201) {
    echo "Created user ID: {$response->body['id']}\n";
}
```

## PUT Request

```php
$response = $client->put('/users/123', [
    'body' => json_encode([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]),
]);

if ($response->statusCode === 200) {
    echo "User updated\n";
}
```

## PATCH Request

```php
$response = $client->patch('/users/123', [
    'body' => json_encode([
        'name' => 'Updated Name',
    ]),
]);
```

## DELETE Request

```php
$response = $client->delete('/users/123');

if ($response->statusCode === 204) {
    echo "User deleted\n";
}
```

## With Custom Headers

```php
$response = $client->get('/protected-resource', [
    'headers' => [
        'Authorization' => 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...',
        'Accept' => 'application/json',
        'X-Request-ID' => uniqid(),
    ],
]);
```

## With Timeout

```php
// For slow endpoints
$response = $client->get('/slow-report', [
    'timeout' => 120,  // 2 minutes
]);
```

## Multiple Requests

```php
$client = HttpClientBuilder::create('https://api.example.com')
    ->baseHeaders(['Authorization' => 'Bearer token'])
    ->build();

// Get all users
$users = $client->get('/users');

// Get each user's posts
foreach ($users->body as $user) {
    $posts = $client->get("/users/{$user['id']}/posts");
    echo "{$user['name']} has " . count($posts->body) . " posts\n";
}
```

## Check Response Status

```php
$response = $client->get('/api/resource');

if ($response->statusCode >= 200 && $response->statusCode < 300) {
    // Success
    return $response->body;
}

if ($response->statusCode === 404) {
    // Not found
    return null;
}

if ($response->statusCode >= 400) {
    // Error
    throw new Exception("API error: {$response->statusCode}");
}
```

## Response Headers

```php
$response = $client->get('/api/data');

// Check content type
$contentType = $response->headers['Content-Type'] ?? 'unknown';

// Get rate limit info
$remaining = $response->headers['X-RateLimit-Remaining'] ?? null;
$limit = $response->headers['X-RateLimit-Limit'] ?? null;

echo "Rate limit: {$remaining}/{$limit}\n";
```

## Response Timing

```php
$response = $client->get('/api/data');

echo "Request completed in {$response->durationMs}ms\n";

if ($response->durationMs > 1000) {
    // Log slow request
    error_log("Slow API call: {$response->durationMs}ms");
}
```
