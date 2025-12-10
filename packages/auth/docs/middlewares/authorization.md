# AuthorizationMiddleware

The `AuthorizationMiddleware` checks if authenticated users have the required roles or permissions to access a route.

## Quick Start

```php
use Lalaz\Auth\Middlewares\AuthorizationMiddleware;

// Require specific role
$router->middleware(AuthorizationMiddleware::requireRoles('admin'));

// Require any of multiple roles
$router->middleware(AuthorizationMiddleware::requireAnyRole(['admin', 'moderator']));

// Require specific permission
$router->middleware(AuthorizationMiddleware::requirePermissions('users.delete'));
```

## Factory Methods

### requireRoles()

User must have ALL specified roles:

```php
public static function requireRoles(string ...$roles): self
```

```php
// Single role
AuthorizationMiddleware::requireRoles('admin');

// Multiple roles (user must have ALL)
AuthorizationMiddleware::requireRoles('admin', 'verified');
```

### requireAnyRole()

User must have AT LEAST ONE of the specified roles:

```php
public static function requireAnyRole(array $roles): self
```

```php
// User must have admin OR moderator
AuthorizationMiddleware::requireAnyRole(['admin', 'moderator']);
```

### requirePermissions()

User must have ALL specified permissions:

```php
public static function requirePermissions(string ...$permissions): self
```

```php
// Single permission
AuthorizationMiddleware::requirePermissions('posts.create');

// Multiple permissions (user must have ALL)
AuthorizationMiddleware::requirePermissions('posts.create', 'posts.publish');
```

### requireAnyPermission()

User must have AT LEAST ONE of the specified permissions:

```php
public static function requireAnyPermission(array $permissions): self
```

```php
// User must have either permission
AuthorizationMiddleware::requireAnyPermission(['posts.edit', 'posts.delete']);
```

## Usage Examples

### Admin-Only Area

```php
$router->group('/admin', function ($router) {
    $router->get('/', [AdminController::class, 'dashboard']);
    $router->get('/users', [AdminController::class, 'users']);
    $router->get('/settings', [AdminController::class, 'settings']);
})->middleware([
    AuthenticationMiddleware::web('/login'),
    AuthorizationMiddleware::requireRoles('admin'),
]);
```

### Moderator or Admin Access

```php
$router->group('/moderation', function ($router) {
    $router->get('/reports', [ModerationController::class, 'reports']);
    $router->post('/ban/{id}', [ModerationController::class, 'ban']);
})->middleware([
    AuthenticationMiddleware::web('/login'),
    AuthorizationMiddleware::requireAnyRole(['admin', 'moderator']),
]);
```

### Permission-Based Access

```php
$router->group('/posts', function ($router) {
    // View posts - basic permission
    $router->get('/', [PostController::class, 'index'])
        ->middleware(AuthorizationMiddleware::requirePermissions('posts.view'));
    
    // Create posts - elevated permission
    $router->post('/', [PostController::class, 'store'])
        ->middleware(AuthorizationMiddleware::requirePermissions('posts.create'));
    
    // Delete posts - admin permission
    $router->delete('/{id}', [PostController::class, 'destroy'])
        ->middleware(AuthorizationMiddleware::requirePermissions('posts.delete'));
        
})->middleware(AuthenticationMiddleware::web('/login'));
```

### Combined Role and Permission

```php
// Must be admin AND have special permission
$router->post('/system/reset', [SystemController::class, 'reset'])
    ->middleware([
        AuthenticationMiddleware::web('/login'),
        AuthorizationMiddleware::requireRoles('admin'),
        AuthorizationMiddleware::requirePermissions('system.reset'),
    ]);
```

### API Authorization

```php
$router->group('/api/v1/admin', function ($router) {
    $router->get('/users', [ApiUserController::class, 'index']);
    $router->delete('/users/{id}', [ApiUserController::class, 'destroy']);
})->middleware([
    AuthenticationMiddleware::jwt(),
    AuthorizationMiddleware::requireRoles('admin'),
]);
```

## Response Behavior

### Web Routes (403 Page)

```php
// Unauthorized access to admin area
// Returns: HTTP 403 Forbidden with error page
```

### API Routes (JSON 403)

```json
{
    "error": "Forbidden",
    "message": "Insufficient permissions"
}
```

### Custom Error Response

