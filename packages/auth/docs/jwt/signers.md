# JWT Signers

Signers are cryptographic algorithms that create and verify JWT signatures.

## Available Signers

| Algorithm | Type | Key Type | Use Case |
|-----------|------|----------|----------|
| HS256 | HMAC | Shared secret | Single server, simple setup |
| HS384 | HMAC | Shared secret | Higher security needs |
| HS512 | HMAC | Shared secret | Maximum HMAC security |
| RS256 | RSA | Public/Private | Distributed systems |

## HMAC Signers (Symmetric)

Same secret key for signing and verification.

### HmacSha256Signer (HS256)

Most common choice. Good balance of security and performance.

```php
use Lalaz\Auth\Jwt\Signers\HmacSha256Signer;

$signer = new HmacSha256Signer('your-256-bit-secret');

// Create encoder with signer
$encoder = new JwtEncoder($signer);
```

### HmacSha384Signer (HS384)

More secure, slightly slower.

```php
use Lalaz\Auth\Jwt\Signers\HmacSha384Signer;

$signer = new HmacSha384Signer('your-384-bit-secret');
```

### HmacSha512Signer (HS512)

Maximum HMAC security.

```php
use Lalaz\Auth\Jwt\Signers\HmacSha512Signer;

$signer = new HmacSha512Signer('your-512-bit-secret');
```

### Generating HMAC Secrets

```bash
# Generate 256-bit secret (for HS256)
php -r "echo bin2hex(random_bytes(32));"

# Generate 384-bit secret (for HS384)
php -r "echo bin2hex(random_bytes(48));"

# Generate 512-bit secret (for HS512)
php -r "echo bin2hex(random_bytes(64));"
```

## RSA Signers (Asymmetric)

Private key signs, public key verifies. Useful when:
- Multiple services need to verify tokens
- You don't want to share the signing key

### RsaSha256Signer (RS256)

```php
use Lalaz\Auth\Jwt\Signers\RsaSha256Signer;

// For signing (needs private key)
$privateKey = file_get_contents('private.pem');
$signer = new RsaSha256Signer(privateKey: $privateKey);

// For verification only (needs public key)
$publicKey = file_get_contents('public.pem');
$verifier = new RsaSha256Signer(publicKey: $publicKey);

// For both signing and verification
$signer = new RsaSha256Signer(
    privateKey: $privateKey,
    publicKey: $publicKey
);

// With passphrase for encrypted private key
$signer = new RsaSha256Signer(
    privateKey: $privateKey,
    passphrase: 'your-passphrase'
);
```

### Generating RSA Keys

```bash
# Generate private key
openssl genrsa -out private.pem 2048

# Extract public key
openssl rsa -in private.pem -pubout -out public.pem
```

## Signer Interface

All signers implement `JwtSignerInterface`:

```php
namespace Lalaz\Auth\Contracts;

interface JwtSignerInterface
{
    /**
     * Get the algorithm name (e.g., "HS256", "RS256")
     */
    public function getAlgorithm(): string;

    /**
     * Sign the data and return the signature.
     */
    public function sign(string $data): string;

    /**
     * Verify the signature matches the data.
     */
    public function verify(string $data, string $signature): bool;
}
```

## Using Signers

### With JwtEncoder

```php
use Lalaz\Auth\Jwt\JwtEncoder;
use Lalaz\Auth\Jwt\Signers\HmacSha256Signer;

// HMAC
$signer = new HmacSha256Signer(env('JWT_SECRET'));
$encoder = new JwtEncoder($signer, defaultTtl: 3600);

// RSA
$signer = new RsaSha256Signer(privateKey: file_get_contents('private.pem'));
$encoder = new JwtEncoder($signer, defaultTtl: 3600);
```

### Configuration

```php
// config/auth.php
return [
    'jwt' => [
        'algorithm' => env('JWT_ALGORITHM', 'HS256'),
        'secret' => env('JWT_SECRET'),  // For HMAC
        'private_key' => env('JWT_PRIVATE_KEY'),  // For RSA
        'public_key' => env('JWT_PUBLIC_KEY'),    // For RSA
        'ttl' => 3600,
    ],
];
```

```php
// In service provider
$config = config('auth.jwt');

$signer = match ($config['algorithm']) {
    'HS256' => new HmacSha256Signer($config['secret']),
    'HS384' => new HmacSha384Signer($config['secret']),
    'HS512' => new HmacSha512Signer($config['secret']),
    'RS256' => new RsaSha256Signer(privateKey: $config['private_key']),
    default => throw new \Exception("Unsupported algorithm: {$config['algorithm']}"),
};

$encoder = new JwtEncoder($signer, $config['ttl']);
```

## Choosing an Algorithm

### Use HMAC When:

- Single application handles both signing and verification
- Simpler key management is preferred
- Performance is critical (HMAC is faster)

```php
// Simple monolith application
$signer = new HmacSha256Signer(env('JWT_SECRET'));
```

### Use RSA When:

- Multiple services need to verify tokens
- You want to distribute the public key only
- Microservices architecture
- Third parties need to verify your tokens

```php
// Auth service (has private key)
$signer = new RsaSha256Signer(privateKey: $privateKey);

// API services (only have public key)
$verifier = new RsaSha256Signer(publicKey: $publicKey);
```

## Distributed System Example

### Auth Service

Signs tokens with private key:

```php
// auth-service/config/auth.php
return [
    'jwt' => [
        'algorithm' => 'RS256',
        'private_key' => file_get_contents('/secrets/jwt-private.pem'),
    ],
];
```

