# JWT Operations

This section covers JWT (JSON Web Token) functionality in Lalaz Auth.

## Overview

JWT is used for stateless authentication, commonly in APIs. The JWT system consists of:

- **JwtEncoder** - Creates and validates tokens
- **Signers** - Cryptographic algorithms (HMAC, RSA)
- **JwtBlacklist** - Invalidate tokens before expiration

## Contents

- [JWT Encoding](./encoding.md) - Creating and decoding tokens
- [Token Blacklisting](./blacklist.md) - Invalidating tokens
- [Signers](./signers.md) - Cryptographic algorithms

## Quick Start

### Generate a Token

```php
use Lalaz\Auth\Jwt\JwtEncoder;

$encoder = resolve(JwtEncoder::class);

$token = $encoder->encode([
    'sub' => $userId,
    'email' => 'user@example.com',
]);
```

### Validate a Token

```php
try {
    $payload = $encoder->decode($token);
    $userId = $payload['sub'];
} catch (ExpiredTokenException $e) {
    // Token expired
} catch (InvalidTokenException $e) {
    // Token invalid
}
```

### Blacklist a Token

```php
use Lalaz\Auth\Jwt\JwtBlacklist;

$blacklist = resolve(JwtBlacklist::class);
$blacklist->add($token);
```

## Configuration

```php
// config/auth.php
return [
    'jwt' => [
        'secret' => env('JWT_SECRET'),
        'algorithm' => 'HS256',
        'ttl' => 3600,           // 1 hour
        'refresh_ttl' => 604800, // 7 days
    ],
];
```

```ini
# .env
JWT_SECRET=your-256-bit-secret-here
```

## JWT Structure

```
eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjMifQ.signature
└───────────────────────────────────────┘└──────────────────┘└─────────┘
              Header                         Payload          Signature
```

### Header
```json
{
    "alg": "HS256",
    "typ": "JWT"
}
```

### Payload (Claims)
```json
{
    "sub": "123",           // Subject (user ID)
    "iat": 1689790143,      // Issued at
    "exp": 1689793743,      // Expiration
    "email": "user@example.com"  // Custom claim
}
```

## Standard Claims

| Claim | Name | Description |
|-------|------|-------------|
| `sub` | Subject | User identifier |
| `iat` | Issued At | Token creation time |
| `exp` | Expiration | Token expiry time |
| `nbf` | Not Before | Token valid after this time |
| `iss` | Issuer | Token issuer |
| `aud` | Audience | Intended recipient |
| `jti` | JWT ID | Unique token identifier |

## Security Best Practices

1. **Use Strong Secrets**
   ```bash
   php -r "echo bin2hex(random_bytes(32));"
   ```

2. **Short Token Lifetime**
   ```php
   'ttl' => 900,  // 15 minutes
   ```

3. **Always Use HTTPS**

4. **Implement Refresh Tokens**

5. **Blacklist on Logout**

6. **Validate All Claims**

## Next Steps

- [JWT Encoding](./encoding.md) - Detailed encoding/decoding
- [Token Blacklisting](./blacklist.md) - Invalidating tokens
- [Signers](./signers.md) - HMAC vs RSA algorithms
