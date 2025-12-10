# JWT Guard

The JWT (JSON Web Token) Guard provides stateless authentication, perfect for APIs and mobile applications.

## How It Works

```
1. User sends login request (email + password)
        │
        ▼
2. Server validates credentials
        │
        ▼
3. Server generates JWT with user data
        │
        ├─── Header: algorithm info
        ├─── Payload: user ID, expiration, claims
        └─── Signature: cryptographic signature
                │
                ▼
4. Client stores JWT (localStorage, memory)
        │
        ▼
5. Client sends JWT in Authorization header
        │
        ▼
6. JwtGuard validates token and loads user
```

## JWT Structure

A JWT has three parts separated by dots:

```
eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjMiLCJleHAiOjE2ODk4NzY1NDN9.signature
└─────────────────────────────────────┘ └──────────────────────────────────┘ └────────┘
           Header (Base64)                      Payload (Base64)              Signature
```

**Header:**
```json
{
  "alg": "HS256",
  "typ": "JWT"
}
```

**Payload:**
```json
{
  "sub": "123",         // Subject (user ID)
  "exp": 1689876543,    // Expiration time
  "iat": 1689790143,    // Issued at
  "iss": "my-app"       // Issuer
}
```

## Configuration

```php
// config/auth.php
return [
    'guards' => [
        'api' => [
            'driver' => 'jwt',
            'provider' => 'users',
        ],
    ],
    
    'providers' => [
        'users' => [
            'driver' => 'model',
            'model' => App\Models\User::class,
        ],
    ],
    
    'jwt' => [
        'secret' => env('JWT_SECRET'),
        'algorithm' => 'HS256',
        'ttl' => 3600,           // Token lifetime in seconds (1 hour)
        'refresh_ttl' => 86400,  // Refresh window (24 hours)
    ],
];
```

```ini
# .env
JWT_SECRET=your-256-bit-secret-key-here
```

## Basic Usage

### Issue Token (Login)

```php
use Lalaz\Auth\AuthManager;
use Lalaz\Auth\Jwt\JwtEncoder;

$auth = resolve(AuthManager::class);
$encoder = resolve(JwtEncoder::class);

// Validate credentials
$user = $auth->guard('api')->attempt([
    'email' => 'user@example.com',
    'password' => 'secret123',
]);

if ($user) {
    // Generate token
    $token = $encoder->encode([
        'sub' => $user->getAuthIdentifier(),
        'email' => $user->email,
    ]);
    
    return [
        'token' => $token,
        'token_type' => 'Bearer',
        'expires_in' => 3600,
    ];
}
```

### Authenticate Request

The client sends the token in the Authorization header:

```http
GET /api/profile HTTP/1.1
Host: example.com
Authorization: Bearer eyJhbGciOiJIUzI1NiIs...
```

JwtGuard automatically extracts and validates this:

```php
use Lalaz\Auth\AuthManager;

$auth = resolve(AuthManager::class);
$guard = $auth->guard('api');

// Get authenticated user (from token)
$user = $guard->user();

// Check if authenticated
if ($guard->check()) {
    echo "Hello, " . $user->name;
}
```

### Logout (Invalidate Token)

```php
// Blacklist the current token
$auth->guard('api')->logout();
```

## API Authentication Example

### Routes

```php
// routes/api.php

// Public routes
$router->post('/api/auth/login', [ApiAuthController::class, 'login']);
$router->post('/api/auth/register', [ApiAuthController::class, 'register']);

// Protected routes
$router->group('/api', function ($router) {
    $router->get('/profile', [ProfileController::class, 'show']);
    $router->put('/profile', [ProfileController::class, 'update']);
    $router->post('/auth/logout', [ApiAuthController::class, 'logout']);
    $router->post('/auth/refresh', [ApiAuthController::class, 'refresh']);
})->middleware(AuthenticationMiddleware::jwt());
```

### Auth Controller

