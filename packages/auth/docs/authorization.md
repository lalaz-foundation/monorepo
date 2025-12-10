# Authorization

Authorization determines what authenticated users can do. Lalaz Auth provides role-based and permission-based authorization.

## Concepts

```
Authentication: WHO are you? (Login)
Authorization:  WHAT can you do? (Permissions)
```

### Roles vs Permissions

**Roles** are named groups:
- `admin`, `editor`, `user`
- Easy to understand
- Broad access control

**Permissions** are specific abilities:
- `posts.create`, `users.delete`
- Fine-grained control
- Feature-level access

## Setting Up Authorization

### 1. Implement AuthenticatableInterface

```php
<?php

namespace App\Models;

use Lalaz\Data\Model;
use Lalaz\Auth\Contracts\AuthenticatableInterface;

class User extends Model implements AuthenticatableInterface
{
    protected static string $tableName = 'users';

    // ... authentication methods ...

    public function getRoles(): array
    {
        // Option 1: Single role column
        return [$this->role];
        
        // Option 2: JSON column
        // return json_decode($this->roles ?? '[]', true);
    }

    public function getPermissions(): array
    {
        // Direct permissions
        $direct = json_decode($this->permissions ?? '[]', true);
        
        // Role-based permissions
        $fromRoles = $this->getPermissionsFromRoles();
        
        return array_unique(array_merge($direct, $fromRoles));
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getRoles());
    }

    public function hasPermission(string $permission): bool
    {
        $permissions = $this->getPermissions();
        
        // Wildcard check
        if (in_array('*', $permissions)) {
            return true;
        }
        
        // Direct match
        if (in_array($permission, $permissions)) {
            return true;
        }
        
        // Category wildcard (posts.* matches posts.create)
        [$category] = explode('.', $permission);
        if (in_array("{$category}.*", $permissions)) {
            return true;
        }
        
        return false;
    }

    private function getPermissionsFromRoles(): array
    {
        $rolePermissions = [
            'admin' => ['*'],
            'editor' => ['posts.*', 'comments.*'],
            'author' => ['posts.create', 'posts.edit', 'comments.create'],
            'user' => ['comments.create'],
        ];

        $permissions = [];
        foreach ($this->getRoles() as $role) {
            $permissions = array_merge(
                $permissions,
                $rolePermissions[$role] ?? []
            );
        }
        
        return $permissions;
    }
}
```

### 2. Database Schema

```sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) DEFAULT 'user',
    permissions JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

## Using AuthContext

The `AuthContext` provides authorization checks after authentication.

### Check Roles

```php
use Lalaz\Auth\AuthContext;

$context = auth_context();

// Single role
if ($context->hasRole('admin')) {
    // User is admin
}

// Any of multiple roles
if ($context->hasAnyRole(['admin', 'editor'])) {
    // User is admin OR editor
}

// All roles
if ($context->hasAllRoles(['verified', 'premium'])) {
    // User has both roles
}
```

### Check Permissions

```php
// Single permission
if ($context->hasPermission('posts.delete')) {
    // Can delete posts
}

// Any of multiple permissions
if ($context->hasAnyPermission(['posts.edit', 'posts.delete'])) {
    // Can edit OR delete
}

// All permissions
if ($context->hasAllPermissions(['posts.edit', 'posts.publish'])) {
    // Can edit AND publish
}
```

### Helper Functions

```php
// Get authenticated user
$user = user();

// Get user from specific guard
$user = user('api');

// Get AuthContext
$context = auth_context();

// Check authentication
if (auth_context()->check()) {
    // User is logged in
}
```

## Middleware Authorization

### Role-Based Routes

```php
use Lalaz\Auth\Middlewares\AuthenticationMiddleware;
use Lalaz\Auth\Middlewares\AuthorizationMiddleware;

// Admin only
$router->group('/admin', function ($router) {
    $router->get('/', [AdminController::class, 'dashboard']);
    $router->get('/users', [AdminController::class, 'users']);
})->middleware([
    AuthenticationMiddleware::web('/login'),
    AuthorizationMiddleware::requireRoles('admin'),
]);

