# Helper Functions

Lalaz Auth provides global helper functions for convenient access to authentication state.

## Available Helpers

### auth()

Get the auth manager or a guard-scoped context.

```php
// Get the auth manager
$manager = auth();

// Authenticate a user
$user = auth()->attempt(['email' => $email, 'password' => $password]);

// Get current user
$user = auth()->user();

// Check if authenticated
if (auth()->check()) {
    // User is logged in
}

// Get guard-scoped context
$apiUser = auth('api')->user();
$isApiAuth = auth('api')->check();
```

### user()

Get the currently authenticated user.

```php
// Get user from current guard
$user = user();

// Get user from specific guard
$user = user('api');
$user = user('admin');

// Use user properties
if ($user) {
    echo "Hello, {$user->name}";
}
```

### auth_context()

Get the `AuthContext` instance for checking authentication state and authorization.

```php
$context = auth_context();

// Check authentication
if ($context->check()) {
    // User is authenticated
}

// Check guest
if ($context->guest()) {
    // User is not authenticated
}

// Get user
$user = $context->user();

// Authorization checks
if ($context->hasRole('admin')) {
    // User has admin role
}

if ($context->hasPermission('posts.create')) {
    // User can create posts
}
```

### authenticated()

Check if a user is authenticated.

```php
if (authenticated()) {
    // User is logged in
}

if (authenticated('api')) {
    // API user is authenticated
}
```

### guest()

Check if no user is authenticated.

```php
if (guest()) {
    // No user logged in
}

if (guest('api')) {
    // No API user authenticated
}
```

## Using Helpers in Different Contexts

### In Controllers

```php
<?php

namespace App\Controllers;

class DashboardController
{
    public function index($request, $response)
    {
        // Check authentication
        if (!auth_context()->check()) {
            return $response->redirect('/login');
        }
        
        // Get user data
        $user = user();
        
        return $response->view('dashboard', [
            'user' => $user,
            'isAdmin' => auth_context()->hasRole('admin'),
        ]);
    }

    public function profile($request, $response)
    {
        $user = user();
        
        return $response->json([
            'id' => $user->getAuthIdentifier(),
            'name' => $user->name,
            'roles' => method_exists($user, 'getRoles') ? $user->getRoles() : [],
        ]);
    }
}
```

### In Middleware

```php
<?php

namespace App\Middlewares;

class CustomAuthMiddleware
{
    public function handle($request, $next)
    {
        $context = auth_context();
        
        if (!$context->check()) {
            return response()->redirect('/login');
        }
        
        // Add user to request for downstream use
        $request->setUser(user());
        
        return $next($request);
    }
}
```

### In Views/Templates

```php
<!-- dashboard.php -->
<header>
    <?php if (auth_context()->check()): ?>
        <nav>
            <span>Welcome, <?= htmlspecialchars(user()->name) ?></span>
            
            <?php if (auth_context()->hasRole('admin')): ?>
                <a href="/admin">Admin Panel</a>
            <?php endif; ?>
            
            <?php if (auth_context()->hasPermission('posts.create')): ?>
                <a href="/posts/create">New Post</a>
            <?php endif; ?>
            
            <form action="/logout" method="POST">
                <button type="submit">Logout</button>
            </form>
        </nav>
    <?php else: ?>
        <nav>
            <a href="/login">Login</a>
            <a href="/register">Register</a>
        </nav>
    <?php endif; ?>
</header>
```

### In Models

```php
<?php

namespace App\Models;

use Lalaz\Data\Model;

class Post extends Model
{
    protected static string $tableName = 'posts';

    /**
     * Check if current user owns this post
     */
    public function isOwnedByCurrentUser(): bool
    {
        $currentUser = user();
        if (!$currentUser) {
            return false;
        }
        return $this->user_id === $currentUser->getAuthIdentifier();
    }

    /**
     * Check if current user can edit this post
     */
    public function canEdit(): bool
    {
        if ($this->isOwnedByCurrentUser()) {
            return auth_context()->hasPermission('posts.edit');
        }
        return auth_context()->hasRole('admin');
    }

    /**
     * Scope to posts visible to current user
     */
    public static function visibleToCurrentUser(): array
    {
        $context = auth_context();
        
        if ($context->hasRole('admin')) {
            return static::all();
        }
        
        $currentUser = user();
        if (!$currentUser) {
            return static::where('is_published', '=', true);
        }
        
        return static::query()
            ->where('is_published', '=', true)
            ->orWhere('user_id', '=', $currentUser->getAuthIdentifier())
            ->get();
    }
}
```

### In Services

```php
<?php

namespace App\Services;

class AuditService
{
    public function log(string $action, array $data = []): void
    {
        $user = user();
        
        AuditLog::create([
            'user_id' => $user?->getAuthIdentifier(),
            'action' => $action,
            'data' => json_encode($data),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}

class NotificationService
{
    public function notifyUser(string $message): void
    {
        $user = user();
        if (!$user) {
            return;
        }
        
        Notification::create([
            'user_id' => $user->getAuthIdentifier(),
            'message' => $message,
            'read' => false,
        ]);
    }
}
```

## Helper Function Reference

### auth(?string $guard = null)

| Parameter | Type | Description |
|-----------|------|-------------|
| `$guard` | `?string` | Guard name (optional) |

| Returns | Description |
|---------|-------------|
| `AuthManager\|GuardContext\|null` | Auth manager or guard context |

**Example:**
```php
$manager = auth();
$user = auth()->user();
$isValid = auth()->check();
$apiUser = auth('api')->user();
```

