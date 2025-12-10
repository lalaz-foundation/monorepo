# Error Handling Examples

Handling errors and failures with Wire.

## Basic Error Handling

```php
use Lalaz\Wire\HttpClient\HttpClientBuilder;
use Lalaz\Wire\HttpClient\HttpException;

$client = HttpClientBuilder::create('https://api.example.com')->build();

try {
    $response = $client->get('/users/123');
    
    if ($response->statusCode === 200) {
        return $response->body;
    }
    
    if ($response->statusCode === 404) {
        throw new NotFoundException('User not found');
    }
    
    throw new ApiException("Error: {$response->statusCode}");
    
} catch (HttpException $e) {
    // Connection error, timeout, etc.
    throw new ServiceUnavailableException($e->getMessage());
}
```

## Status Code Handler

```php
final class ApiResponse
{
    public static function handle(HttpResponse $response): array
    {
        return match (true) {
            $response->statusCode >= 200 && $response->statusCode < 300 
                => $response->body ?? [],
            
            $response->statusCode === 400 
                => throw new BadRequestException(
                    $response->body['message'] ?? 'Bad request'
                ),
            
            $response->statusCode === 401 
                => throw new UnauthorizedException('Invalid credentials'),
            
            $response->statusCode === 403 
                => throw new ForbiddenException('Access denied'),
            
            $response->statusCode === 404 
                => throw new NotFoundException('Resource not found'),
            
            $response->statusCode === 422 
                => throw new ValidationException(
                    $response->body['errors'] ?? []
                ),
            
            $response->statusCode === 429 
                => throw new RateLimitException(
                    'Rate limit exceeded',
                    (int) ($response->headers['Retry-After'] ?? 60)
                ),
            
            $response->statusCode >= 500 
                => throw new ServerException(
                    $response->body['message'] ?? 'Server error'
                ),
            
            default => throw new ApiException(
                "Unexpected status: {$response->statusCode}"
            ),
        };
    }
}

// Usage
$response = $client->get('/users');
$users = ApiResponse::handle($response);
```

## Retry on Failure

```php
final class RetryableClient
{
    public function __construct(
        private HttpClient $client,
        private int $maxRetries = 3,
        private int $delayMs = 100
    ) {}

    public function get(string $endpoint, array $options = []): HttpResponse
    {
        return $this->withRetry(fn() => $this->client->get($endpoint, $options));
    }

    public function post(string $endpoint, array $options = []): HttpResponse
    {
        return $this->withRetry(fn() => $this->client->post($endpoint, $options));
    }

    private function withRetry(callable $request): HttpResponse
    {
        $lastException = null;
        
        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                $response = $request();
                
                // Don't retry client errors (4xx)
                if ($response->statusCode < 500) {
                    return $response;
                }
                
                // Retry server errors (5xx)
                if ($attempt < $this->maxRetries) {
                    $this->sleep($attempt);
                    continue;
                }
                
                return $response;
                
            } catch (HttpException $e) {
                $lastException = $e;
                
                if ($attempt < $this->maxRetries) {
                    $this->sleep($attempt);
                    continue;
                }
            }
        }
        
        throw $lastException ?? new HttpException('Max retries exceeded');
    }

    private function sleep(int $attempt): void
    {
        // Exponential backoff with jitter
        $delay = $this->delayMs * (2 ** ($attempt - 1));
        $jitter = random_int(0, $delay / 2);
        usleep(($delay + $jitter) * 1000);
    }
}

// Usage
$retryable = new RetryableClient($client, maxRetries: 3);
$response = $retryable->get('/unstable-endpoint');
```

## Circuit Breaker Pattern