// Admin or Moderator
$router->group('/moderation', function ($router) {
    $router->get('/reports', [ModerationController::class, 'index']);
})->middleware([
    AuthenticationMiddleware::web('/login'),
    AuthorizationMiddleware::requireAnyRole(['admin', 'moderator']),
]);
```

### Permission-Based Routes

```php
use Lalaz\Auth\Middlewares\PermissionMiddleware;

$router->group('/posts', function ($router) {
    // Anyone authenticated can view
    $router->get('/', [PostController::class, 'index']);
    
    // Need create permission
    $router->post('/', [PostController::class, 'store'])
        ->middleware(PermissionMiddleware::require('posts.create'));
    
    // Need edit permission
    $router->put('/{id}', [PostController::class, 'update'])
        ->middleware(PermissionMiddleware::require('posts.edit'));
    
    // Need delete permission
    $router->delete('/{id}', [PostController::class, 'destroy'])
        ->middleware(PermissionMiddleware::require('posts.delete'));
        
})->middleware(AuthenticationMiddleware::web('/login'));
```

## Controller Authorization

### In-Controller Checks

```php
class PostController
{
    public function update($request, $response, $id)
    {
        $user = user();
        $post = Post::find($id);
        
        // Check ownership or admin
        if ($post->user_id !== $user->getAuthIdentifier() && !$user->hasRole('admin')) {
            return $response->error('Forbidden', 403);
        }
        
        // Update post...
    }

    public function feature($request, $response, $id)
    {
        // Check specific permission
        if (!auth_context()->hasPermission('posts.feature')) {
            return $response->error('Cannot feature posts', 403);
        }
        
        // Feature post...
    }
}
```

### Using Policies

Create policy classes for complex authorization:

```php
<?php

namespace App\Policies;

class PostPolicy
{
    public function view($user, Post $post): bool
    {
        // Anyone can view published posts
        if ($post->is_published) {
            return true;
        }
        
        // Only author or admin can view drafts
        return $post->user_id === $user->getAuthIdentifier() || $user->hasRole('admin');
    }

    public function create($user): bool
    {
        return $user->hasPermission('posts.create');
    }

    public function update($user, Post $post): bool
    {
        // Author can update own posts
        if ($post->user_id === $user->getAuthIdentifier()) {
            return $user->hasPermission('posts.edit');
        }
        
        // Admin can update any
        return $user->hasRole('admin');
    }

    public function delete($user, Post $post): bool
    {
        // Only admin can delete
        return $user->hasRole('admin');
    }

    public function publish($user, Post $post): bool
    {
        // Must own the post AND have publish permission
        return $post->user_id === $user->getAuthIdentifier() 
            && $user->hasPermission('posts.publish');
    }
}
```

Use in controller:

```php
class PostController
{
    private PostPolicy $policy;

    public function __construct()
    {
        $this->policy = new PostPolicy();
    }

    public function update($request, $response, $id)
    {
        $post = Post::find($id);
        $user = user();
        
        if (!$this->policy->update($user, $post)) {
            return $response->error('Forbidden', 403);
        }
        
        // Update post...
    }
}
```

## View Authorization

Check permissions in templates:

```php
<!-- In PHP templates -->
<?php $context = auth_context(); ?>

<?php if ($context->check()): ?>
    <p>Welcome, <?= user()->name ?></p>
    
    <?php if ($context->hasRole('admin')): ?>
        <a href="/admin">Admin Panel</a>
    <?php endif; ?>
    
    <?php if ($context->hasPermission('posts.create')): ?>
        <a href="/posts/create">New Post</a>
    <?php endif; ?>
<?php else: ?>
    <a href="/login">Login</a>
<?php endif; ?>
```

## Role Hierarchies

Implement role inheritance:

```php
class User extends Model implements AuthenticatableInterface
{
    private static array $roleHierarchy = [
        'super-admin' => ['admin', 'moderator', 'user'],
        'admin' => ['moderator', 'user'],
        'moderator' => ['user'],
        'user' => [],
    ];