---

### user(?string $guard = null)

| Parameter | Type | Description |
|-----------|------|-------------|
| `$guard` | `?string` | Guard name (optional) |

| Returns | Description |
|---------|-------------|
| `mixed` | Authenticated user or null |

**Example:**
```php
$webUser = user();           // Current guard
$apiUser = user('api');      // API guard
$adminUser = user('admin');  // Admin guard
```

---

### auth_context()

| Returns | Description |
|---------|-------------|
| `AuthContext\|null` | Current authentication context |

**Methods Available:**
| Method | Returns | Description |
|--------|---------|-------------|
| `check(?string)` | `bool` | Is user authenticated? |
| `guest(?string)` | `bool` | Is user a guest? |
| `user(?string)` | `mixed` | Get user |
| `id(?string)` | `mixed` | Get user ID |
| `hasRole(string, ?string)` | `bool` | Check role |
| `hasAnyRole(array, ?string)` | `bool` | Check any role |
| `hasPermission(string, ?string)` | `bool` | Check permission |
| `hasAnyPermission(array, ?string)` | `bool` | Check any permission |

**Example:**
```php
$context = auth_context();

// Authentication
$context->check();
$context->guest();
$context->user();
$context->id();

// Authorization
$context->hasRole('admin');
$context->hasAnyRole(['admin', 'editor']);
$context->hasPermission('posts.create');
$context->hasAnyPermission(['posts.edit', 'posts.delete']);
```

---

### authenticated(?string $guard = null)

| Parameter | Type | Description |
|-----------|------|-------------|
| `$guard` | `?string` | Guard name (optional) |

| Returns | Description |
|---------|-------------|
| `bool` | True if authenticated |

---

### guest(?string $guard = null)

| Parameter | Type | Description |
|-----------|------|-------------|
| `$guard` | `?string` | Guard name (optional) |

| Returns | Description |
|---------|-------------|
| `bool` | True if guest (not authenticated) |

## Importing Helpers

The helper functions are global (in the root namespace) when loaded via the autoloader. No import is required:

```php
// Just use them directly
$user = user();
$context = auth_context();

if (authenticated()) {
    // ...
}
```

## Common Patterns

### Guard Current User Data

```php
$user = user();

// Guard against null
if (!$user) {
    return $response->error('Not authenticated', 401);
}

// Safe access with null coalescing
$name = $user?->name ?? 'Guest';

// Null-safe method calls (PHP 8+)
$userId = $user?->getAuthIdentifier();
```

### Multi-Guard Access

```php
// API controllers
class ApiController
{
    protected function currentUser()
    {
        return user('api');  // Use API guard
    }
}

// Web controllers
class WebController
{
    protected function currentUser()
    {
        return user();  // Default (web) guard
    }
}

// Admin controllers
class AdminController
{
    protected function currentUser()
    {
        return user('admin');  // Admin guard
    }
}
```

### Conditional Logic Based on Auth

```php
class ContentController
{
    public function show($request, $response, $id)
    {
        $content = Content::find($id);
        $context = auth_context();
        
        // Different responses based on auth state
        if ($context->guest()) {
            // Limited view for guests
            return $response->view('content.preview', [
                'title' => $content->title,
                'excerpt' => $content->excerpt,
            ]);
        }
        
        if ($context->hasRole('premium')) {
            // Full view for premium users
            return $response->view('content.full', [
                'content' => $content,
                'bonus' => $content->bonusContent(),
            ]);
        }
        
        // Standard view for regular users
        return $response->view('content.standard', [
            'content' => $content,
        ]);
    }
}
```

### Audit Trail with User Context

```php
class ActivityLogger
{
    public static function log(string $action, $entity, array $changes = []): void
    {
        $user = user();
        
        ActivityLog::create([
            'user_id' => $user?->getAuthIdentifier(),
            'user_name' => $user?->name ?? 'System',
            'action' => $action,
            'entity_type' => get_class($entity),
            'entity_id' => $entity->id ?? null,
            'changes' => json_encode($changes),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'CLI',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}

// Usage
ActivityLogger::log('created', $post, ['title' => $post->title]);
ActivityLogger::log('updated', $user, ['email' => ['old' => $old, 'new' => $new]]);
```

## Testing with Helpers

```php
use PHPUnit\Framework\TestCase;

class HelperTest extends TestCase
{
    public function test_user_returns_null_when_not_authenticated(): void
    {
        $this->assertNull(user());
    }

    public function test_auth_context_guest_when_not_authenticated(): void
    {
        $context = auth_context();
        if ($context) {
            $this->assertTrue($context->guest());
            $this->assertFalse($context->check());
        }
    }

    public function test_user_returns_authenticated_user(): void
    {
        $testUser = $this->createMockUser();
        $this->authenticateUser($testUser);
        
        $user = user();
        
        $this->assertNotNull($user);
        $this->assertEquals(
            $testUser->getAuthIdentifier(), 
            $user->getAuthIdentifier()
        );
    }

    public function test_authenticated_returns_true_when_logged_in(): void
    {
        $this->authenticateUser($this->createMockUser());
        $this->assertTrue(authenticated());
    }

    public function test_guest_returns_true_when_not_logged_in(): void
    {
        $this->assertTrue(guest());
    }
}
```

## Next Steps

- [Core Concepts](./concepts.md) - Understanding Guards and Providers
- [AuthContext](./concepts.md#authcontext) - Deep dive into context
- [Authorization](./authorization.md) - Roles and permissions
- [Examples](./examples/web-app.md) - Complete implementations
