# Troubleshooting

Common issues and solutions when using Wire.

## Connection Errors

### cURL Error: Could not resolve host

**Symptom:**
```
HttpException: cURL error: Could not resolve host: api.example.com
```

**Causes:**
- Invalid hostname
- DNS resolution failure
- Network connectivity issues

**Solutions:**

```php
// 1. Verify the URL is correct
$client = HttpClientBuilder::create('https://api.example.com') // Check spelling
    ->build();

// 2. Try with IP address directly (for testing)
$client = HttpClientBuilder::create('https://192.168.1.100:8080')
    ->build();

// 3. Check DNS from terminal
// $ nslookup api.example.com
```

### cURL Error: Connection timed out

**Symptom:**
```
HttpException: cURL error: Connection timed out after 30000 milliseconds
```

**Causes:**
- Server not responding
- Firewall blocking connection
- Timeout too short

**Solutions:**

```php
// 1. Increase timeout
$client = HttpClientBuilder::create($baseUrl)
    ->timeout(120)  // 2 minutes
    ->build();

// 2. Per-request timeout
$response = $client->get('/slow-endpoint', [
    'timeout' => 300,  // 5 minutes for this request
]);

// 3. Check if server is reachable
// $ curl -v https://api.example.com
```

### cURL Error: SSL certificate problem

**Symptom:**
```
HttpException: cURL error: SSL certificate problem: unable to get local issuer certificate
```

**Causes:**
- Self-signed certificate
- Expired certificate
- Missing CA bundle

**Solutions:**

```php
// For development/testing only - NOT recommended for production
// Modify CurlTransport options if needed

// Better: Install proper certificates
// On macOS: brew install ca-certificates
// On Ubuntu: apt-get install ca-certificates
```

## Request Issues

### POST Body Not Sent

**Symptom:** Server receives empty body.

**Solutions:**

```php
// 1. Ensure body is properly encoded
$response = $client->post('/api/users', [
    'body' => json_encode($data),  // Must be string
    'headers' => ['Content-Type' => 'application/json'],
]);

// 2. For form data
$response = $client->post('/api/login', [
    'body' => http_build_query([
        'username' => 'user',
        'password' => 'pass',
    ]),
    'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
]);
```

### Query Parameters Not Applied

**Symptom:** Server doesn't receive query parameters.

**Solutions:**

```php
// Correct: Use 'query' key
$response = $client->get('/search', [
    'query' => [
        'q' => 'search term',
        'page' => 1,
    ],
]);

// Wrong: Passing in body
$response = $client->get('/search', [
    'body' => ['q' => 'search term'],  // Won't work for GET
]);
```

### Headers Not Being Set

**Symptom:** Server doesn't receive expected headers.

**Solutions:**

```php
// 1. Check header format (key => value)
$response = $client->get('/api', [
    'headers' => [
        'Authorization' => 'Bearer token',  // Correct
        'Bearer token',  // Wrong - missing key
    ],
]);

// 2. Use baseHeaders for common headers
$client = HttpClientBuilder::create($baseUrl)
    ->baseHeaders([
        'Authorization' => 'Bearer token',
        'Accept' => 'application/json',
    ])
    ->build();
```

## Response Issues

### JSON Decode Error

**Symptom:** Response body is empty or malformed.

**Solutions:**

```php
$response = $client->get('/api/data');

// 1. Check if response is JSON
$contentType = $response->headers['Content-Type'] ?? '';
if (!str_contains($contentType, 'application/json')) {
    // Not JSON - check raw body
    var_dump($response->body);
}

// 2. Check status code first
if ($response->statusCode !== 200) {
    // Error response may not be JSON
    echo "Error: {$response->statusCode}\n";
}
```

### Response Body is Empty

**Symptom:** `$response->body` returns empty string.

**Solutions:**

```php
// 1. Some endpoints return 204 No Content
if ($response->statusCode === 204) {
    // Success, but no body expected
    return true;
}

// 2. Check raw response for debugging
var_dump($response);

// 3. Check Content-Length header
$length = $response->headers['Content-Length'] ?? 'unknown';
echo "Content-Length: {$length}\n";
```

### Wrong Response Type

**Symptom:** Expecting array but getting string.

**Solutions:**

```php
$response = $client->get('/api/data');

// Wire doesn't auto-decode JSON - body is what server returns
// If server returns JSON string, decode it:
$data = json_decode($response->body, true);

// If body is already array (from some transports), use directly:
$data = is_array($response->body) ? $response->body : json_decode($response->body, true);
```

