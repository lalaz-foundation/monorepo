# Token Blacklisting

The `JwtBlacklist` allows you to invalidate JWT tokens before their natural expiration.

## Why Blacklist?

JWTs are stateless - once issued, they're valid until expiration. Blacklisting handles:

- **Logout** - Invalidate token when user logs out
- **Password Change** - Invalidate all existing tokens
- **Security Breach** - Revoke compromised tokens
- **User Ban** - Immediately block user access

## Basic Usage

```php
use Lalaz\Auth\Jwt\JwtBlacklist;

$blacklist = resolve(JwtBlacklist::class);

// Blacklist a token
$blacklist->add($token);

// Check if blacklisted
if ($blacklist->isBlacklisted($token)) {
    // Token has been revoked
}

// Remove from blacklist (rarely needed)
$blacklist->remove($token);
```

## Configuration

```php
// config/auth.php
return [
    'jwt' => [
        'blacklist' => [
            'enabled' => true,
            'driver' => 'cache',  // or 'database'
            'prefix' => 'jwt_blacklist:',
            'ttl' => 604800,  // Keep in blacklist for 7 days
        ],
    ],
];
```

## Storage Drivers

### Cache Driver (Recommended)

Fast, automatic expiration:

```php
use Lalaz\Auth\Jwt\JwtBlacklist;
use Lalaz\Cache\CacheManager;

$cache = resolve(CacheManager::class);

$blacklist = new JwtBlacklist(
    cache: $cache,
    prefix: 'jwt_blacklist:',
    ttl: 604800  // 7 days
);
```

### Redis Driver

For distributed systems:

```php
use Redis;

class RedisBlacklistService
{
    public function __construct(
        private Redis $redis,
        private string $prefix = 'jwt:blacklist:'
    ) {}

    public function add(string $token, ?int $ttl = null): void
    {
        $key = $this->prefix . $this->getTokenId($token);
        $this->redis->setex($key, $ttl ?? $this->defaultTtl, '1');
    }

    public function isBlacklisted(string $token): bool
    {
        $key = $this->prefix . $this->getTokenId($token);
        return $this->redis->exists($key);
    }

    public function remove(string $token): void
    {
        $key = $this->prefix . $this->getTokenId($token);
        $this->redis->del($key);
    }

    private function getTokenId(string $token): string
    {
        // Use token hash for shorter keys
        return hash('sha256', $token);
    }
}
```

### Database Driver

For persistence:

```sql
CREATE TABLE jwt_blacklist (
    id INT PRIMARY KEY AUTO_INCREMENT,
    token_hash VARCHAR(64) UNIQUE,
    user_id INT,
    expires_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token_hash (token_hash),
    INDEX idx_expires_at (expires_at)
);
```

```php
class DatabaseBlacklistService
{
    public function add(string $token, ?int $ttl = null): void
    {
        $payload = $this->encoder->getClaims($token);
        
        DB::table('jwt_blacklist')->insert([
            'token_hash' => hash('sha256', $token),
            'user_id' => $payload['sub'] ?? null,
            'expires_at' => date('Y-m-d H:i:s', $payload['exp'] ?? time() + $ttl),
        ]);
    }

    public function isBlacklisted(string $token): bool
    {
        return DB::table('jwt_blacklist')
            ->where('token_hash', hash('sha256', $token))
            ->where('expires_at', '>', now())
            ->exists();
    }

    public function cleanup(): void
    {
        // Remove expired entries
        DB::table('jwt_blacklist')
            ->where('expires_at', '<', now())
            ->delete();
    }
}
```

## Integration with Guards

### JwtGuard Integration

```php
class JwtGuard implements GuardInterface
{
    public function __construct(
        private UserProviderInterface $provider,
        private JwtEncoder $encoder,
        private JwtBlacklist $blacklist
    ) {}

    public function user(): mixed
    {
        $token = $this->getTokenFromRequest();
        
        if (!$token) {
            return null;
        }

        // Check blacklist first
        if ($this->blacklist->isBlacklisted($token)) {
            return null;
        }

        try {
            $payload = $this->encoder->decode($token);
            return $this->provider->retrieveById($payload['sub']);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function logout(): void
    {
        $token = $this->getTokenFromRequest();
        
        if ($token) {
            $this->blacklist->add($token);
        }
        
        $this->currentUser = null;
    }
}
```

## Use Cases

### Logout

```php
class AuthController
{
    public function logout($request, $response)
    {
        // Blacklist current token
        $this->auth->guard('api')->logout();
        
        return $response->json([
            'message' => 'Successfully logged out',
        ]);
    }
}
```

### Password Change

Invalidate all user's tokens:

```php
class UserController
{
    public function changePassword($request, $response)
    {
        $user = user();
        
        // Update password
        $user->password = password_hash($request->input('new_password'), PASSWORD_DEFAULT);
        $user->token_invalidated_at = time();
        $user->save();
        
        // Blacklist current token
        $this->blacklist->add($this->getCurrentToken());
        
        return $response->json([
            'message' => 'Password changed. Please login again.',
        ]);
    }
}
```

### Revoke All Sessions

```php
class SecurityController
{
    public function revokeAllSessions($request, $response)
    {
        $user = user();
        
        // Update timestamp to invalidate all tokens issued before now
        $user->token_invalidated_at = time();
        $user->save();
        
        return $response->json([
            'message' => 'All sessions have been revoked',
        ]);
    }
}
```

**Then in JwtGuard:**

