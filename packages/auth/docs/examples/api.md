# REST API Example

JWT-based stateless authentication for APIs.

## Overview

This example demonstrates:
- JWT token issuance on login
- Token refresh functionality
- Token revocation (logout)
- API key authentication for services
- Protected API endpoints
- Rate limiting considerations
- Proper error responses

## Project Structure

```
app/
├── Controllers/
│   └── Api/
│       ├── AuthController.php
│       ├── UserController.php
│       └── PostController.php
├── Models/
│   └── User.php
config/
├── auth.php
├── jwt.php
routes/
└── api.php
```

## Configuration

### config/auth.php

```php
<?php

return [
    'default' => 'api',
    
    'guards' => [
        'api' => [
            'driver' => 'jwt',
            'provider' => 'users',
        ],
    ],
    
    'providers' => [
        'users' => [
            'driver' => 'model',
            'model' => \App\Models\User::class,
        ],
    ],
];
```

### config/jwt.php

```php
<?php

return [
    // Token settings
    'ttl' => 60,              // Access token: 60 minutes
    'refresh_ttl' => 20160,   // Refresh token: 14 days
    
    // Signing
    'algorithm' => 'HS256',
    'secret' => env('JWT_SECRET'),
    
    // For RS256/ES256, uncomment:
    // 'algorithm' => 'RS256',
    // 'private_key' => env('JWT_PRIVATE_KEY_PATH'),
    // 'public_key' => env('JWT_PUBLIC_KEY_PATH'),
    
    // Claims
    'issuer' => env('APP_URL', 'https://api.example.com'),
    'audience' => env('APP_NAME', 'MyAPI'),
    
    // Blacklist
    'blacklist_enabled' => true,
    'blacklist_grace_period' => 30, // seconds
];
```

## User Model

### app/Models/User.php

```php
<?php

namespace App\Models;

use Lalaz\Data\Model;
use Lalaz\Auth\Contracts\AuthenticatableInterface;

class User extends Model implements AuthenticatableInterface
{
    protected static string $tableName = 'users';
    
    protected array $fillable = [
        'name',
        'email',
        'password',
        'role',
        'api_key',
    ];

    protected array $hidden = [
        'password',
        'api_key',
    ];

    /**
     * Register a new user
     */
    public static function register(array $data): self
    {
        return self::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => password_hash($data['password'], PASSWORD_BCRYPT),
            'role' => 'user',
            'api_key' => bin2hex(random_bytes(32)),
        ]);
    }

    /**
     * Find by email
     */
    public static function findByEmail(string $email): ?self
    {
        return self::findBy(['email' => $email]);
    }

    /**
     * Find by API key
     */
    public static function findByApiKey(string $apiKey): ?self
    {
        return self::findBy(['api_key' => $apiKey]);
    }

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
        return $this->password;
    }

    public function getRememberToken(): ?string
    {
        return $this->remember_token;
    }

    public function setRememberToken(?string $value): void
    {
        $this->remember_token = $value;
    }

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }

    // ========================================
    // Authorization Methods (optional)
    // ========================================

    public function getRoles(): array
    {
        return [$this->role];
    }

    public function getPermissions(): array
    {
        $permissions = [
            'admin' => ['*'],
            'user' => ['posts.view', 'posts.create', 'posts.edit', 'comments.*'],
        ];
        
        return $permissions[$this->role] ?? [];
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function hasPermission(string $permission): bool
    {
        $permissions = $this->getPermissions();
        return in_array('*', $permissions) || in_array($permission, $permissions);
    }

    /**
     * Convert to array for API responses
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'created_at' => $this->created_at,
        ];
    }
}
```

## Controllers

### app/Controllers/Api/AuthController.php