```php
final class CircuitBreaker
{
    private int $failures = 0;
    private ?int $openedAt = null;
    
    public function __construct(
        private HttpClient $client,
        private int $threshold = 5,
        private int $resetTimeout = 30
    ) {}

    public function request(string $method, string $endpoint, array $options = []): HttpResponse
    {
        if ($this->isOpen()) {
            throw new CircuitOpenException('Circuit breaker is open');
        }
        
        try {
            $response = $this->client->request($method, $endpoint, $options);
            
            if ($response->statusCode >= 500) {
                $this->recordFailure();
            } else {
                $this->recordSuccess();
            }
            
            return $response;
            
        } catch (HttpException $e) {
            $this->recordFailure();
            throw $e;
        }
    }

    private function isOpen(): bool
    {
        if ($this->openedAt === null) {
            return false;
        }
        
        // Check if reset timeout has passed
        if (time() - $this->openedAt >= $this->resetTimeout) {
            $this->openedAt = null;
            $this->failures = 0;
            return false;
        }
        
        return true;
    }

    private function recordFailure(): void
    {
        $this->failures++;
        
        if ($this->failures >= $this->threshold) {
            $this->openedAt = time();
        }
    }

    private function recordSuccess(): void
    {
        $this->failures = 0;
        $this->openedAt = null;
    }
}

class CircuitOpenException extends \Exception {}
```

## Logging Errors

```php
final class LoggingClient
{
    public function __construct(
        private HttpClient $client,
        private LoggerInterface $logger
    ) {}

    public function request(string $method, string $endpoint, array $options = []): HttpResponse
    {
        $startTime = microtime(true);
        
        try {
            $response = $this->client->request($method, $endpoint, $options);
            
            $this->logger->info('HTTP request completed', [
                'method' => $method,
                'endpoint' => $endpoint,
                'status' => $response->statusCode,
                'duration_ms' => $response->durationMs,
            ]);
            
            if ($response->statusCode >= 400) {
                $this->logger->warning('HTTP error response', [
                    'method' => $method,
                    'endpoint' => $endpoint,
                    'status' => $response->statusCode,
                    'body' => $response->body,
                ]);
            }
            
            return $response;
            
        } catch (HttpException $e) {
            $this->logger->error('HTTP request failed', [
                'method' => $method,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
                'duration_ms' => (microtime(true) - $startTime) * 1000,
            ]);
            
            throw $e;
        }
    }
}
```

## Graceful Degradation

```php
final class ResilientApiClient
{
    public function __construct(
        private HttpClient $client,
        private CacheInterface $cache
    ) {}

    public function getData(string $key): array
    {
        try {
            $response = $this->client->get("/api/data/{$key}");
            
            if ($response->statusCode === 200) {
                // Update cache with fresh data
                $this->cache->set("data:{$key}", $response->body, 3600);
                return $response->body;
            }
            
        } catch (HttpException $e) {
            // Log but don't throw
            error_log("API request failed: {$e->getMessage()}");
        }
        
        // Fall back to cached data
        $cached = $this->cache->get("data:{$key}");
        
        if ($cached !== null) {
            return $cached;
        }
        
        // Return default if nothing available
        return ['status' => 'unavailable'];
    }
}
```

## Custom Exception Classes

```php
// Base exception
class ApiException extends \Exception
{
    public function __construct(
        string $message,
        public readonly ?array $body = null,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}

// Specific exceptions
class BadRequestException extends ApiException {}
class UnauthorizedException extends ApiException {}
class ForbiddenException extends ApiException {}
class NotFoundException extends ApiException {}
class ServerException extends ApiException {}

class ValidationException extends ApiException
{
    public function __construct(
        public readonly array $errors,
        string $message = 'Validation failed'
    ) {
        parent::__construct($message, ['errors' => $errors], 422);
    }
}

class RateLimitException extends ApiException
{
    public function __construct(
        string $message,
        public readonly int $retryAfter
    ) {
        parent::__construct($message, null, 429);
    }
}

// Usage
try {
    $response = $client->post('/users', ['body' => $data]);
    
    if ($response->statusCode === 422) {
        throw new ValidationException($response->body['errors']);
    }
    
} catch (ValidationException $e) {
    foreach ($e->errors as $field => $messages) {
        echo "{$field}: " . implode(', ', $messages) . "\n";
    }
} catch (RateLimitException $e) {
    echo "Please wait {$e->retryAfter} seconds\n";
}
```