```php
public function user(): mixed
{
    $token = $this->getTokenFromRequest();
    
    try {
        $payload = $this->encoder->decode($token);
        $user = $this->provider->retrieveById($payload['sub']);
        
        // Check if token was issued before invalidation
        if ($user->token_invalidated_at && $payload['iat'] < $user->token_invalidated_at) {
            return null;  // Token is invalidated
        }
        
        return $user;
    } catch (\Exception $e) {
        return null;
    }
}
```

### Ban User

```php
class AdminController
{
    public function banUser($request, $response, $userId)
    {
        $user = User::find($userId);
        $user->is_banned = true;
        $user->banned_at = time();
        $user->save();
        
        // Invalidate all their tokens
        $user->token_invalidated_at = time();
        $user->save();
        
        return $response->json([
            'message' => 'User has been banned',
        ]);
    }
}
```

## Token ID Strategy

Instead of storing entire tokens, use a token ID:

### Using JTI Claim

```php
// When creating token
$token = $encoder->encode([
    'sub' => $userId,
    'jti' => bin2hex(random_bytes(16)),  // Unique token ID
]);

// Blacklist service uses JTI
class BlacklistService
{
    public function add(string $token): void
    {
        $payload = $this->encoder->getClaims($token);
        $jti = $payload['jti'] ?? null;
        
        if ($jti) {
            $this->cache->set("blacklist:{$jti}", true, $this->ttl);
        }
    }

    public function isBlacklisted(string $token): bool
    {
        $payload = $this->encoder->getClaims($token);
        $jti = $payload['jti'] ?? null;
        
        if (!$jti) {
            return false;
        }
        
        return $this->cache->has("blacklist:{$jti}");
    }
}
```

### Using Token Hash

```php
class BlacklistService
{
    private function getTokenId(string $token): string
    {
        // Hash is shorter and consistent
        return hash('sha256', $token);
    }

    public function add(string $token): void
    {
        $id = $this->getTokenId($token);
        $this->cache->set("blacklist:{$id}", true, $this->ttl);
    }

    public function isBlacklisted(string $token): bool
    {
        $id = $this->getTokenId($token);
        return $this->cache->has("blacklist:{$id}");
    }
}
```

## Automatic Cleanup

### For Database Driver

```php
// Run via cron job
class CleanupBlacklistCommand
{
    public function handle(): void
    {
        $deleted = DB::table('jwt_blacklist')
            ->where('expires_at', '<', now())
            ->delete();
            
        $this->info("Deleted {$deleted} expired entries");
    }
}
```

### For Cache Driver

Cache entries automatically expire - no cleanup needed.

## Testing

```php
use PHPUnit\Framework\TestCase;
use Lalaz\Auth\Jwt\JwtBlacklist;

class JwtBlacklistTest extends TestCase
{
    private JwtBlacklist $blacklist;
    private JwtEncoder $encoder;

    protected function setUp(): void
    {
        $this->encoder = new JwtEncoder('test-secret');
        $cache = new ArrayCache();
        $this->blacklist = new JwtBlacklist($cache);
    }

    public function test_adds_token_to_blacklist(): void
    {
        $token = $this->encoder->encode(['sub' => 123]);
        
        $this->blacklist->add($token);
        
        $this->assertTrue($this->blacklist->isBlacklisted($token));
    }

    public function test_non_blacklisted_token(): void
    {
        $token = $this->encoder->encode(['sub' => 123]);
        
        $this->assertFalse($this->blacklist->isBlacklisted($token));
    }

    public function test_removes_token_from_blacklist(): void
    {
        $token = $this->encoder->encode(['sub' => 123]);
        
        $this->blacklist->add($token);
        $this->blacklist->remove($token);
        
        $this->assertFalse($this->blacklist->isBlacklisted($token));
    }

    public function test_different_tokens_are_independent(): void
    {
        $token1 = $this->encoder->encode(['sub' => 123]);
        $token2 = $this->encoder->encode(['sub' => 456]);
        
        $this->blacklist->add($token1);
        
        $this->assertTrue($this->blacklist->isBlacklisted($token1));
        $this->assertFalse($this->blacklist->isBlacklisted($token2));
    }
}
```

## Performance Considerations

### Cache-Based (Recommended)

- O(1) lookup time
- Automatic expiration
- Scales well with Redis

### Database-Based

- Persistent storage
- Can query by user
- Needs periodic cleanup
- Add index on `token_hash`

### Hybrid Approach

```php
class HybridBlacklistService
{
    public function __construct(
        private CacheInterface $cache,
        private DatabaseInterface $db
    ) {}

    public function add(string $token): void
    {
        $id = $this->getTokenId($token);
        
        // Add to cache for fast lookup
        $this->cache->set("blacklist:{$id}", true, $this->ttl);
        
        // Persist to database for audit trail
        $this->db->insert('jwt_blacklist', [
            'token_hash' => $id,
            'created_at' => now(),
        ]);
    }

    public function isBlacklisted(string $token): bool
    {
        $id = $this->getTokenId($token);
        
        // Check cache first (fast path)
        if ($this->cache->has("blacklist:{$id}")) {
            return true;
        }
        
        // Fall back to database (slow path)
        $exists = $this->db->exists('jwt_blacklist', [
            'token_hash' => $id,
        ]);
        
        // Re-populate cache if found
        if ($exists) {
            $this->cache->set("blacklist:{$id}", true, $this->ttl);
        }
        
        return $exists;
    }
}
```

## Next Steps

- [JWT Signers](./signers.md) - Cryptographic algorithms
- [JWT Guard](../guards/jwt.md) - Using JWT for authentication
- [API Example](../examples/api.md) - Complete API implementation