```php
<?php

namespace App\Controllers\Api;

use Lalaz\Auth\AuthManager;
use Lalaz\Auth\Jwt\JwtEncoder;

class ApiAuthController
{
    public function __construct(
        private AuthManager $auth,
        private JwtEncoder $encoder
    ) {}

    public function login($request, $response)
    {
        $credentials = [
            'email' => $request->input('email'),
            'password' => $request->input('password'),
        ];

        // Validate input
        if (empty($credentials['email']) || empty($credentials['password'])) {
            return $response->json([
                'error' => 'Email and password are required',
            ], 422);
        }

        // Attempt authentication
        $user = $this->auth->guard('api')->attempt($credentials);

        if (!$user) {
            return $response->json([
                'error' => 'Invalid credentials',
            ], 401);
        }

        // Generate tokens using JwtGuard
        $tokens = $this->auth->guard('api')->attemptWithTokens($credentials);

        if (!$tokens) {
            return $response->json(['error' => 'Invalid credentials'], 401);
        }

        return $response->json([
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'token_type' => $tokens['token_type'],
            'expires_in' => $tokens['expires_in'],
            'user' => [
                'id' => $user->getAuthIdentifier(),
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function logout($request, $response)
    {
        $this->auth->guard('api')->logout();
        
        return $response->json([
            'message' => 'Successfully logged out',
        ]);
    }

    public function refresh($request, $response)
    {
        $refreshToken = $request->input('refresh_token');

        try {
            $payload = $this->encoder->decode($refreshToken);
            
            if ($payload['type'] !== 'refresh') {
                throw new \Exception('Invalid token type');
            }

            // Generate new access token
            $accessToken = $this->encoder->encode([
                'sub' => $payload['sub'],
                'type' => 'access',
            ]);

            return $response->json([
                'access_token' => $accessToken,
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]);
        } catch (\Exception $e) {
            return $response->json([
                'error' => 'Invalid refresh token',
            ], 401);
        }
    }
}
```

### Profile Controller

```php
<?php

namespace App\Controllers\Api;

class ProfileController
{
    public function show($request, $response)
    {
        $user = user('api');
        
        return $response->json([
            'id' => $user->getAuthIdentifier(),
            'name' => $user->name,
            'email' => $user->email,
            'created_at' => $user->created_at,
        ]);
    }

    public function update($request, $response)
    {
        $user = user('api');
        
        $user->name = $request->input('name', $user->name);
        $user->save();
        
        return $response->json([
            'message' => 'Profile updated',
            'user' => [
                'id' => $user->getAuthIdentifier(),
                'name' => $user->name,
            ],
        ]);
    }
}
```

## JWT Encoder

The `JwtEncoder` class handles token creation and validation.

### Creating Tokens

```php
use Lalaz\Auth\Jwt\JwtEncoder;

$encoder = resolve(JwtEncoder::class);

// Simple token
$token = $encoder->encode([
    'sub' => $userId,
]);

// Token with custom claims
$token = $encoder->encode([
    'sub' => $userId,
    'email' => 'user@example.com',
    'roles' => ['admin', 'editor'],
    'permissions' => ['posts.create', 'posts.edit'],
]);

// Token with custom TTL (time to live)
$token = $encoder->encode(
    payload: ['sub' => $userId],
    ttl: 7200  // 2 hours
);
```

### Decoding Tokens

```php
$payload = $encoder->decode($token);

if ($payload === null) {
    // Token is invalid (expired, bad signature, or malformed)
    // Handle error
} else {
    echo $payload['sub'];    // User ID
    echo $payload['exp'];    // Expiration timestamp
    echo $payload['iat'];    // Issued at timestamp
}
```

### Available Methods

```php
// Encode payload to JWT string
$token = $encoder->encode(array $payload, ?int $expiration = null): string;

// Decode JWT string to payload (returns null if invalid)
$payload = $encoder->decode(string $token): ?array;

// Check if token is valid
$isValid = $encoder->validate(string $token): bool;

// Get payload without validation (for debugging or expired token inspection)
$payload = $encoder->getClaims($token);  // Returns ?array
```

## JWT Signers

Different algorithms for signing tokens:

### HMAC Signers (Symmetric)

Same secret key for signing and verification:

```php
use Lalaz\Auth\Jwt\Signers\HmacSha256Signer;
use Lalaz\Auth\Jwt\Signers\HmacSha384Signer;
use Lalaz\Auth\Jwt\Signers\HmacSha512Signer;

// HS256 - Most common, good for most uses
$signer = new HmacSha256Signer($secret);

// HS384 - More secure
$signer = new HmacSha384Signer($secret);

// HS512 - Most secure HMAC
$signer = new HmacSha512Signer($secret);
```

