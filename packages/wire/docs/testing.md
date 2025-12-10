# Testing

Testing HTTP clients and code that uses Wire.

## Using FakeTransport

Wire provides a `FakeTransport` for testing without making real HTTP requests:

```php
use Lalaz\Wire\Tests\Common\FakeTransport;
use Lalaz\Wire\HttpClient\HttpClientBuilder;

$transport = new FakeTransport(
    status: 200,
    headers: ['Content-Type' => 'application/json'],
    body: ['id' => 1, 'name' => 'John']
);

$client = HttpClientBuilder::create('https://api.example.com')
    ->withTransport($transport)
    ->build();

$response = $client->get('/users/1');

// Assert response
$this->assertSame(200, $response->statusCode);
$this->assertSame('John', $response->body['name']);

// Assert request was made
$this->assertSame('/users/1', $transport->lastRequest()->url);
```

## FakeTransport Features

### Queue Multiple Responses

```php
$transport = new FakeTransport(status: 200, body: ['first' => true]);
$transport->queueResponse(status: 201, body: ['second' => true]);
$transport->queueResponse(status: 204);

// First request returns 200
$response1 = $client->get('/first');

// Second request returns 201
$response2 = $client->post('/second');

// Third request returns 204
$response3 = $client->delete('/third');
```

### Simulate Exceptions

```php
use Lalaz\Wire\HttpClient\HttpException;

$transport = new FakeTransport();
$transport->willThrow(new HttpException('Connection timeout'));

$this->expectException(HttpException::class);
$client->get('/timeout');
```

### Inspect Requests

```php
$transport = new FakeTransport();
$client = HttpClientBuilder::create('https://api.example.com')
    ->withTransport($transport)
    ->build();

$client->post('/users', [
    'headers' => ['Content-Type' => 'application/json'],
    'body' => json_encode(['name' => 'John']),
]);

// Get last request
$request = $transport->lastRequest();
$this->assertSame('POST', $request->method);
$this->assertSame('https://api.example.com/users', $request->url);
$this->assertSame('{"name":"John"}', $request->body);

// Get all requests
$this->assertCount(1, $transport->requests);

// Clear requests
$transport->clearRequests();
```

## Testing Patterns

### Test Service That Uses HttpClient

```php
class UserService
{
    public function __construct(private HttpClient $client) {}
    
    public function getUser(int $id): ?array
    {
        $response = $this->client->get("/users/{$id}");
        return $response->statusCode === 200 ? $response->body : null;
    }
}

class UserServiceTest extends TestCase
{
    public function test_get_user_returns_user_data(): void
    {
        $transport = new FakeTransport(
            body: ['id' => 1, 'name' => 'John']
        );
        
        $client = HttpClientBuilder::create('https://api.example.com')
            ->withTransport($transport)
            ->build();
        
        $service = new UserService($client);
        $user = $service->getUser(1);
        
        $this->assertSame('John', $user['name']);
    }
    
    public function test_get_user_returns_null_on_404(): void
    {
        $transport = new FakeTransport(status: 404);
        
        $client = HttpClientBuilder::create('https://api.example.com')
            ->withTransport($transport)
            ->build();
        
        $service = new UserService($client);
        $user = $service->getUser(999);
        
        $this->assertNull($user);
    }
}
```

### Test Error Handling

```php
public function test_handles_server_error(): void
{
    $transport = new FakeTransport(
        status: 500,
        body: ['error' => 'Internal server error']
    );
    
    $client = HttpClientBuilder::create()
        ->withTransport($transport)
        ->build();
    
    $response = $client->get('https://api.example.com/data');
    
    $this->assertSame(500, $response->statusCode);
}

public function test_handles_connection_error(): void
{
    $transport = new FakeTransport();
    $transport->willThrow(new HttpException('Connection refused'));
    
    $client = HttpClientBuilder::create()
        ->withTransport($transport)
        ->build();
    
    $this->expectException(HttpException::class);
    $this->expectExceptionMessage('Connection refused');
    
    $client->get('https://api.example.com/data');
}
```

### Test Request Building

```php
public function test_sends_correct_headers(): void
{
    $transport = new FakeTransport();
    
    $client = HttpClientBuilder::create('https://api.example.com')
        ->withTransport($transport)
        ->baseHeaders(['Authorization' => 'Bearer token'])
        ->build();
    
    $client->get('/protected', [
        'headers' => ['X-Custom' => 'value'],
    ]);
    
    $request = $transport->lastRequest();
    $this->assertSame('Bearer token', $request->headers['Authorization']);
    $this->assertSame('value', $request->headers['X-Custom']);
}

public function test_builds_query_string(): void
{
    $transport = new FakeTransport();
    
    $client = HttpClientBuilder::create()
        ->withTransport($transport)
        ->build();
    
    $client->get('https://api.example.com/search', [
        'query' => ['q' => 'test', 'page' => 1],
    ]);
    
    $request = $transport->lastRequest();
    $this->assertSame(['q' => 'test', 'page' => 1], $request->query);
}
```

## Integration Tests

For integration tests against real APIs:

```php
class ApiIntegrationTest extends TestCase
{
    private HttpClient $client;
    
    protected function setUp(): void
    {
        if (!getenv('RUN_INTEGRATION_TESTS')) {
            $this->markTestSkipped('Integration tests skipped');
        }
        
        $this->client = HttpClientBuilder::create('https://httpbin.org')
            ->timeout(30)
            ->build();
    }
    
    public function test_real_get_request(): void
    {
        $response = $this->client->get('/json');
        
        $this->assertSame(200, $response->statusCode);
        $this->assertIsArray($response->body);
    }
    
    public function test_real_post_request(): void
    {
        $response = $this->client->post('/post', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode(['test' => true]),
        ]);
        
        $this->assertSame(200, $response->statusCode);
    }
}
```

Run integration tests:

```bash
RUN_INTEGRATION_TESTS=1 ./vendor/bin/phpunit --testsuite=Integration
```

## Best Practices

1. **Always use FakeTransport in unit tests** - Don't make real HTTP requests
2. **Test error scenarios** - 4xx, 5xx responses, connection failures
3. **Verify request details** - Check headers, body, query params
4. **Use integration tests sparingly** - Only for critical API flows
5. **Clear transport between tests** - Call `clearRequests()` if reusing
