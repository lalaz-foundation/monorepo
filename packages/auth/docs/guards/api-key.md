# API Key Guard

The API Key Guard provides simple authentication for machine-to-machine communication, webhooks, and third-party integrations.

## How It Works

```
1. External system has an API key
        │
        ▼
2. System sends request with API key
        │
        ├─── In header: X-API-Key: abc123
        └─── Or query: ?api_key=abc123
                │
                ▼
3. ApiKeyGuard validates the key
        │
        ▼
4. Provider loads the associated user/client
```

## When to Use

**Good for:**
- Third-party integrations (Stripe webhooks, etc.)
- Server-to-server communication
- Simple internal services
- IoT devices
- Background job authentication

**Not recommended for:**
- User-facing authentication
- Mobile apps (use JWT instead)
- Sensitive operations (use JWT with short expiration)

## Configuration

```php
// config/auth.php
return [
    'guards' => [
        'external' => [
            'driver' => 'api-key',
            'provider' => 'api_clients',
            'header' => 'X-API-Key',     // Optional, default: X-API-Key
            'query' => 'api_key',         // Optional, default: api_key
        ],
    ],
    
    'providers' => [
        'api_clients' => [
            'driver' => 'model',
            'model' => App\Models\ApiClient::class,
        ],
    ],
];
```

## Database Setup

Create a table for API clients:

```php
// Migration
class CreateApiClientsTable extends Migration
{
    public function up(): void
    {
        $this->schema->create('api_clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('api_key', 64)->unique();
            $table->string('api_key_hash', 64);  // Store hash for security
            $table->boolean('is_active')->default(true);
            $table->json('permissions')->nullable();
            $table->json('allowed_ips')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }
}
```

```php
// App\Models\ApiClient
namespace App\Models;

use Lalaz\Data\Model;
use Lalaz\Auth\Contracts\AuthenticatableInterface;

class ApiClient extends Model implements AuthenticatableInterface
{
    protected static string $tableName = 'api_clients';
    
    // ========================================
    // AuthenticatableInterface Implementation
    // ========================================

    public function getAuthIdentifier(): mixed
    {
        return $this->id;
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthPassword(): string
    {
        return '';  // API keys don't use passwords
    }

    public function getRememberToken(): ?string
    {
        return null;  // API clients don't use remember tokens
    }

    public function setRememberToken(?string $value): void
    {
        // Not applicable for API clients
    }

    public function getRememberTokenName(): string
    {
        return '';
    }

    // ========================================
    // Authorization Methods (optional)
    // ========================================

    public function getRoles(): array
    {
        return ['api_client'];
    }

    public function getPermissions(): array
    {
        return json_decode($this->permissions ?? '[]', true);
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getRoles());
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->getPermissions());
    }
}
```

## API Key Provider

Create a custom provider for API key authentication:

```php
namespace App\Providers;

use Lalaz\Auth\Contracts\ApiKeyProviderInterface;
use App\Models\ApiClient;

class ApiKeyProvider implements ApiKeyProviderInterface
{
    /**
     * Retrieve client by API key.
     */
    public function retrieveByApiKey(string $apiKey): ?ApiClient
    {
        // Option 1: Direct lookup (if storing plain key - not recommended)
        // return ApiClient::where('api_key', $apiKey)->first();
        
        // Option 2: Hash lookup (more secure)
        $hash = hash('sha256', $apiKey);
        $client = ApiClient::where('api_key_hash', $hash)->first();
        
        if (!$client || !$client->is_active) {
            return null;
        }
        
        // Update last used timestamp
        $client->last_used_at = date('Y-m-d H:i:s');
        $client->save();
        
        return $client;
    }

    /**
     * Validate the API key.
     */
    public function validateApiKey(string $apiKey): bool
    {
        return $this->retrieveByApiKey($apiKey) !== null;
    }
}
```

## Basic Usage

### Authenticate Request