```php
<?php

namespace App\Controllers\Api;

use App\Models\User;
use Lalaz\Auth\Guards\JwtGuard;
use Lalaz\Auth\Jwt\JwtEncoder;
use function Lalaz\Auth\Helpers\auth;
use function Lalaz\Auth\Helpers\user;

class AuthController
{
    private JwtEncoder $jwt;

    public function __construct()
    {
        $this->jwt = new JwtEncoder(config('jwt'));
    }

    /**
     * POST /api/auth/register
     */
    public function register($request, $response)
    {
        $data = $request->json();
        
        // Validate
        $errors = $this->validateRegistration($data);
        if (!empty($errors)) {
            return $response->json([
                'error' => 'Validation failed',
                'details' => $errors,
            ], 422);
        }
        
        // Check email exists
        if (User::findByEmail($data['email'])) {
            return $response->json([
                'error' => 'Email already registered',
            ], 409);
        }
        
        // Create user
        $user = User::register($data);
        
        // Generate tokens
        $tokens = $this->generateTokens($user);
        
        return $response->json([
            'message' => 'Registration successful',
            'user' => $user->toArray(),
            'tokens' => $tokens,
        ], 201);
    }

    /**
     * POST /api/auth/login
     */
    public function login($request, $response)
    {
        $data = $request->json();
        
        if (empty($data['email']) || empty($data['password'])) {
            return $response->json([
                'error' => 'Email and password are required',
            ], 400);
        }
        
        // Attempt authentication
        $user = auth()->attempt([
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
        
        if (!$user) {
            return $response->json([
                'error' => 'Invalid credentials',
            ], 401);
        }
        
        // Generate tokens
        $tokens = $this->generateTokens($user);
        
        return $response->json([
            'message' => 'Login successful',
            'user' => $user->toArray(),
            'tokens' => $tokens,
        ]);
    }

    /**
     * POST /api/auth/refresh
     */
    public function refresh($request, $response)
    {
        $data = $request->json();
        
        if (empty($data['refresh_token'])) {
            return $response->json([
                'error' => 'Refresh token is required',
            ], 400);
        }
        
        try {
            // Decode refresh token
            $payload = $this->jwt->decode($data['refresh_token']);
            
            // Verify it's a refresh token
            if (($payload['type'] ?? '') !== 'refresh') {
                throw new \Exception('Invalid token type');
            }
            
            // Get user
            $user = User::find($payload['sub']);
            if (!$user) {
                throw new \Exception('User not found');
            }
            
            // Blacklist old refresh token
            $this->jwt->blacklist($data['refresh_token']);
            
            // Generate new tokens
            $tokens = $this->generateTokens($user);
            
            return $response->json([
                'message' => 'Tokens refreshed',
                'tokens' => $tokens,
            ]);
            
        } catch (\Exception $e) {
            return $response->json([
                'error' => 'Invalid refresh token',
            ], 401);
        }
    }

    /**
     * POST /api/auth/logout
     */
    public function logout($request, $response)
    {
        $token = $this->extractToken($request);
        
        if ($token) {
            $this->jwt->blacklist($token);
        }
        
        auth()->logout();
        
        return $response->json([
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * GET /api/auth/me
     */
    public function me($request, $response)
    {
        $user = user();
        
        if (!$user) {
            return $response->json([
                'error' => 'Not authenticated',
            ], 401);
        }
        
        return $response->json([
            'user' => $user->toArray(),
        ]);
    }

    /**
     * Generate access and refresh tokens
     */
    private function generateTokens($user): array
    {
        $now = time();
        $config = config('jwt');
        
        // Access token
        $accessPayload = [
            'sub' => $user->getAuthIdentifier(),
            'email' => $user->email,
            'role' => $user->role,
            'type' => 'access',
            'iat' => $now,
            'exp' => $now + ($config['ttl'] * 60),
        ];
        
        // Refresh token
        $refreshPayload = [
            'sub' => $user->getAuthIdentifier(),
            'type' => 'refresh',
            'iat' => $now,
            'exp' => $now + ($config['refresh_ttl'] * 60),
        ];
        
        return [
            'access_token' => $this->jwt->encode($accessPayload),
            'refresh_token' => $this->jwt->encode($refreshPayload),
            'token_type' => 'Bearer',
            'expires_in' => $config['ttl'] * 60,
        ];
    }

    /**
     * Extract token from request
     */
    private function extractToken($request): ?string
    {
        $header = $request->header('Authorization');
        
        if ($header && preg_match('/Bearer\s+(.+)/i', $header, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    /**
     * Validate registration data
     */
    private function validateRegistration(array $data): array
    {
        $errors = [];
        
        if (empty($data['name'])) {
            $errors['name'] = 'Name is required';
        }
        
        if (empty($data['email'])) {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        }
        
        if (empty($data['password'])) {
            $errors['password'] = 'Password is required';
        } elseif (strlen($data['password']) < 8) {
            $errors['password'] = 'Password must be at least 8 characters';
        }
        
        return $errors;
    }
}
```