    public function hasRole(string $role): bool
    {
        $userRoles = $this->getRoles();
        
        foreach ($userRoles as $userRole) {
            // Direct match
            if ($userRole === $role) {
                return true;
            }
            
            // Inherited role
            $inherited = self::$roleHierarchy[$userRole] ?? [];
            if (in_array($role, $inherited)) {
                return true;
            }
        }
        
        return false;
    }
}
```

Now admins automatically have moderator and user permissions:

```php
$admin = User::find(1);  // Has 'admin' role

$admin->hasRole('admin');      // true
$admin->hasRole('moderator');  // true (inherited)
$admin->hasRole('user');       // true (inherited)
$admin->hasRole('super-admin'); // false
```

## Common Permission Patterns

### Resource Permissions

```
posts.view
posts.create
posts.edit
posts.delete
posts.publish

users.view
users.create
users.edit
users.delete
users.ban

settings.view
settings.manage
```

### Feature Permissions

```
dashboard.access
reports.view
reports.export
analytics.view
billing.manage
```

### Wildcards

```php
// User with 'posts.*' can:
$user->hasPermission('posts.view');    // true
$user->hasPermission('posts.create');  // true
$user->hasPermission('posts.delete');  // true
$user->hasPermission('users.view');    // false

// Admin with '*' can:
$admin->hasPermission('anything');     // true
```

## Managing Roles and Permissions

### Assign Role

```php
// Simple role column
$user->role = 'editor';
$user->save();

// Multiple roles (JSON)
$roles = $user->getRoles();
$roles[] = 'editor';
$user->roles = json_encode(array_unique($roles));
$user->save();
```

### Grant Permission

```php
$permissions = $user->getDirectPermissions();
$permissions[] = 'posts.feature';
$user->permissions = json_encode(array_unique($permissions));
$user->save();
```

### Revoke Permission

```php
$permissions = array_filter(
    $user->getDirectPermissions(),
    fn($p) => $p !== 'posts.feature'
);
$user->permissions = json_encode(array_values($permissions));
$user->save();
```

### Admin Interface

```php
class UserController
{
    public function updateRole($request, $response, $id)
    {
        // Check if current user can manage roles
        if (!auth_context()->hasPermission('users.manage-roles')) {
            return $response->error('Forbidden', 403);
        }
        
        $user = User::find($id);
        $user->role = $request->input('role');
        $user->save();
        
        return $response->json(['message' => 'Role updated']);
    }

    public function updatePermissions($request, $response, $id)
    {
        if (!auth_context()->hasPermission('users.manage-permissions')) {
            return $response->error('Forbidden', 403);
        }
        
        $user = User::find($id);
        $user->permissions = json_encode($request->input('permissions', []));
        $user->save();
        
        return $response->json(['message' => 'Permissions updated']);
    }
}
```

## Testing Authorization

```php
use PHPUnit\Framework\TestCase;

class AuthorizationTest extends TestCase
{
    public function test_admin_has_all_permissions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        
        $this->assertTrue($admin->hasPermission('posts.delete'));
        $this->assertTrue($admin->hasPermission('users.ban'));
        $this->assertTrue($admin->hasPermission('anything'));
    }

    public function test_editor_has_limited_permissions(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        
        $this->assertTrue($editor->hasPermission('posts.create'));
        $this->assertTrue($editor->hasPermission('posts.edit'));
        $this->assertFalse($editor->hasPermission('users.delete'));
    }

    public function test_user_can_access_own_resources(): void
    {
        $user = User::factory()->create();
        $ownPost = Post::factory()->create(['user_id' => $user->id]);
        $otherPost = Post::factory()->create();
        
        $policy = new PostPolicy();
        
        $this->assertTrue($policy->update($user, $ownPost));
        $this->assertFalse($policy->update($user, $otherPost));
    }

    public function test_middleware_blocks_unauthorized_access(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $this->actingAs($user);
        
        $response = $this->get('/admin');
        
        $response->assertForbidden();
    }
}
```

## Next Steps

- [Middleware Overview](./middlewares/index.md) - Protecting routes
- [AuthorizationMiddleware](./middlewares/authorization.md) - Role-based middleware
- [PermissionMiddleware](./middlewares/permission.md) - Permission-based middleware
- [Web App Example](./examples/web-app.md) - Complete implementation
