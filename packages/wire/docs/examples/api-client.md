# Building an API Client

Create a dedicated client for a specific API.

## Basic API Client

```php
use Lalaz\Wire\HttpClient\HttpClient;
use Lalaz\Wire\HttpClient\HttpClientBuilder;
use Lalaz\Wire\HttpClient\HttpException;

final class GitHubClient
{
    private HttpClient $http;

    public function __construct(string $token)
    {
        $this->http = HttpClientBuilder::create('https://api.github.com')
            ->baseHeaders([
                'Authorization' => "Bearer {$token}",
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'MyApp/1.0',
            ])
            ->timeout(30)
            ->build();
    }

    public function getUser(string $username): ?array
    {
        $response = $this->http->get("/users/{$username}");
        
        return $response->statusCode === 200 ? $response->body : null;
    }

    public function getUserRepos(string $username): array
    {
        $response = $this->http->get("/users/{$username}/repos", [
            'query' => ['sort' => 'updated', 'per_page' => 100],
        ]);
        
        return $response->statusCode === 200 ? $response->body : [];
    }

    public function createIssue(string $owner, string $repo, array $data): ?array
    {
        $response = $this->http->post("/repos/{$owner}/{$repo}/issues", [
            'body' => json_encode($data),
        ]);
        
        return $response->statusCode === 201 ? $response->body : null;
    }
}

// Usage
$github = new GitHubClient('ghp_your_token');
$user = $github->getUser('octocat');
$repos = $github->getUserRepos('octocat');
```

## With Error Handling

```php
final class ApiClient
{
    private HttpClient $http;

    public function __construct(string $baseUrl, string $apiKey)
    {
        $this->http = HttpClientBuilder::create($baseUrl)
            ->baseHeaders([
                'X-API-Key' => $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->timeout(30)
            ->build();
    }

    public function get(string $endpoint, array $params = []): array
    {
        return $this->request('GET', $endpoint, ['query' => $params]);
    }

    public function post(string $endpoint, array $data): array
    {
        return $this->request('POST', $endpoint, [
            'body' => json_encode($data),
        ]);
    }

    public function put(string $endpoint, array $data): array
    {
        return $this->request('PUT', $endpoint, [
            'body' => json_encode($data),
        ]);
    }

    public function delete(string $endpoint): bool
    {
        $response = $this->http->delete($endpoint);
        return $response->statusCode === 204;
    }

    private function request(string $method, string $endpoint, array $options): array
    {
        try {
            $response = $this->http->request($method, $endpoint, $options);
            
            if ($response->statusCode >= 400) {
                throw new ApiException(
                    $response->body['message'] ?? 'API error',
                    $response->statusCode
                );
            }
            
            return $response->body ?? [];
            
        } catch (HttpException $e) {
            throw new ApiException(
                "Connection error: {$e->getMessage()}",
                0,
                $e
            );
        }
    }
}

class ApiException extends \Exception {}
```

## Paginated Results

```php
final class PaginatedApiClient
{
    private HttpClient $http;

    public function __construct(HttpClient $http)
    {
        $this->http = $http;
    }

    /**
     * @return \Generator<array>
     */
    public function getAllUsers(): \Generator
    {
        $page = 1;
        
        do {
            $response = $this->http->get('/users', [
                'query' => ['page' => $page, 'per_page' => 100],
            ]);
            
            if ($response->statusCode !== 200) {
                break;
            }
            
            foreach ($response->body['data'] as $user) {
                yield $user;
            }
            
            $hasMore = $response->body['meta']['has_more'] ?? false;
            $page++;
            
        } while ($hasMore);
    }
}

// Usage
$client = new PaginatedApiClient($http);

foreach ($client->getAllUsers() as $user) {
    echo "{$user['name']}\n";
}
```

## Resource-Based Client

```php
final class RestClient
{
    private HttpClient $http;

    public function __construct(string $baseUrl, string $token)
    {
        $this->http = HttpClientBuilder::create($baseUrl)
            ->baseHeaders([
                'Authorization' => "Bearer {$token}",
                'Content-Type' => 'application/json',
            ])
            ->build();
    }

    public function users(): ResourceClient
    {
        return new ResourceClient($this->http, '/users');
    }

    public function posts(): ResourceClient
    {
        return new ResourceClient($this->http, '/posts');
    }

    public function comments(): ResourceClient
    {
        return new ResourceClient($this->http, '/comments');
    }
}

final class ResourceClient
{
    public function __construct(
        private HttpClient $http,
        private string $path
    ) {}

    public function all(array $params = []): array
    {
        $response = $this->http->get($this->path, ['query' => $params]);
        return $response->body ?? [];
    }

    public function find(int|string $id): ?array
    {
        $response = $this->http->get("{$this->path}/{$id}");
        return $response->statusCode === 200 ? $response->body : null;
    }

    public function create(array $data): array
    {
        $response = $this->http->post($this->path, [
            'body' => json_encode($data),
        ]);
        return $response->body;
    }

    public function update(int|string $id, array $data): array
    {
        $response = $this->http->put("{$this->path}/{$id}", [
            'body' => json_encode($data),
        ]);
        return $response->body;
    }

    public function delete(int|string $id): bool
    {
        $response = $this->http->delete("{$this->path}/{$id}");
        return $response->statusCode === 204;
    }
}

// Usage
$api = new RestClient('https://api.example.com', $token);

// Get all users
$users = $api->users()->all(['active' => true]);

// Get single user
$user = $api->users()->find(123);

// Create user
$newUser = $api->users()->create([
    'name' => 'John',
    'email' => 'john@example.com',
]);

// Update user
$updated = $api->users()->update(123, ['name' => 'Jane']);

// Delete user
$api->users()->delete(123);
```

## With Caching

```php
final class CachedApiClient
{
    public function __construct(
        private HttpClient $http,
        private CacheInterface $cache,
        private int $ttl = 300
    ) {}

    public function get(string $endpoint, array $params = []): array
    {
        $cacheKey = $this->buildCacheKey($endpoint, $params);
        
        if ($cached = $this->cache->get($cacheKey)) {
            return $cached;
        }
        
        $response = $this->http->get($endpoint, ['query' => $params]);
        
        if ($response->statusCode === 200) {
            $this->cache->set($cacheKey, $response->body, $this->ttl);
        }
        
        return $response->body ?? [];
    }

    private function buildCacheKey(string $endpoint, array $params): string
    {
        return 'api:' . md5($endpoint . serialize($params));
    }
}
```