### app/Controllers/Api/UserController.php

```php
<?php

namespace App\Controllers\Api;

use App\Models\User;
use function Lalaz\Auth\Helpers\user;
use function Lalaz\Auth\Helpers\auth_context;

class UserController
{
    /**
     * GET /api/users
     * Admin only
     */
    public function index($request, $response)
    {
        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 20);
        
        $users = User::query()
            ->orderBy('created_at', 'DESC')
            ->paginate($perPage, $page);
        
        return $response->json([
            'data' => array_map(fn($u) => $u->toArray(), $users['data']),
            'meta' => [
                'current_page' => $users['current_page'],
                'per_page' => $users['per_page'],
                'total' => $users['total'],
                'last_page' => $users['last_page'],
            ],
        ]);
    }

    /**
     * GET /api/users/{id}
     */
    public function show($request, $response, $id)
    {
        $user = User::find($id);
        
        if (!$user) {
            return $response->json([
                'error' => 'User not found',
            ], 404);
        }
        
        // Only admin or self can view details
        $currentUser = user();
        if ($currentUser->id !== $user->id && !auth_context()->hasRole('admin')) {
            return $response->json([
                'error' => 'Forbidden',
            ], 403);
        }
        
        return $response->json([
            'data' => $user->toArray(),
        ]);
    }

    /**
     * PUT /api/users/{id}
     */
    public function update($request, $response, $id)
    {
        $user = User::find($id);
        
        if (!$user) {
            return $response->json([
                'error' => 'User not found',
            ], 404);
        }
        
        // Only admin or self can update
        $currentUser = user();
        if ($currentUser->id !== $user->id && !auth_context()->hasRole('admin')) {
            return $response->json([
                'error' => 'Forbidden',
            ], 403);
        }
        
        $data = $request->json();
        
        // Update allowed fields
        if (isset($data['name'])) {
            $user->name = $data['name'];
        }
        
        // Only admin can change roles
        if (isset($data['role']) && auth_context()->hasRole('admin')) {
            $user->role = $data['role'];
        }
        
        $user->save();
        
        return $response->json([
            'message' => 'User updated',
            'data' => $user->toArray(),
        ]);
    }

    /**
     * DELETE /api/users/{id}
     * Admin only
     */
    public function destroy($request, $response, $id)
    {
        $user = User::find($id);
        
        if (!$user) {
            return $response->json([
                'error' => 'User not found',
            ], 404);
        }
        
        // Prevent self-deletion
        if ($user->id === user()->id) {
            return $response->json([
                'error' => 'Cannot delete yourself',
            ], 400);
        }
        
        $user->delete();
        
        return $response->json([
            'message' => 'User deleted',
        ]);
    }
}
```

### app/Controllers/Api/PostController.php

