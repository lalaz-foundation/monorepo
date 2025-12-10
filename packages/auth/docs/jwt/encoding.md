# JWT Encoding

The `JwtEncoder` class handles creating and validating JSON Web Tokens.

## Basic Usage

### Creating Tokens

```php
use Lalaz\Auth\Jwt\JwtEncoder;

$encoder = resolve(JwtEncoder::class);

// Simple token with user ID
$token = $encoder->encode([
    'sub' => $userId,
]);

// Token with custom claims
$token = $encoder->encode([
    'sub' => $userId,
    'email' => 'user@example.com',
    'roles' => ['admin', 'editor'],
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
    // Token is invalid, malformed, or expired
    // Reject the request
} else {
    $userId = $payload['sub'];
    $email = $payload['email'] ?? null;
    $issuedAt = $payload['iat'];
    $expiresAt = $payload['exp'];
}
```

### Validating Without Decoding

```php
// Check if token is valid without getting payload
if ($encoder->validate($token)) {
    // Token is valid
}
```

## Constructor

```php
use Lalaz\Auth\Jwt\JwtEncoder;
use Lalaz\Auth\Jwt\Signers\HmacSha256Signer;

// With secret string (uses HMAC-SHA256)
$encoder = new JwtEncoder('your-secret-key');

// With custom signer
$signer = new HmacSha256Signer('your-secret-key');
$encoder = new JwtEncoder($signer);

// With default TTL
$encoder = new JwtEncoder(
    secret: 'your-secret-key',
    defaultTtl: 3600  // 1 hour
);
```

## API Reference

### encode()

```php
public function encode(array $payload, ?int $ttl = null): string
```

Creates a JWT from the payload.

**Parameters:**
- `payload` - Array of claims to include
- `ttl` - Token lifetime in seconds (optional, uses default if not provided)

**Returns:** JWT string

**Automatic Claims:**
- `iat` - Issued at (current timestamp)
- `exp` - Expiration time (iat + ttl)

```php
// These claims are added automatically
$token = $encoder->encode(['sub' => 123]);

$payload = $encoder->decode($token);
// $payload = [
//     'sub' => 123,
//     'iat' => 1689790143,
//     'exp' => 1689793743,
// ]
```

### decode()

```php
public function decode(string $token): ?array
```

Decodes and validates a JWT.

**Parameters:**
- `token` - JWT string

**Returns:** 
- Payload array if valid
- `null` if token is invalid, malformed, expired, or has invalid signature

```php
$payload = $encoder->decode($token);

if ($payload === null) {
    // Handle invalid/expired token
} else {
    // Token is valid, use payload
    $userId = $payload['sub'];
}
```

### validate()

```php
public function validate(string $token): bool
```

Checks if a token is valid.

```php
if ($encoder->validate($token)) {
    $payload = $encoder->decode($token);
}
```

### getClaims()

```php
public function getClaims(string $token): ?array
```

Decodes token without validating signature or expiration.

> ⚠️ **Warning:** Use only for debugging. Never use in production for authentication.

```php
// For debugging only
$payload = $encoder->getClaims($token);
var_dump($payload);
```

## Standard Claims

### Required Claims

| Claim | Description | Example |
|-------|-------------|---------|
| `sub` | Subject (user ID) | `"123"` or `123` |

### Automatic Claims

| Claim | Description | Set By |
|-------|-------------|--------|
| `iat` | Issued At | `encode()` |
| `exp` | Expiration | `encode()` |

### Optional Claims

| Claim | Description | Example |
|-------|-------------|---------|
| `iss` | Issuer | `"my-app"` |
| `aud` | Audience | `"api-users"` |
| `nbf` | Not Before | `1689790143` |
| `jti` | JWT ID (unique) | `"abc123"` |

## Custom Claims

Add any data you need:

```php
$token = $encoder->encode([
    'sub' => $userId,
    
    // User info
    'email' => $user->email,
    'name' => $user->name,
    
    // Authorization
    'roles' => ['admin', 'editor'],
    'permissions' => ['posts.create', 'posts.edit'],
    
    // Custom data
    'tenant_id' => $user->tenant_id,
    'subscription' => 'premium',
]);
```

### Best Practices for Custom Claims

**Do include:**
- User identifier (`sub`)
- Roles and permissions (if small)
- Token type (access/refresh)

**Don't include:**
- Passwords or secrets
- Large data objects
- Sensitive personal information
- Frequently changing data

```php
// Good
$token = $encoder->encode([
    'sub' => $userId,
    'type' => 'access',
    'roles' => ['admin'],
]);

// Bad - too much data
$token = $encoder->encode([
    'sub' => $userId,
    'user' => $user->toArray(),  // Entire user object
    'all_permissions' => [...],   // Huge array
]);
```

## Token Types

### Access Token

Short-lived token for API requests:

```php
$accessToken = $encoder->encode(
    payload: [
        'sub' => $userId,
        'type' => 'access',
    ],
    ttl: 900  // 15 minutes
);
```

### Refresh Token

Long-lived token for getting new access tokens:

```php
$refreshToken = $encoder->encode(
    payload: [
        'sub' => $userId,
        'type' => 'refresh',
    ],
    ttl: 604800  // 7 days
);
```

### Using Both

