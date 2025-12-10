# Glossary

A reference guide to authentication terminology used throughout the Lalaz Auth package.

---

## Core Concepts

### Authentication
The process of verifying **who** a user is. This typically involves checking credentials like email/password, tokens, or API keys.

> "Authentication answers: *Are you who you claim to be?*"

### Authorization
The process of verifying **what** an authenticated user can do. This involves checking roles and permissions.

> "Authorization answers: *Are you allowed to do this?*"

### Credentials
Information provided by a user to prove their identity. Common examples:
- Email + Password
- API Key
- JWT Token
- OAuth Token

---

## Guards

### Guard
A guard defines **how** users are authenticated. It handles the mechanism for validating credentials and maintaining authentication state.

```php
// Using a specific guard
$user = auth()->guard('jwt')->user();
```

| Guard | Best For | State |
|-------|----------|-------|
| SessionGuard | Web applications | Stateful |
| JwtGuard | APIs | Stateless |
| ApiKeyGuard | Service integrations | Stateless |

### SessionGuard
Authenticates users via server-side sessions and cookies. User state is stored on the server.

**When to use:** Traditional web applications with browser-based users.

### JwtGuard
Authenticates users via JSON Web Tokens passed in the `Authorization` header. No server-side state required.

**When to use:** REST APIs, mobile apps, SPAs, microservices.

### ApiKeyGuard
Authenticates requests using a static API key. Simple but effective for service-to-service communication.

**When to use:** Webhooks, third-party integrations, internal services.

### Stateful Authentication
Authentication where the server maintains user state (usually in a session). The server "remembers" who is logged in.

### Stateless Authentication
Authentication where the server does NOT maintain user state. Each request must include all information needed to authenticate (like a JWT token).

---

## Providers

### Provider (UserProvider)
A provider defines **where** user data comes from. It handles retrieving user records and validating credentials against stored data.

```php
// Providers are configured in config/auth.php
'providers' => [
    'users' => [
        'driver' => 'model',
        'model' => App\Models\User::class,
    ],
],
```

### ModelUserProvider
Retrieves users from your database using a Lalaz ORM model. The most common provider type.

**Requires:** A model class implementing the `Authenticatable` trait.

### GenericUserProvider
A flexible provider that uses callbacks to retrieve and validate users. Useful for custom authentication sources.

**Use cases:** LDAP, external APIs, custom databases.

---

## Context

### AuthContext
A per-request container that holds the currently authenticated user and their roles/permissions. Think of it as "who is logged in right now."

```php
// Get the current auth context
$context = auth_context();

// Check user info
$context->user();          // The authenticated user
$context->isAuthenticated(); // true/false
$context->hasRole('admin');  // Check role
```

### GuardContext
An extension of AuthContext that also tracks which guard was used for authentication. Useful in multi-guard setups.

```php
$context = guard_context();
$context->guardName(); // 'jwt', 'web', etc.
```

---

## JWT (JSON Web Tokens)

### JWT (JSON Web Token)
A compact, URL-safe token format for securely transmitting information between parties. In authentication, JWTs prove user identity without server-side sessions.

**Structure:** `header.payload.signature`

```
eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.
eyJzdWIiOiIxMjM0IiwiZXhwIjoxNjcwMDAwMDAwfQ.
SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c
```

### Access Token
A short-lived JWT that grants access to protected resources. Typically expires in 15-60 minutes.

```php
// Returned after successful authentication
$tokens['access_token']  // Use this for API requests
```

### Refresh Token
A longer-lived token used to obtain new access tokens without re-authenticating. Typically expires in days or weeks.

```php
// Use to get a new access token
$newTokens = auth()->guard('jwt')->refreshTokens($refreshToken);
```

### Token Pair
The combination of an access token and refresh token returned after authentication.

```php
$tokens = $jwt->createTokenPair($user);
// Returns: ['access_token' => '...', 'refresh_token' => '...']
```

### Claims
The data encoded in a JWT's payload. Standard claims include:

| Claim | Full Name | Description |
|-------|-----------|-------------|
| `sub` | Subject | The user ID |
| `iat` | Issued At | When the token was created |
| `exp` | Expiration | When the token expires |
| `nbf` | Not Before | Token is invalid before this time |
| `jti` | JWT ID | Unique token identifier |

### Signing
The process of cryptographically securing a JWT to prevent tampering. Lalaz Auth supports:

| Algorithm | Type | Description |
|-----------|------|-------------|
| HS256 | HMAC | Symmetric (shared secret) |
| HS384 | HMAC | Symmetric (longer hash) |
| HS512 | HMAC | Symmetric (longest hash) |
| RS256 | RSA | Asymmetric (public/private keys) |

### Blacklist
A list of revoked tokens that should no longer be accepted, even if they haven't expired. Used for logout, password changes, or security incidents.