### RSA Signers (Asymmetric)

Private key signs, public key verifies:

```php
use Lalaz\Auth\Jwt\Signers\RsaSha256Signer;

// For signing (needs private key)
$signer = new RsaSha256Signer(privateKey: $privateKey);

// For verification (needs public key only)
$signer = new RsaSha256Signer(publicKey: $publicKey);

// For both signing and verification
$signer = new RsaSha256Signer(
    privateKey: $privateKey,
    publicKey: $publicKey
);
```

## Token Blacklisting

Invalidate tokens before expiration:

```php
use Lalaz\Auth\Jwt\JwtBlacklist;

$blacklist = resolve(JwtBlacklist::class);

// Blacklist a token
$blacklist->add($token);

// Check if token is blacklisted
if ($blacklist->isBlacklisted($token)) {
    throw new \Exception('Token has been revoked');
}

// The JwtGuard checks the blacklist automatically
$auth->guard('api')->logout();  // Adds current token to blacklist
```

### Blacklist Storage

By default, blacklist uses cache. Configure in your app:

```php
// Using Redis
$blacklist = new BlacklistService(
    cache: $redisCache,
    prefix: 'jwt_blacklist:'
);

// Using file-based cache
$blacklist = new BlacklistService(
    cache: $fileCache,
    prefix: 'jwt_blacklist:'
);
```

## Protecting Routes

### Using Middleware

```php
use Lalaz\Auth\Middlewares\AuthenticationMiddleware;

// Require valid JWT
$router->group('/api', function ($router) {
    // All routes here require authentication
})->middleware(AuthenticationMiddleware::jwt());

// With custom guard name
$router->middleware(AuthenticationMiddleware::jwt('api'));
```

### Custom JWT Middleware

```php
use Lalaz\Auth\Jwt\JwtEncoder;

class JwtMiddleware
{
    public function __construct(
        private JwtEncoder $encoder
    ) {}

    public function handle($request, $next)
    {
        $header = $request->header('Authorization');
        
        if (!$header || !str_starts_with($header, 'Bearer ')) {
            return response()->json(['error' => 'Token required'], 401);
        }

        $token = substr($header, 7);

        try {
            $payload = $this->encoder->decode($token);
            $request->setUserPayload($payload);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        return $next($request);
    }
}
```

## Best Practices

### 1. Use Strong Secrets

Generate a secure secret:

```bash
php -r "echo bin2hex(random_bytes(32));"
# Output: 64 character hex string
```

Or:

```bash
openssl rand -base64 32
```

### 2. Short Access Token Lifetime

```php
// config/auth.php
'jwt' => [
    'ttl' => 900,  // 15 minutes
    'refresh_ttl' => 604800,  // 7 days
],
```

### 3. Use Refresh Tokens

Implement token refresh to avoid frequent logins:

```php
// Client-side pseudocode
if (isTokenExpired(accessToken)) {
    accessToken = await refreshAccessToken(refreshToken);
}
```

### 4. Minimize Payload Size

Keep tokens small:

```php
// Good - minimal data
$token = $encoder->encode([
    'sub' => $userId,
]);

// Bad - too much data
$token = $encoder->encode([
    'sub' => $userId,
    'user' => $user->toArray(),  // Don't include full user object
    'permissions' => $allPermissions,  // Too much
]);
```

### 5. Don't Store Sensitive Data in Payload

JWT payload is only encoded, not encrypted:

```php
// Never include:
// - Passwords
// - API keys
// - Credit card numbers
// - Personal sensitive data
```

### 6. Use HTTPS

Always use HTTPS in production to protect tokens in transit.

### 7. Store Tokens Securely on Client

```javascript
// Good: httpOnly cookie (set by server)
// Good: Memory (for SPAs)

// Bad: localStorage (XSS vulnerable)
// Bad: sessionStorage (XSS vulnerable)
```

## Common Patterns

### Token Refresh Flow