```php
class CustomAuthorizationMiddleware extends AuthorizationMiddleware
{
    protected function respondToUnauthorized($request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to access this resource',
                    'required' => $this->requiredRoles,
                ],
            ], 403);
        }
        
        // Flash message and redirect
        session()->flash('error', 'You do not have access to this area');
        return response()->redirect('/dashboard');
    }
}
```

## Working with Roles

### Define Roles on User Model

```php
class User extends Model implements AuthenticatableInterface
{
    public function getRoles(): array
    {
        // Simple: Single role column
        return [$this->role];
        
        // Or: JSON column
        // return json_decode($this->roles ?? '[]', true);
        
        // Or: From relationship
        // return $this->roles()->pluck('name')->toArray();
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getRoles());
    }
}
```

### Role Hierarchy

Implement role hierarchy in your User model:

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
        
        // Direct role check
        if (in_array($role, $userRoles)) {
            return true;
        }
        
        // Check inherited roles
        foreach ($userRoles as $userRole) {
            $inherited = self::$roleHierarchy[$userRole] ?? [];
            if (in_array($role, $inherited)) {
                return true;
            }
        }
        
        return false;
    }
}
```

Now `admin` automatically has `moderator` and `user` roles:

```php
$admin = User::find(1);  // Has 'admin' role

$admin->hasRole('admin');     // true
$admin->hasRole('moderator'); // true (inherited)
$admin->hasRole('user');      // true (inherited)
```

## Working with Permissions

### Define Permissions on User Model

```php
class User extends Model implements AuthenticatableInterface
{
    public function getPermissions(): array
    {
        // Direct permissions from JSON column
        $direct = json_decode($this->permissions ?? '[]', true);
        
        // Permissions from roles
        $fromRoles = $this->getPermissionsFromRoles();
        
        return array_unique(array_merge($direct, $fromRoles));
    }

    private function getPermissionsFromRoles(): array
    {
        $rolePermissions = [
            'admin' => ['*'],  // All permissions
            'editor' => [
                'posts.view', 'posts.create', 'posts.edit', 'posts.delete',
                'comments.view', 'comments.moderate',
            ],
            'author' => [
                'posts.view', 'posts.create', 'posts.edit',
                'comments.view', 'comments.create',
            ],
            'user' => [
                'posts.view',
                'comments.view', 'comments.create',
            ],
        ];

        $permissions = [];
        
        foreach ($this->getRoles() as $role) {
            $perms = $rolePermissions[$role] ?? [];
            $permissions = array_merge($permissions, $perms);
        }
        
        return $permissions;
    }

    public function hasPermission(string $permission): bool
    {
        $permissions = $this->getPermissions();
        
        // Wildcard check
        if (in_array('*', $permissions)) {
            return true;
        }
        
        // Exact match
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
}
```

### Permission Wildcards

```php
// User with 'posts.*' permission
$editor->hasPermission('posts.view');    // true
$editor->hasPermission('posts.create');  // true
$editor->hasPermission('posts.delete');  // true
$editor->hasPermission('users.view');    // false

// Admin with '*' permission
$admin->hasPermission('posts.view');     // true
$admin->hasPermission('users.delete');   // true
$admin->hasPermission('anything');       // true
```

## Route Patterns

### Layered Authorization

```php
// All authenticated users
$router->group('/app', function ($router) {
    $router->get('/dashboard', [DashboardController::class, 'index']);
    
    // Editors and above
    $router->group('/posts', function ($router) {
        $router->get('/', [PostController::class, 'index']);
        $router->post('/', [PostController::class, 'store']);
        
        // Only admins can delete
        $router->delete('/{id}', [PostController::class, 'destroy'])
            ->middleware(AuthorizationMiddleware::requireRoles('admin'));
            
    })->middleware(AuthorizationMiddleware::requireAnyRole(['editor', 'admin']));
    
})->middleware(AuthenticationMiddleware::web('/login'));
```

### Resource-Level Authorization

```php
// Posts resource with different permissions per action
$router->group('/posts', function ($router) {
    $router->get('/', [PostController::class, 'index']);
    
    $router->post('/', [PostController::class, 'store'])
        ->middleware(AuthorizationMiddleware::requirePermissions('posts.create'));
    
    $router->put('/{id}', [PostController::class, 'update'])
        ->middleware(AuthorizationMiddleware::requirePermissions('posts.edit'));
    
    $router->delete('/{id}', [PostController::class, 'destroy'])
        ->middleware(AuthorizationMiddleware::requirePermissions('posts.delete'));
        
})->middleware(AuthenticationMiddleware::web('/login'));
```

## In-Controller Authorization

Sometimes you need authorization checks within controllers:

```php
class PostController
{
    public function update($request, $response, $id)
    {
        $post = Post::find($id);
        $user = user();
        
        // Check ownership OR admin role
        if ($post->user_id !== $user->id && !$user->hasRole('admin')) {
            return $response->error('You cannot edit this post', 403);
        }
        
        // ... update logic
    }