```php
// Revoke a token
$jwt->blacklist($token);

// Check if blacklisted
$jwt->isBlacklisted($token); // true/false
```

---

## Middleware

### Middleware
Code that runs before your route handler, used to protect routes and enforce security rules.

### AuthenticationMiddleware
Ensures the user is logged in. Rejects unauthenticated requests.

```php
// Web: Redirects to login page
AuthenticationMiddleware::web('/login');

// API: Returns 401 JSON response
AuthenticationMiddleware::jwt();
```

### AuthorizationMiddleware
Ensures the user has required **roles**. Runs after authentication.

```php
// User must have 'admin' role
AuthorizationMiddleware::requireRoles('admin');

// User must have ANY of these roles
AuthorizationMiddleware::requireRoles('admin', 'moderator');
```

### PermissionMiddleware
Ensures the user has required **permissions**. More granular than role checks.

```php
// Must have this permission
PermissionMiddleware::all('posts.delete');

// Must have ANY of these permissions
PermissionMiddleware::any('posts.edit', 'posts.delete');
```

---

## Roles & Permissions

### Role
A named group of permissions assigned to users. Represents "what kind of user" someone is.

**Examples:** `admin`, `editor`, `subscriber`, `guest`

```php
// Check if user has role
auth_context()->hasRole('admin');

// Check multiple roles (any)
auth_context()->hasAnyRole(['admin', 'editor']);
```

### Permission
A specific action a user is allowed to perform. More granular than roles.

**Examples:** `posts.create`, `posts.edit`, `posts.delete`, `users.manage`

```php
// Check single permission
auth_context()->hasPermission('posts.delete');

// Check all permissions required
auth_context()->hasAllPermissions(['posts.create', 'posts.edit']);
```

### RBAC (Role-Based Access Control)
A security model where permissions are assigned to roles, and roles are assigned to users. Users inherit permissions from their roles.

```
User → Roles → Permissions
```

---

## Traits & Interfaces

### Authenticatable (Trait)
A trait that adds authentication capabilities to your User model. Provides methods for password hashing, remember tokens, etc.

```php
class User extends Model
{
    use Authenticatable;
}
```

### Authorizable (Trait)
A trait that adds authorization capabilities (roles/permissions) to your User model.

```php
class User extends Model
{
    use Authenticatable, Authorizable;
}
```

### GuardInterface
The contract that all guards must implement. Defines methods like `attempt()`, `user()`, `logout()`.

### StatelessGuardInterface
Extended interface for guards that don't use sessions (JWT, API Key).

### UserProviderInterface
The contract that all user providers must implement. Defines how to retrieve and validate users.

---

## Helper Functions

### `auth()`
Returns the AuthManager instance. Use to access guards and perform authentication.

```php
auth()->attempt($credentials);
auth()->guard('jwt')->user();
```

### `user()`
Returns the currently authenticated user, or `null` if not authenticated.

```php
$user = user();
echo $user->name;
```

### `authenticated()`
Returns `true` if a user is currently authenticated.

```php
if (authenticated()) {
    // User is logged in
}
```

### `auth_context()`
Returns the current AuthContext with the authenticated user and roles/permissions.

```php
auth_context()->hasRole('admin');
auth_context()->hasPermission('posts.edit');
```

### `guard_context()`
Returns GuardContext with additional guard information.

```php
guard_context()->guardName(); // Which guard authenticated the user
```

---

## Security Terms

### Hashing
One-way transformation of data (like passwords) that cannot be reversed. Lalaz uses PHP's `password_hash()` with bcrypt.

### Session Fixation
An attack where an attacker sets a victim's session ID. Lalaz regenerates session IDs on login to prevent this.

### CSRF (Cross-Site Request Forgery)
An attack where malicious sites trick users into performing unwanted actions. Use CSRF tokens to prevent.

### Token Expiration
The time limit after which a token is no longer valid. Short expiration = more secure but less convenient.

### Remember Me
A feature that keeps users logged in across browser sessions using a persistent cookie/token.

---

## Configuration Terms

### Driver
The implementation type for a guard or provider. Examples: `session`, `jwt`, `model`, `generic`.

### TTL (Time To Live)
How long something remains valid, typically measured in seconds.

```php
'jwt' => [
    'ttl' => 3600, // Access token: 1 hour
    'refresh_ttl' => 604800, // Refresh token: 7 days
],
```

---

## See Also

- [Core Concepts](./concepts.md) — Detailed explanation of Guards, Providers, and Context
- [Quick Start](./quick-start.md) — Get started in 5 minutes
- [API Reference](./api-reference.md) — Complete method documentation

---

<p align="center">
  <sub>Can't find a term? <a href="https://github.com/lalaz-foundation/framework/issues">Open an issue</a> and we'll add it!</sub>
</p>
