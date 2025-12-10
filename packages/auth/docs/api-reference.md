# API Reference

Complete reference for all classes, interfaces, and methods in Lalaz Auth.

## Table of Contents

- [Contracts (Interfaces)](#contracts)
- [AuthManager](#authmanager)
- [AuthContext](#authcontext)
- [Guards](#guards)
- [Providers](#providers)
- [Middlewares](#middlewares)
- [JWT Classes](#jwt-classes)
- [Helper Functions](#helper-functions)

---

## Contracts

### AuthenticatableInterface

Interface that user models must implement to work with the authentication system.

```php
namespace Lalaz\Auth\Contracts;

interface AuthenticatableInterface
{
    public function getAuthIdentifier(): mixed;
    public function getAuthIdentifierName(): string;
    public function getAuthPassword(): string;
    public function getRememberToken(): ?string;
    public function setRememberToken(?string $value): void;
    public function getRememberTokenName(): string;
}
```

| Method | Return Type | Description |
|--------|-------------|-------------|
| `getAuthIdentifier()` | `mixed` | Get the unique identifier for the user |
| `getAuthIdentifierName()` | `string` | Get the name of the identifier column (e.g., 'id') |
| `getAuthPassword()` | `string` | Get the hashed password |
| `getRememberToken()` | `?string` | Get the remember me token |
| `setRememberToken(?string)` | `void` | Set the remember me token |
| `getRememberTokenName()` | `string` | Get the column name for the remember token |

> **Note:** For role and permission support, implement `AuthorizableInterface` which provides `getRoles()`, `getPermissions()`, `hasRole()`, `hasPermission()`, etc.

---

### GuardInterface

Interface for authentication guards.

```php
namespace Lalaz\Auth\Contracts;

interface GuardInterface
{
    public function getName(): string;
    public function attempt(array $credentials): mixed;
    public function login(mixed $user): void;
    public function logout(): void;
    public function check(): bool;
    public function guest(): bool;
    public function user(): mixed;
    public function id(): mixed;
    public function validate(array $credentials): bool;
    public function setProvider(UserProviderInterface $provider): void;
}
```

| Method | Return Type | Description |
|--------|-------------|-------------|
| `getName()` | `string` | Get the guard name/identifier |
| `attempt(array)` | `mixed` | Authenticate with credentials (returns user or null) |
| `login(mixed)` | `void` | Log in a user directly |
| `logout()` | `void` | Log out current user |
| `check()` | `bool` | Check if authenticated |
| `guest()` | `bool` | Check if guest (not authenticated) |
| `user()` | `mixed` | Get authenticated user or null |
| `id()` | `mixed` | Get the authenticated user's ID |
| `validate(array)` | `bool` | Validate credentials without persisting authentication |
| `setProvider(UserProviderInterface)` | `void` | Set the user provider |

---

### StatelessGuardInterface

Interface for stateless guards (JWT, API Key). Extends `GuardInterface`.

```php
namespace Lalaz\Auth\Contracts;

interface StatelessGuardInterface extends GuardInterface
{
    public function createToken(mixed $user, array $claims = []): string;
    public function authenticateToken(string $token): mixed;
    public function revokeToken(string $token): bool;
    public function refreshToken(string $token): ?string;
    public function getTokenFromRequest(): ?string;
}
```

| Method | Return Type | Description |
|--------|-------------|-------------|
| `createToken(mixed, array)` | `string` | Create a token for user |
| `authenticateToken(string)` | `mixed` | Authenticate using token |
| `revokeToken(string)` | `bool` | Revoke a token |
| `refreshToken(string)` | `?string` | Refresh and get new token |
| `getTokenFromRequest()` | `?string` | Extract token from HTTP request |

---

### UserProviderInterface

Interface for user providers.

```php
namespace Lalaz\Auth\Contracts;

interface UserProviderInterface
{
    public function retrieveById(mixed $identifier): mixed;
    public function retrieveByCredentials(array $credentials): mixed;
    public function validateCredentials(mixed $user, array $credentials): bool;
}
```

| Method | Return Type | Description |
|--------|-------------|-------------|
| `retrieveById(mixed)` | `mixed` | Find user by ID |
| `retrieveByCredentials(array)` | `mixed` | Find user by credentials (excluding password) |
| `validateCredentials(mixed, array)` | `bool` | Validate user's password |

> **Note:** Additional interfaces exist for extended functionality:
> - `RememberTokenProviderInterface` - For remember me tokens
> - `ApiKeyProviderInterface` - For API key authentication

---

## AuthManager

Main class for managing authentication guards.

```php
namespace Lalaz\Auth;

class AuthManager
{
    public function guard(?string $name = null): GuardInterface;
    public function extend(string $name, Closure $callback): self;
    public function register(string $name, GuardInterface $guard): self;
    public function registerProvider(string $name, UserProviderInterface $provider): self;
    public function getProvider(string $name): ?UserProviderInterface;
    public function setDefaultGuard(string $name): self;
    public function getDefaultGuard(): string;
    public function hasGuard(string $name): bool;
    public function getGuardNames(): array;
    public function forgetGuard(string $name): void;
    public function forgetGuards(): void;
    
    // Convenience methods (delegate to default guard)
    public function check(): bool;
    public function guest(): bool;
    public function user(): mixed;
    public function id(): mixed;
    public function attempt(array $credentials): mixed;
    public function login(mixed $user): void;
    public function logout(): void;
}
```

### Methods

#### `guard(?string $name = null): GuardInterface`

Get a guard instance.

```php
$webGuard = $auth->guard();        // Default guard
$apiGuard = $auth->guard('api');   // Named guard
$adminGuard = $auth->guard('admin');
```

#### `extend(string $name, Closure $callback): self`

Register a custom guard creator.

```php
$auth->extend('custom', function ($name, $manager) {
    return new CustomGuard();
});
```

#### `register(string $name, GuardInterface $guard): self`

Register a guard instance directly.

```php
$auth->register('oauth', new OAuthGuard($config));
```

#### `registerProvider(string $name, UserProviderInterface $provider): self`

Register a user provider.

```php
$auth->registerProvider('users', new ModelUserProvider(User::class));
```

#### `setDefaultGuard(string $name): self`

Set the default guard.

```php
$auth->setDefaultGuard('api');
```

#### `getDefaultGuard(): string`

Get the default guard name.

```php
$default = $auth->getDefaultGuard(); // 'session'
```

#### `hasGuard(string $name): bool`

Check if a guard is registered.

```php
if ($auth->hasGuard('jwt')) {
    // JWT guard is available
}
```

#### `forgetGuard(string $name): void`

Forget a cached guard instance.

```php
$auth->forgetGuard('web');
```

---

## AuthContext

Per-request authentication context. Stores authenticated users per guard.

```php
namespace Lalaz\Auth;

class AuthContext
{
    public function setUser(mixed $user, ?string $guard = null): void;
    public function user(?string $guard = null): mixed;
    public function clear(?string $guard = null): void;
    public function isAuthenticated(?string $guard = null): bool;
    public function check(?string $guard = null): bool;
    public function isGuest(?string $guard = null): bool;
    public function guest(?string $guard = null): bool;
    public function setCurrentGuard(string $guard): self;
    public function getCurrentGuard(): string;
    public function setDefaultGuard(string $guard): self;
    public function getDefaultGuard(): string;
    public function guard(string $guard): GuardContext;
    public function id(?string $guard = null): mixed;
    
    // Role/permission delegation (calls user methods)
    public function hasRole(string $role, ?string $guard = null): bool;
    public function hasAnyRole(array $roles, ?string $guard = null): bool;
    public function hasPermission(string $permission, ?string $guard = null): bool;
    public function hasAnyPermission(array $permissions, ?string $guard = null): bool;
}
```

### Authentication Methods

| Method | Return Type | Description |
|--------|-------------|-------------|
| `setUser(mixed, ?string)` | `void` | Set the authenticated user for a guard |
| `user(?string)` | `mixed` | Get authenticated user (null = current guard) |
| `clear(?string)` | `void` | Clear user for a guard (null = all guards) |
| `isAuthenticated(?string)` | `bool` | Check if user is authenticated |
| `check(?string)` | `bool` | Alias for isAuthenticated() |
| `isGuest(?string)` | `bool` | Check if no user is authenticated |
| `guest(?string)` | `bool` | Alias for isGuest() |
| `id(?string)` | `mixed` | Get the authenticated user's ID |

### Guard Management

| Method | Return Type | Description |
|--------|-------------|-------------|
| `setCurrentGuard(string)` | `self` | Set the current active guard |
| `getCurrentGuard()` | `string` | Get the current guard name |
| `setDefaultGuard(string)` | `self` | Set the default guard |
| `getDefaultGuard()` | `string` | Get the default guard name |
| `guard(string)` | `GuardContext` | Get a guard-scoped context wrapper |

### Role Methods (delegated to user)

| Method | Parameters | Return Type | Description |
|--------|------------|-------------|-------------|
| `hasRole` | `string $role, ?string $guard` | `bool` | User has specific role |
| `hasAnyRole` | `array $roles, ?string $guard` | `bool` | User has at least one role |

### Permission Methods (delegated to user)

| Method | Parameters | Return Type | Description |
|--------|------------|-------------|-------------|
| `hasPermission` | `string $permission, ?string $guard` | `bool` | User has specific permission |
| `hasAnyPermission` | `array $permissions, ?string $guard` | `bool` | User has at least one permission |

### Usage Example

```php
$context = resolve(AuthContext::class);

// Set user after authentication
$context->setUser($user, 'web');
$context->setCurrentGuard('web');

// Check authentication
if ($context->check()) {
    $user = $context->user();
    $userId = $context->id();
}

// Use guard-scoped context
$context->guard('api')->user();
$context->guard('api')->hasRole('admin');
```

---

## Guards

### SessionGuard

Session-based authentication for web applications.

```php
namespace Lalaz\Auth\Guards;

class SessionGuard extends BaseGuard
{
    public function __construct(
        ?SessionInterface $session = null,
        ?UserProviderInterface $provider = null,
        ?RequestInterface $request = null
    );
    
    // GuardInterface methods
    public function getName(): string;
    public function attempt(array $credentials): mixed;
    public function login(mixed $user): void;
    public function logout(): void;
    public function check(): bool;
    public function guest(): bool;
    public function user(): mixed;
    public function id(): mixed;
    public function validate(array $credentials): bool;
    
    // Session-specific
    public function attemptWithRemember(array $credentials, bool $remember = false): mixed;
    public function loginById(mixed $id): mixed;
    public function setRequest(RequestInterface $request): void;
    public function setSession(SessionInterface $session): void;
}
```

**Session-specific Methods:**

| Method | Return Type | Description |
|--------|-------------|-------------|
| `attemptWithRemember(array, bool)` | `mixed` | Authenticate with remember me option |
| `loginById(mixed)` | `mixed` | Log in a user by their ID |
| `setRequest(RequestInterface)` | `void` | Set the HTTP request instance |
| `setSession(SessionInterface)` | `void` | Set the session instance |

**Configuration:**

```php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
],
```

---

### JwtGuard

JWT-based authentication for APIs. Implements `StatelessGuardInterface`.

```php
namespace Lalaz\Auth\Guards;

class JwtGuard extends BaseGuard implements StatelessGuardInterface
{
    public function __construct(
        JwtEncoder $encoder,
        ?JwtBlacklist $blacklist = null,
        ?UserProviderInterface $provider = null,
        ?RequestInterface $request = null,
        int $ttl = 3600
    );
    
    // GuardInterface methods
    public function getName(): string;
    public function attempt(array $credentials): mixed;
    public function login(mixed $user): void;
    public function logout(): void;
    public function check(): bool;
    public function guest(): bool;
    public function user(): mixed;
    public function id(): mixed;
    public function validate(array $credentials): bool;
    
    // StatelessGuardInterface methods
    public function createToken(mixed $user, array $claims = []): string;
    public function authenticateToken(string $token): mixed;
    public function revokeToken(string $token): bool;
    public function refreshToken(string $token): ?string;
    public function getTokenFromRequest(): ?string;
    
    // JWT-specific
    public function attemptWithTokens(array $credentials): ?array;
    public function createTokenPair(mixed $user, array $claims = []): array;
    public function refreshTokenPair(string $refreshToken): ?array;
    public function setRequest(RequestInterface $request): void;
    public function setHeaderName(string $name): void;
    public function setTokenPrefix(string $prefix): void;
    public function getEncoder(): JwtEncoder;
    public function getToken(): ?string;
}
```

**JWT-specific Methods:**

| Method | Return Type | Description |
|--------|-------------|-------------|
| `attemptWithTokens(array)` | `?array` | Authenticate and return token pair |
| `createTokenPair(mixed, array)` | `array` | Create access + refresh tokens |
| `refreshTokenPair(string)` | `?array` | Refresh both tokens |
| `getToken()` | `?string` | Get current JWT token |
| `getEncoder()` | `JwtEncoder` | Get the JWT encoder instance |

**Token Pair Response:**

```php
[
    'access_token' => 'eyJ...',
    'refresh_token' => 'eyJ...',
    'token_type' => 'Bearer',
    'expires_in' => 3600,
]
```

**Configuration:**

```php
'guards' => [
    'api' => [
        'driver' => 'jwt',
        'provider' => 'users',
    ],
],
```

---

### ApiKeyGuard

API key authentication for services. Implements `StatelessGuardInterface`.

```php
namespace Lalaz\Auth\Guards;

class ApiKeyGuard extends BaseGuard implements StatelessGuardInterface
{
    public function __construct(
        ?UserProviderInterface $provider = null,
        ?RequestInterface $request = null
    );
    
    // GuardInterface methods
    public function getName(): string;
    public function attempt(array $credentials): mixed;
    public function login(mixed $user): void;
    public function logout(): void;
    public function check(): bool;
    public function guest(): bool;
    public function user(): mixed;
    public function id(): mixed;
    public function validate(array $credentials): bool;
    
    // StatelessGuardInterface methods
    public function createToken(mixed $user, array $claims = []): string;
    public function authenticateToken(string $token): mixed;
    public function revokeToken(string $token): bool;
    public function refreshToken(string $token): ?string;
    public function getTokenFromRequest(): ?string;
    
    // ApiKey-specific
    public function generateApiKey(string $prefix = 'lz'): array;
    public function hashApiKey(string $apiKey): string;
    public function setRequest(RequestInterface $request): void;
    public function setHeaderName(string $name): void;
    public function setQueryParam(string $param): void;
    public function getApiKey(): ?string;
    public static function isValidFormat(string $apiKey): bool;
}
```

**API Key-specific Methods:**

| Method | Return Type | Description |
|--------|-------------|-------------|
| `generateApiKey(string)` | `array` | Generate key with hash: `['key' => ..., 'hash' => ...]` |
| `hashApiKey(string)` | `string` | Hash an API key for storage |
| `getApiKey()` | `?string` | Get the current API key |
| `isValidFormat(string)` | `bool` | Validate API key format (static) |

**Note:** The provider should implement `ApiKeyProviderInterface` for `retrieveByApiKey()` method.

**Configuration:**

```php
'guards' => [
    'service' => [
        'driver' => 'api-key',
        'provider' => 'services',
        'header' => 'X-API-Key',    // Optional
        'query_param' => 'api_key', // Optional
    ],
],
```

---

## Providers

### ModelUserProvider

Full-featured provider for ORM models. Implements multiple interfaces.

```php
namespace Lalaz\Auth\Providers;

class ModelUserProvider implements 
    UserProviderInterface, 
    RememberTokenProviderInterface, 
    ApiKeyProviderInterface
{
    public function __construct(
        string $model, 
        ?PasswordHasherInterface $hasher = null
    );
    
    // UserProviderInterface
    public function retrieveById(mixed $identifier): mixed;
    public function retrieveByCredentials(array $credentials): mixed;
    public function validateCredentials(mixed $user, array $credentials): bool;
    
    // RememberTokenProviderInterface
    public function retrieveByToken(mixed $identifier, string $token): mixed;
    public function updateRememberToken(mixed $user, string $token): void;
    
    // ApiKeyProviderInterface
    public function retrieveByApiKey(string $apiKey): mixed;
    
    // Additional methods
    public function passwordNeedsRehash(mixed $user): bool;
    public function hashPassword(string $password): string;
    public function getHasher(): PasswordHasherInterface;
    public function setHasher(PasswordHasherInterface $hasher): self;
    public function getModel(): string;
    public function setModel(string $model): self;
}
```

**Additional Methods:**

| Method | Return Type | Description |
|--------|-------------|-------------|
| `passwordNeedsRehash(mixed)` | `bool` | Check if password needs rehashing |
| `hashPassword(string)` | `string` | Hash a plain text password |
| `getHasher()` | `PasswordHasherInterface` | Get the password hasher |
| `setHasher(PasswordHasherInterface)` | `self` | Set the password hasher |
| `retrieveByToken(mixed, string)` | `mixed` | Find user by remember token |
| `retrieveByApiKey(string)` | `mixed` | Find user by API key |

**Configuration:**

```php
'providers' => [
    'users' => [
        'driver' => 'model',
        'model' => \App\Models\User::class,
    ],
],
```

---

### GenericUserProvider

Flexible provider using callbacks. Implements multiple interfaces.

```php
namespace Lalaz\Auth\Providers;

class GenericUserProvider implements 
    UserProviderInterface, 
    RememberTokenProviderInterface, 
    ApiKeyProviderInterface
{
    public function __construct(?PasswordHasherInterface $hasher = null);
    
    // Callback setters
    public function setByIdCallback(Closure $callback): self;
    public function setByCredentialsCallback(Closure $callback): self;
    public function setValidateCallback(Closure $callback): self;
    public function setByTokenCallback(Closure $callback): self;
    public function setByApiKeyCallback(Closure $callback): self;
    public function setUpdateTokenCallback(Closure $callback): self;
    
    // UserProviderInterface
    public function retrieveById(mixed $identifier): mixed;
    public function retrieveByCredentials(array $credentials): mixed;
    public function validateCredentials(mixed $user, array $credentials): bool;
    
    // RememberTokenProviderInterface
    public function retrieveByToken(mixed $identifier, string $token): mixed;
    public function updateRememberToken(mixed $user, string $token): void;
    
    // ApiKeyProviderInterface
    public function retrieveByApiKey(string $apiKey): mixed;
    
    // Additional methods
    public function passwordNeedsRehash(mixed $user): bool;
    public function hashPassword(string $password): string;
    public function getHasher(): PasswordHasherInterface;
    public function setHasher(PasswordHasherInterface $hasher): self;
    
    // Factory method
    public static function create(
        Closure $byId, 
        Closure $byCredentials, 
        ?PasswordHasherInterface $hasher = null
    ): self;
}
```

**Usage:**

```php
// Using factory method
$provider = GenericUserProvider::create(
    byId: fn($id) => User::find($id),
    byCredentials: fn($creds) => User::where('email', $creds['email'])->first()
);

// Using fluent setters
$provider = (new GenericUserProvider())
    ->setByIdCallback(fn($id) => User::find($id))
    ->setByCredentialsCallback(fn($creds) => User::findByEmail($creds['email']))
    ->setByApiKeyCallback(fn($key) => ApiClient::findByKey($key));
```

**Configuration:**

```php
'providers' => [
    'custom' => [
        'driver' => 'generic',
        // Callbacks are set programmatically
    ],
],
```

---

## Middlewares

### AuthenticationMiddleware

Requires authentication to proceed.

```php
namespace Lalaz\Auth\Middlewares;

class AuthenticationMiddleware implements MiddlewareInterface
{
    public function __construct(
        string $guard = 'web',
        ?string $loginUrl = null,
        ?AuthManager $authManager = null,
        ?AuthContext $authContext = null,
        ?SessionInterface $session = null
    );
    
    public function handle(RequestInterface $req, ResponseInterface $res, callable $next): mixed;
    public function getGuardName(): string;
    
    // Factory methods
    public static function guard(string $guard, ?string $loginUrl = null): self;
    public static function session(?string $loginUrl = null): self;
    public static function web(?string $loginUrl = null): self;
    public static function jwt(): self;
    public static function api(): self;
    public static function apiKey(): self;
    public static function redirectTo(string $loginUrl, string $guard = 'web'): self;
    public static function strict(string $guard = 'web'): self;
    
    // Dependency injection setters
    public function setAuthManager(AuthManager $authManager): self;
    public function setAuthContext(AuthContext $authContext): self;
    public function setSession(SessionInterface $session): self;
}
```

**Usage:**

```php
// Web routes - redirect on failure
AuthenticationMiddleware::web('/login');
AuthenticationMiddleware::session('/login');

// API routes - return 401 JSON
AuthenticationMiddleware::jwt();
AuthenticationMiddleware::api();
AuthenticationMiddleware::apiKey();

// Custom guard
AuthenticationMiddleware::guard('admin', '/admin/login');

// Strict mode (always throw 401)
AuthenticationMiddleware::strict('api');
```

---

### AuthorizationMiddleware

Requires specific roles. User must have at least one of the required roles.

```php
namespace Lalaz\Auth\Middlewares;

class AuthorizationMiddleware implements MiddlewareInterface
{
    public function __construct(
        array $requiredRoles = [],
        ?string $guard = null,
        ?AuthContext $authContext = null
    );
    
    public function handle(RequestInterface $req, ResponseInterface $res, callable $next): mixed;
    public function forGuard(string $guard): self;
    public function setAuthContext(AuthContext $authContext): self;
    
    // Factory methods
    public static function requireRoles(string ...$roles): self;
    public static function admin(): self;
    public static function moderator(): self;
    public static function guard(string $guard, string ...$roles): self;
}
```

**Usage:**

```php
// Single role
AuthorizationMiddleware::requireRoles('admin');

// Any of these roles
AuthorizationMiddleware::requireRoles('admin', 'editor');

// Preset shortcuts
AuthorizationMiddleware::admin();      // Requires 'admin' role
AuthorizationMiddleware::moderator();  // Requires 'admin' or 'moderator' role

// With specific guard
AuthorizationMiddleware::guard('api', 'admin', 'super-admin');

// Fluent guard setting
AuthorizationMiddleware::requireRoles('admin')->forGuard('api');
```

---

### PermissionMiddleware

Requires specific permissions.

```php
namespace Lalaz\Auth\Middlewares;

class PermissionMiddleware implements MiddlewareInterface
{
    public function __construct(
        array $permissions = [],
        ?string $redirectUrl = null,
        bool $requireAll = false,
        ?string $guard = null,
        ?AuthContext $authContext = null
    );
    
    public function handle(RequestInterface $req, ResponseInterface $res, callable $next): mixed;
    public function forGuard(string $guard): self;
    public function setAuthContext(AuthContext $authContext): self;
    
    // Factory methods
    public static function any(string ...$permissions): self;
    public static function all(string ...$permissions): self;
    public static function redirectOnFailure(array $permissions, string $redirectUrl): self;
    public static function guard(string $guard, string ...$permissions): self;
}
```

**Usage:**

```php
// Any of these permissions (OR)
PermissionMiddleware::any('posts.create', 'posts.edit');

// All of these permissions (AND)
PermissionMiddleware::all('posts.edit', 'posts.publish');

// With redirect on failure
PermissionMiddleware::redirectOnFailure(['admin.access'], '/unauthorized');

// With specific guard
PermissionMiddleware::guard('api', 'users.manage');

// Fluent guard setting
PermissionMiddleware::any('posts.create')->forGuard('api');
```

---

## JWT Classes

### JwtEncoder

Encodes and decodes JWT tokens using configurable signing algorithms.

```php
namespace Lalaz\Auth\Jwt;

class JwtEncoder
{
    public function __construct(
        JwtSignerInterface|string $signerOrSecret,
        int $expiration = 3600,
        int $refreshExpiration = 604800,
        string $issuer = 'lalaz'
    );
    
    // Encoding
    public function encode(array $payload, ?int $expiration = null): string;
    public function createAccessToken(mixed $userId, array $claims = []): string;
    public function createRefreshToken(mixed $userId): string;
    
    // Decoding & Validation
    public function decode(string $token): ?array;
    public function validate(string $token): bool;
    public function verify(string $token): bool;
    public function getClaims(string $token): ?array;
    
    // Token inspection
    public function getSubject(string $token): ?string;
    public function getTokenType(string $token): ?string;
    public function getJti(string $token): ?string;
    public function getExpiration(string $token): ?int;
    public function isRefreshToken(string $token): bool;
    
    // Signer access
    public function getSigner(): JwtSignerInterface;
    public function getAlgorithm(): string;
}
```

**Methods:**

| Method | Parameters | Return Type | Description |
|--------|------------|-------------|-------------|
| `encode` | `array $payload, ?int $exp` | `string` | Create JWT from payload |
| `decode` | `string $token` | `?array` | Decode and validate JWT (null if invalid/expired) |
| `validate` | `string $token` | `bool` | Check if token is valid |
| `verify` | `string $token` | `bool` | Verify signature only (ignores expiration) |
| `getClaims` | `string $token` | `?array` | Get claims without validation |
| `createAccessToken` | `mixed $userId, array $claims` | `string` | Create access token |
| `createRefreshToken` | `mixed $userId` | `string` | Create refresh token |
| `isRefreshToken` | `string $token` | `bool` | Check if token is a refresh token |

**Usage:**

```php
// With secret string (creates HmacSha256Signer internally)
$encoder = new JwtEncoder('your-secret-key');

// With explicit signer
$signer = new HmacSha256Signer('your-secret-key');
$encoder = new JwtEncoder($signer, expiration: 3600);

// Create tokens
$accessToken = $encoder->createAccessToken($userId, ['role' => 'admin']);
$refreshToken = $encoder->createRefreshToken($userId);

// Decode and validate
$payload = $encoder->decode($token);
if ($payload !== null) {
    $userId = $payload['sub'];
}
```

---

### JwtBlacklist

Stores blacklisted JWT tokens in memory.

```php
namespace Lalaz\Auth\Jwt;

class JwtBlacklist
{
    public function add(string $tokenOrJti, ?int $expiresAt = null): void;
    public function has(string $tokenOrJti): bool;
    public function isBlacklisted(string $jti): bool;
    public function remove(string $tokenOrJti): void;
    public function clear(): void;
    public function cleanup(): void;
}
```

| Method | Description |
|--------|-------------|
| `add(string, ?int)` | Add token/JTI to blacklist with optional expiration |
| `has(string)` | Check if token/JTI is blacklisted |
| `isBlacklisted(string)` | Alias for has() |
| `remove(string)` | Remove token/JTI from blacklist |
| `clear()` | Clear all blacklisted tokens |
| `cleanup()` | Remove expired entries |

---

### Signers

Available JWT signing algorithms. All implement `JwtSignerInterface`.

```php
namespace Lalaz\Auth\Jwt\Signers;

use Lalaz\Auth\Contracts\JwtSignerInterface;

// HMAC-SHA256 (symmetric)
class HmacSha256Signer implements JwtSignerInterface
{
    public function __construct(string $secret);
    public function getAlgorithm(): string;  // Returns 'HS256'
    public function sign(string $data): string;
    public function verify(string $data, string $signature): bool;
}

// HMAC-SHA384 (symmetric)
class HmacSha384Signer implements JwtSignerInterface
{
    public function __construct(string $secret);
    public function getAlgorithm(): string;  // Returns 'HS384'
    public function sign(string $data): string;
    public function verify(string $data, string $signature): bool;
}

// HMAC-SHA512 (symmetric)
class HmacSha512Signer implements JwtSignerInterface
{
    public function __construct(string $secret);
    public function getAlgorithm(): string;  // Returns 'HS512'
    public function sign(string $data): string;
    public function verify(string $data, string $signature): bool;
}

// RSA-SHA256 (asymmetric)
class RsaSha256Signer implements JwtSignerInterface
{
    public function __construct(
        ?string $privateKey = null,
        ?string $publicKey = null,
        ?string $passphrase = null
    );
    public function getAlgorithm(): string;  // Returns 'RS256'
    public function sign(string $data): string;
    public function verify(string $data, string $signature): bool;
    public static function generateKeyPair(int $bits = 2048): array;
}
```

**Usage:**

```php
// HMAC signers (symmetric - same key for sign and verify)
$signer = new HmacSha256Signer('your-secret-key');
$signer = new HmacSha384Signer('your-secret-key');
$signer = new HmacSha512Signer('your-secret-key');

// RSA signer (asymmetric)
// For signing only (needs private key)
$signer = new RsaSha256Signer(privateKey: $privateKeyPem);

// For verification only (needs public key)
$signer = new RsaSha256Signer(publicKey: $publicKeyPem);

// For both signing and verification
$signer = new RsaSha256Signer(
    privateKey: $privateKeyPem,
    publicKey: $publicKeyPem,
    passphrase: 'optional-passphrase'
);

// Generate new key pair for testing
$keys = RsaSha256Signer::generateKeyPair(2048);
// Returns: ['privateKey' => '...', 'publicKey' => '...']
```

---

## Helper Functions

Global helper functions in the root namespace (no import required).

### auth()

```php
function auth(?string $guard = null): AuthManager|GuardContext|null
```

Get the auth manager or a guard-scoped context.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `$guard` | `?string` | `null` | Guard name (null = return AuthManager) |

```php
// Get auth manager
$manager = auth();
$user = auth()->user();
$isLoggedIn = auth()->check();

// Get guard-scoped context
$apiUser = auth('api')->user();
$isApiAuth = auth('api')->check();
```

---

### user()

```php
function user(?string $guard = null): mixed
```

Get the authenticated user.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `$guard` | `?string` | `null` | Guard name (null for current guard) |

```php
$user = user();          // Current guard
$apiUser = user('api');  // API guard
```

---

### auth_context()

```php
function auth_context(): ?AuthContext
```

Get the AuthContext instance directly.

```php
$context = auth_context();

if ($context->check()) {
    // Authenticated
}

if ($context->hasRole('admin')) {
    // Is admin
}
```

---

### authenticated()

```php
function authenticated(?string $guard = null): bool
```

Check if a user is authenticated.

```php
if (authenticated()) {
    // User is logged in
}

if (authenticated('api')) {
    // API user is authenticated
}
```

---

### guest()

```php
function guest(?string $guard = null): bool
```

Check if no user is authenticated.

```php
if (guest()) {
    // No user logged in
}
```

---

## Exceptions

### AuthException

Base exception for authentication errors.

```php
namespace Lalaz\Auth\Exceptions;

class AuthException extends \Exception
{
    public static function invalidCredentials(): self;
    public static function unauthenticated(): self;
    public static function unauthorized(): self;
}
```

### JwtException

JWT-specific errors.

```php
namespace Lalaz\Auth\Jwt\Exceptions;

class JwtException extends \Exception
{
    public static function expired(): self;
    public static function invalid(): self;
    public static function blacklisted(): self;
    public static function malformed(): self;
}
```

---

## Configuration Reference

Complete configuration structure:

```php
<?php
// config/auth.php

return [
    // Default guard name
    'default' => 'web',
    
    // Guard definitions
    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
            'session_key' => 'auth_user_id',
        ],
        'api' => [
            'driver' => 'jwt',
            'provider' => 'users',
        ],
        'admin' => [
            'driver' => 'session',
            'provider' => 'admins',
            'session_key' => 'admin_auth_id',
        ],
        'service' => [
            'driver' => 'api-key',
            'provider' => 'services',
            'header' => 'X-API-Key',
            'query_param' => 'api_key',
        ],
    ],
    
    // Provider definitions
    'providers' => [
        'users' => [
            'driver' => 'model',
            'model' => \App\Models\User::class,
        ],
        'admins' => [
            'driver' => 'model',
            'model' => \App\Models\Admin::class,
        ],
        'services' => [
            'driver' => 'generic',
            'resolver' => [\App\Services\ApiKeyService::class, 'findByKey'],
        ],
    ],
    
    // JWT settings
    'jwt' => [
        'algorithm' => 'HS256',
        'secret' => env('JWT_SECRET'),
        'ttl' => 60,
        'refresh_ttl' => 20160,
        'issuer' => env('APP_URL'),
        'audience' => env('APP_NAME'),
        'blacklist_enabled' => true,
        'blacklist_grace_period' => 30,
    ],
    
    // Session settings (for session guards)
    'session' => [
        'lifetime' => 120,
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ],
];
```