```php
<?php

namespace App\Controllers\Api;

use App\Models\Post;
use function Lalaz\Auth\Helpers\user;
use function Lalaz\Auth\Helpers\auth_context;

class PostController
{
    /**
     * GET /api/posts
     */
    public function index($request, $response)
    {
        $posts = Post::query()
            ->where('is_published', '=', true)
            ->orderBy('created_at', 'DESC')
            ->limit(20)
            ->get();
        
        return $response->json([
            'data' => array_map(fn($p) => $p->toArray(), $posts),
        ]);
    }

    /**
     * POST /api/posts
     */
    public function store($request, $response)
    {
        $data = $request->json();
        
        // Validate
        if (empty($data['title']) || empty($data['content'])) {
            return $response->json([
                'error' => 'Title and content are required',
            ], 422);
        }
        
        $post = Post::create([
            'user_id' => user()->id,
            'title' => $data['title'],
            'content' => $data['content'],
            'is_published' => $data['is_published'] ?? false,
        ]);
        
        return $response->json([
            'message' => 'Post created',
            'data' => $post->toArray(),
        ], 201);
    }

    /**
     * GET /api/posts/{id}
     */
    public function show($request, $response, $id)
    {
        $post = Post::find($id);
        
        if (!$post) {
            return $response->json([
                'error' => 'Post not found',
            ], 404);
        }
        
        return $response->json([
            'data' => $post->toArray(),
        ]);
    }

    /**
     * PUT /api/posts/{id}
     */
    public function update($request, $response, $id)
    {
        $post = Post::find($id);
        
        if (!$post) {
            return $response->json([
                'error' => 'Post not found',
            ], 404);
        }
        
        // Check ownership or admin
        if ($post->user_id !== user()->id && !auth_context()->hasRole('admin')) {
            return $response->json([
                'error' => 'Forbidden',
            ], 403);
        }
        
        $data = $request->json();
        
        if (isset($data['title'])) {
            $post->title = $data['title'];
        }
        if (isset($data['content'])) {
            $post->content = $data['content'];
        }
        if (isset($data['is_published'])) {
            $post->is_published = $data['is_published'];
        }
        
        $post->save();
        
        return $response->json([
            'message' => 'Post updated',
            'data' => $post->toArray(),
        ]);
    }

    /**
     * DELETE /api/posts/{id}
     */
    public function destroy($request, $response, $id)
    {
        $post = Post::find($id);
        
        if (!$post) {
            return $response->json([
                'error' => 'Post not found',
            ], 404);
        }
        
        // Check ownership or admin
        if ($post->user_id !== user()->id && !auth_context()->hasRole('admin')) {
            return $response->json([
                'error' => 'Forbidden',
            ], 403);
        }
        
        $post->delete();
        
        return $response->json([
            'message' => 'Post deleted',
        ]);
    }
}
```

## Routes

### routes/api.php

```php
<?php

use App\Controllers\Api\AuthController;
use App\Controllers\Api\UserController;
use App\Controllers\Api\PostController;
use Lalaz\Auth\Middlewares\AuthenticationMiddleware;
use Lalaz\Auth\Middlewares\AuthorizationMiddleware;
use Lalaz\Auth\Middlewares\PermissionMiddleware;

// API prefix
$router->group('/api', function ($router) {
    
    // Public auth routes
    $router->post('/auth/register', [AuthController::class, 'register']);
    $router->post('/auth/login', [AuthController::class, 'login']);
    $router->post('/auth/refresh', [AuthController::class, 'refresh']);
    
    // Protected auth routes
    $router->group('/auth', function ($router) {
        $router->post('/logout', [AuthController::class, 'logout']);
        $router->get('/me', [AuthController::class, 'me']);
    })->middleware(AuthenticationMiddleware::api());
    
    // Protected resource routes
    $router->group('/', function ($router) {
        
        // Posts - all authenticated users
        $router->get('/posts', [PostController::class, 'index']);
        $router->get('/posts/{id}', [PostController::class, 'show']);
        
        // Posts - need create permission
        $router->post('/posts', [PostController::class, 'store'])
            ->middleware(PermissionMiddleware::require('posts.create'));
        
        // Posts - need edit permission
        $router->put('/posts/{id}', [PostController::class, 'update'])
            ->middleware(PermissionMiddleware::require('posts.edit'));
        
        // Posts - need delete permission (admin only)
        $router->delete('/posts/{id}', [PostController::class, 'destroy'])
            ->middleware(AuthorizationMiddleware::requireRoles('admin'));
        
        // Users - admin only
        $router->group('/users', function ($router) {
            $router->get('/', [UserController::class, 'index']);
            $router->get('/{id}', [UserController::class, 'show']);
            $router->put('/{id}', [UserController::class, 'update']);
            $router->delete('/{id}', [UserController::class, 'destroy']);
        })->middleware(AuthorizationMiddleware::requireRoles('admin'));
        
    })->middleware(AuthenticationMiddleware::api());
    
});
```

## API Usage Examples

### Register

```bash
curl -X POST http://localhost/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123"
  }'
```