```php
// auth-service/AuthController.php
class AuthController
{
    public function login($request, $response)
    {
        // Validate credentials
        $user = $this->authenticate($request);
        
        // Sign token with private key
        $token = $this->encoder->encode([
            'sub' => $user->id,
            'email' => $user->email,
        ]);
        
        return $response->json([
            'access_token' => $token,
        ]);
    }
}
```

### API Service

Verifies tokens with public key:

```php
// api-service/config/auth.php
return [
    'jwt' => [
        'algorithm' => 'RS256',
        'public_key' => file_get_contents('/secrets/jwt-public.pem'),
    ],
];
```

```php
// api-service/JwtMiddleware.php
class JwtMiddleware
{
    public function __construct()
    {
        $publicKey = config('auth.jwt.public_key');
        $verifier = new RsaSha256Signer(publicKey: $publicKey);
        $this->encoder = new JwtEncoder($verifier);
    }

    public function handle($request, $next)
    {
        $token = $this->getToken($request);
        
        try {
            $payload = $this->encoder->decode($token);
            $request->setUser($payload);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        return $next($request);
    }
}
```

## Custom Signer

Create your own signer:

```php
use Lalaz\Auth\Contracts\JwtSignerInterface;

class EdDsaSigner implements JwtSignerInterface
{
    public function __construct(
        private string $privateKey,
        private string $publicKey
    ) {}

    public function getAlgorithm(): string
    {
        return 'EdDSA';
    }

    public function sign(string $data): string
    {
        return sodium_crypto_sign_detached($data, $this->privateKey);
    }

    public function verify(string $data, string $signature): bool
    {
        return sodium_crypto_sign_verify_detached(
            $signature,
            $data,
            $this->publicKey
        );
    }
}
```

## Security Best Practices

### 1. Strong Key Length

```php
// HS256 needs at least 256 bits (32 bytes)
$secret = bin2hex(random_bytes(32));

// RSA needs at least 2048 bits
openssl genrsa -out private.pem 2048
```

### 2. Protect Private Keys

```bash
# Set proper permissions
chmod 600 private.pem

# Use environment variables
export JWT_PRIVATE_KEY="$(cat private.pem)"
```

### 3. Key Rotation

```php
// Support multiple keys during rotation
class KeyRotatingEncoder
{
    private array $signers = [];
    private int $currentKeyId = 0;

    public function addKey(int $keyId, SignerInterface $signer): void
    {
        $this->signers[$keyId] = $signer;
    }

    public function setCurrentKey(int $keyId): void
    {
        $this->currentKeyId = $keyId;
    }

    public function encode(array $payload): string
    {
        $payload['kid'] = $this->currentKeyId;  // Key ID in token
        $signer = $this->signers[$this->currentKeyId];
        // ... encode with current key
    }

    public function decode(string $token): array
    {
        // Get key ID from header
        $header = $this->decodeHeader($token);
        $keyId = $header['kid'] ?? $this->currentKeyId;
        
        $signer = $this->signers[$keyId] ?? throw new InvalidKeyException();
        // ... verify with correct key
    }
}
```

### 4. Algorithm Verification

Always verify the algorithm in the token matches expected:

```php
class JwtEncoder
{
    public function decode(string $token): array
    {
        $parts = explode('.', $token);
        $header = json_decode(base64_decode($parts[0]), true);
        
        // Prevent algorithm confusion attacks
        if ($header['alg'] !== $this->signer->getAlgorithm()) {
            throw new InvalidTokenException('Algorithm mismatch');
        }
        
        // Continue verification...
    }
}
```

## Testing

```php
use PHPUnit\Framework\TestCase;
use Lalaz\Auth\Jwt\Signers\HmacSha256Signer;

class HmacSignerTest extends TestCase
{
    private HmacSha256Signer $signer;

    protected function setUp(): void
    {
        $this->signer = new HmacSha256Signer('test-secret');
    }

    public function test_returns_correct_algorithm(): void
    {
        $this->assertEquals('HS256', $this->signer->getAlgorithm());
    }

    public function test_signs_data(): void
    {
        $signature = $this->signer->sign('test data');
        
        $this->assertNotEmpty($signature);
    }

    public function test_verifies_valid_signature(): void
    {
        $data = 'test data';
        $signature = $this->signer->sign($data);
        
        $this->assertTrue($this->signer->verify($data, $signature));
    }

    public function test_rejects_invalid_signature(): void
    {
        $data = 'test data';
        
        $this->assertFalse($this->signer->verify($data, 'invalid'));
    }

    public function test_rejects_tampered_data(): void
    {
        $signature = $this->signer->sign('original data');
        
        $this->assertFalse($this->signer->verify('tampered data', $signature));
    }

    public function test_different_secrets_produce_different_signatures(): void
    {
        $signer1 = new HmacSha256Signer('secret1');
        $signer2 = new HmacSha256Signer('secret2');
        
        $data = 'test data';
        
        $sig1 = $signer1->sign($data);
        $sig2 = $signer2->sign($data);
        
        $this->assertNotEquals($sig1, $sig2);
    }
}
```

## Algorithm Comparison

| Algorithm | Speed | Key Size | Security | Best For |
|-----------|-------|----------|----------|----------|
| HS256 | Fast | 256-bit | Good | Most apps |
| HS384 | Fast | 384-bit | Better | Higher security |
| HS512 | Fast | 512-bit | Best HMAC | Maximum HMAC security |
| RS256 | Slow | 2048-bit | Good | Distributed systems |

## Next Steps

- [JWT Encoding](./encoding.md) - Creating and decoding tokens
- [Token Blacklisting](./blacklist.md) - Invalidating tokens
- [JWT Guard](../guards/jwt.md) - Using JWT for authentication