## Testing Issues

### FakeTransport Not Returning Expected Response

**Symptom:** Test receives unexpected response.

**Solutions:**

```php
// 1. Check queue order - responses are returned FIFO
$transport = new FakeTransport();
$transport
    ->willReturn(new HttpResponse(200, [], ['first' => 'response']))
    ->willReturn(new HttpResponse(200, [], ['second' => 'response']));

$client->get('/first');   // Gets first response
$client->get('/second');  // Gets second response

// 2. For exceptions, willThrow replaces queue
$transport->willThrow(new \Exception('error'));
// All requests will now throw

// 3. Reset transport between tests
protected function setUp(): void
{
    $this->transport = new FakeTransport();
}
```

### Tests Pass but Production Fails

**Symptom:** Everything works in tests but fails with real API.

**Solutions:**

```php
// 1. Test with real API in integration tests
/**
 * @group integration
 */
public function testRealApiCall(): void
{
    if (!getenv('RUN_INTEGRATION_TESTS')) {
        $this->markTestSkipped('Integration tests disabled');
    }
    
    $client = HttpClientBuilder::create('https://real-api.com')
        ->build();
    
    $response = $client->get('/health');
    $this->assertEquals(200, $response->statusCode);
}

// 2. Verify your FakeTransport matches real API behavior
// Check: status codes, response format, headers
```

## Performance Issues

### Slow Requests

**Symptom:** Requests take too long.

**Solutions:**

```php
// 1. Check response time
$response = $client->get('/api/data');
echo "Duration: {$response->durationMs}ms\n";

// 2. Use shorter timeout for fast endpoints
$response = $client->get('/health', ['timeout' => 5]);

// 3. Reuse client instances
// Don't create new client for each request
$client = HttpClientBuilder::create($baseUrl)->build();

// Good: reuse
foreach ($urls as $url) {
    $client->get($url);
}

// Bad: new client each time
foreach ($urls as $url) {
    $client = HttpClientBuilder::create($baseUrl)->build();
    $client->get($url);
}
```

### Memory Usage

**Symptom:** High memory usage with large responses.

**Solutions:**

```php
// 1. Process large responses in chunks
// (requires custom transport implementation)

// 2. Don't store all responses in memory
foreach ($ids as $id) {
    $response = $client->get("/items/{$id}");
    processItem($response->body);  // Process immediately
    // $response goes out of scope and is freed
}

// 3. Use generators for pagination
function fetchAllPages(HttpClient $client): \Generator
{
    $page = 1;
    do {
        $response = $client->get('/items', ['query' => ['page' => $page]]);
        yield from $response->body['data'];
        $page++;
    } while ($response->body['has_more']);
}
```

## Debug Tips

### Enable Verbose Logging

```php
// Create a debugging wrapper
final class DebugClient
{
    public function __construct(private HttpClient $client) {}
    
    public function request(string $method, string $url, array $options = []): HttpResponse
    {
        echo ">>> {$method} {$url}\n";
        echo "Options: " . json_encode($options, JSON_PRETTY_PRINT) . "\n";
        
        $response = $this->client->request($method, $url, $options);
        
        echo "<<< {$response->statusCode} ({$response->durationMs}ms)\n";
        echo "Headers: " . json_encode($response->headers, JSON_PRETTY_PRINT) . "\n";
        echo "Body: " . json_encode($response->body, JSON_PRETTY_PRINT) . "\n";
        
        return $response;
    }
}

// Use for debugging
$debug = new DebugClient($client);
$debug->request('GET', '/api/data');
```

### Inspect Request Details

```php
// HttpRequest stores all request details
$request = new HttpRequest(
    method: 'POST',
    url: '/api/users',
    headers: ['Authorization' => 'Bearer token'],
    query: ['include' => 'profile'],
    body: '{"name":"John"}',
    timeout: 30
);

echo "Method: {$request->method}\n";
echo "URL: {$request->url}\n";
echo "Headers: " . print_r($request->headers, true);
echo "Query: " . print_r($request->query, true);
echo "Body: {$request->body}\n";
echo "Timeout: {$request->timeout}\n";
```

### Check cURL Directly

```bash
# Test connection from terminal
curl -v https://api.example.com/health

# Test with headers
curl -H "Authorization: Bearer token" \
     -H "Content-Type: application/json" \
     https://api.example.com/users

# Test POST
curl -X POST \
     -H "Content-Type: application/json" \
     -d '{"name":"John"}' \
     https://api.example.com/users
```