**Response:**
```json
{
  "message": "Registration successful",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "role": "user",
    "created_at": "2024-01-15T10:30:00Z"
  },
  "tokens": {
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "token_type": "Bearer",
    "expires_in": 3600
  }
}
```

### Login

```bash
curl -X POST http://localhost/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com",
    "password": "password123"
  }'
```

### Access Protected Resource

```bash
curl http://localhost/api/auth/me \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
```

### Refresh Token

```bash
curl -X POST http://localhost/api/auth/refresh \
  -H "Content-Type: application/json" \
  -d '{
    "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
  }'
```

### Create Post

```bash
curl -X POST http://localhost/api/posts \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..." \
  -H "Content-Type: application/json" \
  -d '{
    "title": "My First Post",
    "content": "Hello, World!",
    "is_published": true
  }'
```

### Logout

```bash
curl -X POST http://localhost/api/auth/logout \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
```

## Error Responses

All error responses follow this format:

```json
{
  "error": "Error message",
  "details": {
    "field": "Validation error message"
  }
}
```

| Status | Meaning |
|--------|---------|
| 400 | Bad Request - Invalid input |
| 401 | Unauthorized - Not authenticated |
| 403 | Forbidden - Not authorized |
| 404 | Not Found - Resource doesn't exist |
| 409 | Conflict - Resource already exists |
| 422 | Unprocessable - Validation failed |
| 500 | Server Error |

## Testing

### tests/Api/AuthTest.php

```php
<?php

namespace Tests\Api;

use App\Models\User;
use PHPUnit\Framework\TestCase;

class AuthTest extends TestCase
{
    public function test_can_register(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);
        
        $response->assertStatus(201);
        $response->assertJsonStructure([
            'user' => ['id', 'name', 'email'],
            'tokens' => ['access_token', 'refresh_token'],
        ]);
    }

    public function test_can_login(): void
    {
        $user = User::register([
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);
        
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);
        
        $response->assertStatus(200);
        $response->assertJsonStructure(['tokens' => ['access_token']]);
    }

    public function test_can_access_protected_route_with_token(): void
    {
        $user = User::register([
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);
        
        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);
        
        $token = $loginResponse->json('tokens.access_token');
        
        $response = $this->getJson('/api/auth/me', [
            'Authorization' => "Bearer {$token}",
        ]);
        
        $response->assertStatus(200);
        $response->assertJson(['user' => ['email' => 'test@example.com']]);
    }

    public function test_cannot_access_protected_route_without_token(): void
    {
        $response = $this->getJson('/api/auth/me');
        
        $response->assertStatus(401);
    }

    public function test_can_refresh_token(): void
    {
        $user = User::register([
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);
        
        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);
        
        $refreshToken = $loginResponse->json('tokens.refresh_token');
        
        $response = $this->postJson('/api/auth/refresh', [
            'refresh_token' => $refreshToken,
        ]);
        
        $response->assertStatus(200);
        $response->assertJsonStructure(['tokens' => ['access_token']]);
    }

    public function test_logout_blacklists_token(): void
    {
        $user = User::register([
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);
        
        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);
        
        $token = $loginResponse->json('tokens.access_token');
        
        // Logout
        $this->postJson('/api/auth/logout', [], [
            'Authorization' => "Bearer {$token}",
        ]);
        
        // Try to use the same token
        $response = $this->getJson('/api/auth/me', [
            'Authorization' => "Bearer {$token}",
        ]);
        
        $response->assertStatus(401);
    }
}
```

## Security Best Practices

1. **Use HTTPS** - Always serve API over TLS
2. **Short Token TTL** - Access tokens expire in 60 minutes
3. **Secure Secrets** - Store JWT_SECRET in environment
4. **Blacklist Tokens** - Enable token blacklisting on logout
5. **Rate Limiting** - Limit login attempts
6. **Input Validation** - Validate all input data
7. **Error Handling** - Don't leak sensitive information

## Next Steps

- [Multi-Guard Setup](./multi-guard.md) - Multiple auth guards
- [JWT Documentation](../jwt/index.md) - JWT configuration
- [Token Blacklisting](../jwt/blacklist.md) - Revoke tokens