```php
use Lalaz\Auth\AuthManager;

$auth = resolve(AuthManager::class);
$guard = $auth->guard('external');

// Get the authenticated API client
$client = $guard->user();

if ($guard->check()) {
    echo "Authenticated as: " . $client->name;
}
```

### In Controllers

```php
class WebhookController
{
    public function handle($request, $response)
    {
        // Middleware already verified API key
        $client = user('external');
        
        // Check specific permission
        if (!$client->hasPermission('webhooks.receive')) {
            return $response->json(['error' => 'Forbidden'], 403);
        }

        // Process webhook
        $payload = $request->json();
        
        // ... handle webhook logic
        
        return $response->json(['status' => 'processed']);
    }
}
```

## Generating API Keys

```php
class ApiClientController
{
    public function create($request, $response)
    {
        // Generate a secure API key
        $apiKey = bin2hex(random_bytes(32));  // 64 character hex string
        
        $client = new ApiClient();
        $client->name = $request->input('name');
        $client->api_key = $this->maskKey($apiKey);  // Store masked version for display
        $client->api_key_hash = hash('sha256', $apiKey);  // Store hash for validation
        $client->permissions = json_encode($request->input('permissions', []));
        $client->save();
        
        // Return the full key ONLY ONCE (user must save it)
        return $response->json([
            'id' => $client->id,
            'name' => $client->name,
            'api_key' => $apiKey,  // Only shown once!
            'warning' => 'Save this API key. It will not be shown again.',
        ]);
    }

    private function maskKey(string $key): string
    {
        // Show first 8 and last 4 characters
        return substr($key, 0, 8) . '...' . substr($key, -4);
    }
}
```

## Protecting Routes

```php
use Lalaz\Auth\Middlewares\AuthenticationMiddleware;

// Webhook routes
$router->group('/webhooks', function ($router) {
    $router->post('/stripe', [WebhookController::class, 'stripe']);
    $router->post('/github', [WebhookController::class, 'github']);
})->middleware(AuthenticationMiddleware::apiKey());

// External API
$router->group('/external-api', function ($router) {
    $router->get('/data', [ExternalApiController::class, 'getData']);
    $router->post('/sync', [ExternalApiController::class, 'sync']);
})->middleware(AuthenticationMiddleware::apiKey('external'));
```

## Sending Requests with API Key

### Header (Recommended)

```bash
curl -X POST https://api.example.com/webhooks/data \
  -H "X-API-Key: your-api-key-here" \
  -H "Content-Type: application/json" \
  -d '{"event": "test"}'
```

### Query Parameter

```bash
curl "https://api.example.com/webhooks/data?api_key=your-api-key-here" \
  -X POST \
  -H "Content-Type: application/json" \
  -d '{"event": "test"}'
```

### PHP Client Example

```php
$client = new HttpClient();

$response = $client->post('https://api.example.com/webhooks/data', [
    'headers' => [
        'X-API-Key' => 'your-api-key-here',
        'Content-Type' => 'application/json',
    ],
    'json' => [
        'event' => 'test',
    ],
]);
```

## IP Whitelisting

Add extra security by restricting allowed IPs:

```php
class ApiKeyMiddleware
{
    public function handle($request, $next)
    {
        $client = user('external');
        
        if (!$client) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        // Check IP whitelist
        $allowedIps = json_decode($client->allowed_ips ?? '[]', true);
        
        if (!empty($allowedIps)) {
            $clientIp = $request->ip();
            
            if (!in_array($clientIp, $allowedIps)) {
                return response()->json([
                    'error' => 'IP not allowed',
                ], 403);
            }
        }
        
        return $next($request);
    }
}
```

## Rate Limiting

Implement rate limiting per API key:

```php
class RateLimitMiddleware
{
    private CacheInterface $cache;

    public function handle($request, $next)
    {
        $client = user('external');
        $key = "rate_limit:{$client->id}";
        
        $attempts = (int) $this->cache->get($key, 0);
        $maxAttempts = $client->rate_limit ?? 100;  // Per minute
        
        if ($attempts >= $maxAttempts) {
            return response()->json([
                'error' => 'Rate limit exceeded',
                'retry_after' => 60,
            ], 429)->withHeader('Retry-After', 60);
        }
        
        $this->cache->increment($key);
        $this->cache->expire($key, 60);  // Reset after 1 minute
        
        $response = $next($request);
        
        return $response
            ->withHeader('X-RateLimit-Limit', $maxAttempts)
            ->withHeader('X-RateLimit-Remaining', $maxAttempts - $attempts - 1);
    }
}
```

## Managing API Keys

### List All Keys

```php
public function index($request, $response)
{
    $clients = ApiClient::all();
    
    return $response->json([
        'clients' => array_map(fn($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'api_key' => $c->api_key,  // Masked version
            'is_active' => $c->is_active,
            'last_used_at' => $c->last_used_at,
        ], $clients),
    ]);
}
```

### Revoke a Key

```php
public function revoke($request, $response, $id)
{
    $client = ApiClient::find($id);
    
    if (!$client) {
        return $response->json(['error' => 'Not found'], 404);
    }
    
    $client->is_active = false;
    $client->save();
    
    return $response->json([
        'message' => 'API key revoked',
    ]);
}
```

### Regenerate a Key

```php
public function regenerate($request, $response, $id)
{
    $client = ApiClient::find($id);
    
    if (!$client) {
        return $response->json(['error' => 'Not found'], 404);
    }
    
    // Generate new key
    $newKey = bin2hex(random_bytes(32));
    
    $client->api_key = substr($newKey, 0, 8) . '...' . substr($newKey, -4);
    $client->api_key_hash = hash('sha256', $newKey);
    $client->save();
    
    return $response->json([
        'api_key' => $newKey,
        'warning' => 'Save this API key. It will not be shown again.',
    ]);
}
```

## Security Best Practices

### 1. Always Hash Keys

Never store plain API keys:

```php
// Bad
$client->api_key = $apiKey;

// Good
$client->api_key_hash = hash('sha256', $apiKey);
```

### 2. Use HTTPS

API keys in transit must be encrypted:

```bash
# Bad
curl http://api.example.com/data -H "X-API-Key: secret"

# Good
curl https://api.example.com/data -H "X-API-Key: secret"
```

### 3. Set Expiration Dates

```php
// Add to migration
$table->timestamp('expires_at')->nullable();

// Check in provider
if ($client->expires_at && $client->expires_at < now()) {
    return null;  // Key has expired
}
```

### 4. Log API Key Usage

```php
// In middleware or provider
Log::info('API key used', [
    'client_id' => $client->id,
    'client_name' => $client->name,
    'endpoint' => $request->path(),
    'ip' => $request->ip(),
]);
```

### 5. Scope Permissions

Limit what each API key can do:

```php
$client->permissions = json_encode([
    'webhooks.receive',
    'data.read',
    // NOT 'data.delete' - limited access
]);
```

## Complete Example

### API Client Management

```php
// routes/admin.php
$router->group('/admin/api-clients', function ($router) {
    $router->get('/', [ApiClientController::class, 'index']);
    $router->post('/', [ApiClientController::class, 'create']);
    $router->delete('/{id}', [ApiClientController::class, 'revoke']);
    $router->post('/{id}/regenerate', [ApiClientController::class, 'regenerate']);
})->middleware(AuthenticationMiddleware::web('/admin/login'));
```