```php
// AuthController
public function refresh($request, $response)
{
    $refreshToken = $request->input('refresh_token')
                 ?? $request->cookie('refresh_token');

    // Validate refresh token
    $payload = $this->encoder->decode($refreshToken);
    
    if ($payload === null) {
        return $response->json([
            'error' => 'Invalid or expired refresh token',
        ], 401);
    }
    
    // Ensure it's a refresh token
    if (($payload['type'] ?? null) !== 'refresh') {
        return $response->json([
            'error' => 'Not a refresh token',
        ], 401);
    }

    // Check if user still exists and is active
    $user = User::find($payload['sub']);
    if (!$user || !$user->is_active) {
        return $response->json([
            'error' => 'User not found or inactive',
        ], 401);
    }

    // Issue new access token
    $newAccessToken = $this->encoder->encode([
        'sub' => $user->getAuthIdentifier(),
        'type' => 'access',
    ]);

    return $response->json([
        'access_token' => $newAccessToken,
        'token_type' => 'Bearer',
        'expires_in' => 3600,
    ]);
}
```

### Token Revocation on Password Change

```php
class UserController
{
    public function changePassword($request, $response)
    {
        $user = user('api');
        
        // Validate and update password
        $user->password = password_hash($request->input('new_password'), PASSWORD_DEFAULT);
        $user->password_changed_at = time();  // Timestamp for token validation
        $user->save();

        // Blacklist all existing tokens
        // (Alternatively, check token_invalidated_at in JwtGuard)
        
        return $response->json(['message' => 'Password changed']);
    }
}
```

## Error Handling

The `JwtEncoder` returns `null` for invalid tokens instead of throwing exceptions:

```php
use Lalaz\Auth\Jwt\JwtBlacklist;

$payload = $encoder->decode($token);

if ($payload === null) {
    // Token is invalid, malformed, or expired
    return response()->json([
        'error' => 'invalid_token',
        'message' => 'Token is invalid or expired',
    ], 401);
}

// Check blacklist separately
$blacklist = resolve(JwtBlacklist::class);
if ($blacklist->isBlacklisted($token)) {
    return response()->json([
        'error' => 'token_revoked',
        'message' => 'Token has been revoked',
    ], 401);
}

// Token is valid
$userId = $payload['sub'];
```

## Testing

```php
use PHPUnit\Framework\TestCase;
use Lalaz\Auth\Guards\JwtGuard;
use Lalaz\Auth\Jwt\JwtEncoder;

class JwtGuardTest extends TestCase
{
    private JwtGuard $guard;
    private JwtEncoder $encoder;

    protected function setUp(): void
    {
        $this->encoder = new JwtEncoder('test-secret');
        $provider = $this->createMock(UserProviderInterface::class);
        $this->guard = new JwtGuard($provider, $this->encoder);
    }

    public function test_can_authenticate_with_valid_token(): void
    {
        $token = $this->encoder->encode(['sub' => 123]);
        
        // Simulate request with token
        $_SERVER['HTTP_AUTHORIZATION'] = "Bearer {$token}";
        
        $this->assertTrue($this->guard->check());
    }
}
```

## Troubleshooting

### "Token expired" Immediately After Login

**Problem:** Token expires right after creation.

**Solution:** Check server time and `exp` claim:
```php
$payload = $encoder->getClaims($token);
echo date('Y-m-d H:i:s', $payload['exp']);  // When does it expire?
echo date('Y-m-d H:i:s');  // What time is it now?
```

### "Invalid signature" Error

**Problem:** Token verification fails.

**Solutions:**
1. Ensure same secret is used for signing and verification
2. Check for whitespace in secret
3. Verify the secret in .env matches config

### Token Not Being Read

**Problem:** `user()` returns null even with valid token.

**Solutions:**
1. Check Authorization header format: `Bearer <token>`
2. Verify header is being sent by client
3. Check if web server is stripping Authorization header

```php
// Debug
var_dump($_SERVER['HTTP_AUTHORIZATION'] ?? 'Not set');
var_dump(getallheaders());
```

## Next Steps

- Learn about [API Key Guard](./api-key.md)
- Implement [Token Blacklisting](../jwt/blacklist.md)
- Add [Role-Based Authorization](../authorization.md)
- See complete [API Example](../examples/api.md)