```php
public function login($request, $response)
{
    $user = $this->authenticate($request);
    
    $accessToken = $this->encoder->encode([
        'sub' => $user->getAuthIdentifier(),
        'type' => 'access',
    ], ttl: 900);
    
    $refreshToken = $this->encoder->encode([
        'sub' => $user->getAuthIdentifier(),
        'type' => 'refresh',
    ], ttl: 604800);
    
    return $response->json([
        'access_token' => $accessToken,
        'refresh_token' => $refreshToken,
        'token_type' => 'Bearer',
        'expires_in' => 900,
    ]);
}

public function refresh($request, $response)
{
    $refreshToken = $request->input('refresh_token');
    
    $payload = $this->encoder->decode($refreshToken);
    
    if ($payload === null) {
        return $response->json([
            'error' => 'Invalid or expired refresh token',
        ], 401);
    }
    
    if ($payload['type'] !== 'refresh') {
        return $response->json([
            'error' => 'Not a refresh token',
        ], 401);
    }
    
    // Issue new access token
    $accessToken = $this->encoder->encode([
        'sub' => $payload['sub'],
        'type' => 'access',
    ], 900);
    
    return $response->json([
        'access_token' => $accessToken,
        'token_type' => 'Bearer',
        'expires_in' => 900,
    ]);
}
```

## Error Handling

The `JwtEncoder` returns `null` for invalid tokens instead of throwing exceptions. This allows for cleaner error handling:

### Handling Invalid Tokens

```php
$payload = $encoder->decode($token);

if ($payload === null) {
    // Token is invalid, malformed, or expired
    return response()->json([
        'error' => 'invalid_token',
        'message' => 'The provided token is invalid or expired.',
    ], 401);
}

// Token is valid, continue processing
$userId = $payload['sub'];
```

### Complete Error Handling with Blacklist

```php
use Lalaz\Auth\Jwt\JwtBlacklist;

$payload = $encoder->decode($token);

if ($payload === null) {
    return response()->json([
        'error' => 'invalid_token',
        'message' => 'Token is invalid or expired',
        'action' => 'login',
    ], 401);
}

// Check blacklist
$blacklist = resolve(JwtBlacklist::class);
if ($blacklist->isBlacklisted($token)) {
    return response()->json([
        'error' => 'token_revoked',
        'message' => 'Token has been revoked',
        'action' => 'login',
    ], 401);
}

// Token is valid, use it
return $payload;
```

## Manual Token Construction

For advanced use cases:

```php
class JwtEncoder
{
    public function encode(array $payload, ?int $ttl = null): string
    {
        // Add standard claims
        $payload['iat'] = time();
        $payload['exp'] = time() + ($ttl ?? $this->defaultTtl);
        
        // Build header
        $header = [
            'alg' => $this->signer->getAlgorithm(),
            'typ' => 'JWT',
        ];
        
        // Encode parts
        $headerEncoded = $this->base64UrlEncode(json_encode($header));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));
        
        // Create signature
        $signature = $this->signer->sign("{$headerEncoded}.{$payloadEncoded}");
        $signatureEncoded = $this->base64UrlEncode($signature);
        
        return "{$headerEncoded}.{$payloadEncoded}.{$signatureEncoded}";
    }
}
```

## Testing

```php
use PHPUnit\Framework\TestCase;
use Lalaz\Auth\Jwt\JwtEncoder;

class JwtEncoderTest extends TestCase
{
    private JwtEncoder $encoder;

    protected function setUp(): void
    {
        $this->encoder = new JwtEncoder('test-secret');
    }

    public function test_encodes_payload(): void
    {
        $token = $this->encoder->encode(['sub' => 123]);
        
        $this->assertIsString($token);
        $this->assertCount(3, explode('.', $token));
    }

    public function test_decodes_valid_token(): void
    {
        $token = $this->encoder->encode(['sub' => 123, 'name' => 'John']);
        
        $payload = $this->encoder->decode($token);
        
        $this->assertNotNull($payload);
        $this->assertEquals(123, $payload['sub']);
        $this->assertEquals('John', $payload['name']);
        $this->assertArrayHasKey('iat', $payload);
        $this->assertArrayHasKey('exp', $payload);
    }

    public function test_returns_null_on_expired_token(): void
    {
        $token = $this->encoder->encode(['sub' => 123], -1);
        
        $payload = $this->encoder->decode($token);
        
        $this->assertNull($payload);
    }

    public function test_returns_null_on_invalid_signature(): void
    {
        $token = $this->encoder->encode(['sub' => 123]);
        $parts = explode('.', $token);
        $parts[2] = 'invalid-signature';
        $tamperedToken = implode('.', $parts);
        
        $payload = $this->encoder->decode($tamperedToken);
        
        $this->assertNull($payload);
    }

    public function test_validate_returns_true_for_valid_token(): void
    {
        $token = $this->encoder->encode(['sub' => 123]);
        
        $this->assertTrue($this->encoder->validate($token));
    }

    public function test_validate_returns_false_for_invalid_token(): void
    {
        $this->assertFalse($this->encoder->validate('invalid.token.here'));
    }
}
```

## Next Steps

- [Token Blacklisting](./blacklist.md) - Invalidating tokens
- [Signers](./signers.md) - HMAC vs RSA algorithms
- [JWT Guard](../guards/jwt.md) - Using JWT for authentication