    public function feature($request, $response, $id)
    {
        // Check specific permission
        if (!auth_context()->hasPermission('posts.feature')) {
            return $response->error('Cannot feature posts', 403);
        }
        
        // ... feature logic
    }
}
```

### Using Policy Classes

For complex authorization, use policy classes:

```php
// App\Policies\PostPolicy
class PostPolicy
{
    public function view($user, Post $post): bool
    {
        // Anyone can view published posts
        if ($post->is_published) {
            return true;
        }
        
        // Only owner or admin can view drafts
        return $post->user_id === $user->id || $user->hasRole('admin');
    }

    public function update($user, Post $post): bool
    {
        // Owner can update
        if ($post->user_id === $user->id) {
            return true;
        }
        
        // Admin can update any
        return $user->hasRole('admin');
    }

    public function delete($user, Post $post): bool
    {
        // Only admin can delete
        return $user->hasRole('admin');
    }

    public function feature($user, Post $post): bool
    {
        return $user->hasPermission('posts.feature');
    }
}
```

```php
// In controller
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
            return $response->error('Unauthorized', 403);
        }
        
        // ... update logic
    }
}
```

## Testing

### Unit Tests

```php
use PHPUnit\Framework\TestCase;

class AuthorizationMiddlewareTest extends TestCase
{
    public function test_allows_user_with_required_role(): void
    {
        $user = $this->createUser(['role' => 'admin']);
        $context = new AuthContext();
        $context->setUser($user);
        
        $middleware = AuthorizationMiddleware::requireRoles('admin');
        
        $called = false;
        $response = $middleware->handle($request, function ($req) use (&$called) {
            $called = true;
            return response();
        });
        
        $this->assertTrue($called);
    }

    public function test_denies_user_without_required_role(): void
    {
        $user = $this->createUser(['role' => 'user']);
        $context = new AuthContext();
        $context->setUser($user);
        
        $middleware = AuthorizationMiddleware::requireRoles('admin');
        
        $response = $middleware->handle($request, $next);
        
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_allows_any_matching_role(): void
    {
        $user = $this->createUser(['role' => 'moderator']);
        $context = new AuthContext();
        $context->setUser($user);
        
        $middleware = AuthorizationMiddleware::requireAnyRole(['admin', 'moderator']);
        
        $called = false;
        $middleware->handle($request, function ($req) use (&$called) {
            $called = true;
            return response();
        });
        
        $this->assertTrue($called);
    }
}
```

### Integration Tests

```php
class AuthorizationMiddlewareIntegrationTest extends TestCase
{
    public function test_admin_can_access_admin_area(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        
        $response = $this->get('/admin/dashboard');
        
        $response->assertOk();
    }

    public function test_regular_user_cannot_access_admin_area(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $this->actingAs($user);
        
        $response = $this->get('/admin/dashboard');
        
        $response->assertForbidden();
    }

    public function test_user_with_permission_can_access(): void
    {
        $user = User::factory()->create([
            'permissions' => json_encode(['posts.delete']),
        ]);
        $this->actingAs($user);
        
        $response = $this->delete('/posts/1');
        
        $response->assertOk();
    }
}
```

## Troubleshooting

### Authorization Always Fails

**Problem:** User has role but still gets 403.

**Solutions:**
1. Verify `getRoles()` returns expected array
2. Check role names match exactly (case-sensitive)
3. Ensure user is set in AuthContext

```php
// Debug in controller
$user = user();
var_dump($user->getRoles());
var_dump($user->hasRole('admin'));
```

### Permission Check Not Working

**Problem:** User has permission but check fails.

**Solutions:**
1. Verify `getPermissions()` returns expected array
2. Check permission names match exactly
3. Check wildcard logic is correct

```php
// Debug
$user = user();
var_dump($user->getPermissions());
var_dump($user->hasPermission('posts.create'));
```

## Next Steps

- Learn about [PermissionMiddleware](./permission.md) for dedicated permission checks
- Set up [Roles and Permissions](../authorization.md) system
- See [Examples](../examples/web-app.md) for complete implementations