```php
// App\Controllers\Admin\ApiClientController
namespace App\Controllers\Admin;

use App\Models\ApiClient;

class ApiClientController
{
    public function index($request, $response)
    {
        $clients = ApiClient::orderBy('created_at', 'desc')->get();
        
        $response->view('admin/api-clients/index', [
            'clients' => $clients,
        ]);
    }

    public function create($request, $response)
    {
        $name = $request->input('name');
        $permissions = $request->input('permissions', []);
        $allowedIps = array_filter(
            explode("\n", $request->input('allowed_ips', ''))
        );

        // Generate secure key
        $apiKey = bin2hex(random_bytes(32));

        $client = new ApiClient();
        $client->name = $name;
        $client->api_key = substr($apiKey, 0, 8) . '...' . substr($apiKey, -4);
        $client->api_key_hash = hash('sha256', $apiKey);
        $client->permissions = json_encode($permissions);
        $client->allowed_ips = json_encode($allowedIps);
        $client->save();

        // Flash the full key for display (one time only)
        session()->flash('new_api_key', $apiKey);
        session()->flash('new_client_name', $name);
        
        $response->redirect('/admin/api-clients');
    }

    public function revoke($request, $response, $id)
    {
        $client = ApiClient::find($id);
        
        if ($client) {
            $client->is_active = false;
            $client->save();
            session()->flash('success', "API client '{$client->name}' has been revoked.");
        }
        
        $response->redirect('/admin/api-clients');
    }

    public function regenerate($request, $response, $id)
    {
        $client = ApiClient::find($id);
        
        if ($client) {
            $apiKey = bin2hex(random_bytes(32));
            $client->api_key = substr($apiKey, 0, 8) . '...' . substr($apiKey, -4);
            $client->api_key_hash = hash('sha256', $apiKey);
            $client->save();
            
            session()->flash('new_api_key', $apiKey);
            session()->flash('new_client_name', $client->name);
        }
        
        $response->redirect('/admin/api-clients');
    }
}
```

## Testing

```php
use PHPUnit\Framework\TestCase;

class ApiKeyGuardTest extends TestCase
{
    public function test_valid_api_key_authenticates(): void
    {
        $apiKey = bin2hex(random_bytes(32));
        $hash = hash('sha256', $apiKey);
        
        // Create test client
        $client = ApiClient::create([
            'name' => 'Test Client',
            'api_key_hash' => $hash,
            'is_active' => true,
        ]);
        
        // Simulate request with API key
        $_SERVER['HTTP_X_API_KEY'] = $apiKey;
        
        $guard = resolve(AuthManager::class)->guard('external');
        
        $this->assertTrue($guard->check());
        $this->assertEquals($client->id, $guard->id());
    }

    public function test_invalid_api_key_fails(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = 'invalid-key';
        
        $guard = resolve(AuthManager::class)->guard('external');
        
        $this->assertFalse($guard->check());
        $this->assertNull($guard->user());
    }

    public function test_inactive_client_fails(): void
    {
        $apiKey = bin2hex(random_bytes(32));
        
        ApiClient::create([
            'name' => 'Inactive Client',
            'api_key_hash' => hash('sha256', $apiKey),
            'is_active' => false,  // Deactivated
        ]);
        
        $_SERVER['HTTP_X_API_KEY'] = $apiKey;
        
        $guard = resolve(AuthManager::class)->guard('external');
        
        $this->assertFalse($guard->check());
    }
}
```

## Troubleshooting

### Key Not Being Recognized

**Problem:** Valid API key returns 401.

**Solutions:**
1. Check header name matches config (`X-API-Key`)
2. Verify key hash matches stored hash
3. Check if client is active
4. Verify provider is configured correctly

```php
// Debug
$key = $_SERVER['HTTP_X_API_KEY'] ?? null;
echo "Received key: " . ($key ? substr($key, 0, 8) . '...' : 'none');

$hash = hash('sha256', $key);
echo "Hash: " . $hash;

$client = ApiClient::where('api_key_hash', $hash)->first();
var_dump($client);
```

### Header Not Being Received

**Problem:** `$_SERVER['HTTP_X_API_KEY']` is empty.

**Solutions:**
1. Check if web server is stripping custom headers
2. Apache: Add to .htaccess:
   ```apache
   SetEnvIf X-API-Key "(.*)" HTTP_X_API_KEY=$1
   ```
3. Nginx: Add to config:
   ```nginx
   fastcgi_pass_header X-API-Key;
   ```

## Next Steps

- Learn about [User Providers](../providers/index.md)
- Add [Role-Based Authorization](../authorization.md)
- Implement [Rate Limiting](../middlewares/index.md)
- See complete [API Example](../examples/api.md)
