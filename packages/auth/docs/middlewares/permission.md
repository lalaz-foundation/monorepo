# PermissionMiddleware

The `PermissionMiddleware` provides dedicated permission checking for fine-grained access control.

## Quick Start

```php
use Lalaz\Auth\Middlewares\PermissionMiddleware;

// Require single permission
$router->middleware(PermissionMiddleware::require('posts.create'));

// Require all permissions
$router->middleware(PermissionMiddleware::requireAll(['posts.edit', 'posts.publish']));

// Require any permission
$router->middleware(PermissionMiddleware::requireAny(['posts.view', 'pages.view']));
```

## Factory Methods

### require()

User must have the specified permission:

```php
public static function require(string $permission): self
```

```php
PermissionMiddleware::require('users.create');
PermissionMiddleware::require('posts.delete');
PermissionMiddleware::require('settings.manage');
```

### requireAll()

User must have ALL specified permissions:

```php
public static function requireAll(array $permissions): self
```

```php
// Must have BOTH permissions
PermissionMiddleware::requireAll([
    'posts.create',
    'posts.publish',
]);
```

### requireAny()

User must have AT LEAST ONE of the specified permissions:

```php
public static function requireAny(array $permissions): self
```

```php
// Must have at least one
PermissionMiddleware::requireAny([
    'posts.view',
    'pages.view',
    'articles.view',
]);
```

## Usage Examples

### Basic CRUD Permissions

```php
$router->group('/posts', function ($router) {
    // View posts
    $router->get('/', [PostController::class, 'index'])
        ->middleware(PermissionMiddleware::require('posts.view'));
    
    // Create post
    $router->post('/', [PostController::class, 'store'])
        ->middleware(PermissionMiddleware::require('posts.create'));
    
    // Edit post
    $router->put('/{id}', [PostController::class, 'update'])
        ->middleware(PermissionMiddleware::require('posts.edit'));
    
    // Delete post
    $router->delete('/{id}', [PostController::class, 'destroy'])
        ->middleware(PermissionMiddleware::require('posts.delete'));

})->middleware(AuthenticationMiddleware::web('/login'));
```

### Multi-Step Process

```php
// Publishing requires both edit and publish permissions
$router->post('/posts/{id}/publish', [PostController::class, 'publish'])
    ->middleware([
        AuthenticationMiddleware::web('/login'),
        PermissionMiddleware::requireAll(['posts.edit', 'posts.publish']),
    ]);
```

### Content Type Access

```php
// User can access content if they can view ANY content type
$router->get('/content', [ContentController::class, 'index'])
    ->middleware([
        AuthenticationMiddleware::web('/login'),
        PermissionMiddleware::requireAny([
            'posts.view',
            'pages.view',
            'products.view',
        ]),
    ]);
```

### Admin Features

```php
$router->group('/admin', function ($router) {
    // User management
    $router->get('/users', [AdminController::class, 'users'])
        ->middleware(PermissionMiddleware::require('users.view'));
    
    $router->post('/users', [AdminController::class, 'createUser'])
        ->middleware(PermissionMiddleware::require('users.create'));
    
    $router->delete('/users/{id}', [AdminController::class, 'deleteUser'])
        ->middleware(PermissionMiddleware::require('users.delete'));
    
    // System settings
    $router->get('/settings', [AdminController::class, 'settings'])
        ->middleware(PermissionMiddleware::require('settings.view'));
    
    $router->put('/settings', [AdminController::class, 'updateSettings'])
        ->middleware(PermissionMiddleware::require('settings.manage'));

})->middleware(AuthenticationMiddleware::web('/login'));
```

## Permission Naming Conventions

Use consistent naming for permissions:

### Resource.Action Pattern

```
resource.action
```

**Common Actions:**
- `view` - Read/list resources
- `create` - Create new resources
- `edit` - Update existing resources
- `delete` - Remove resources
- `manage` - Full control

**Examples:**
```
posts.view
posts.create
posts.edit
posts.delete
posts.publish
posts.feature

users.view
users.create
users.edit
users.delete
users.ban

settings.view
settings.manage

reports.view
reports.export
```

### Wildcards

Support wildcards in user permissions:

```php
// In User model
public function hasPermission(string $permission): bool
{
    $permissions = $this->getPermissions();
    
    // All permissions
    if (in_array('*', $permissions)) {
        return true;
    }
    
    // Exact match
    if (in_array($permission, $permissions)) {
        return true;
    }
    
    // Category wildcard
    [$category] = explode('.', $permission);
    if (in_array("{$category}.*", $permissions)) {
        return true;
    }
    
    return false;
}
```

```php
// User with posts.* can:
$user->hasPermission('posts.view');    // ✓
$user->hasPermission('posts.create');  // ✓
$user->hasPermission('posts.delete');  // ✓
$user->hasPermission('users.view');    // ✗

// Admin with * can:
$admin->hasPermission('posts.view');   // ✓
$admin->hasPermission('users.delete'); // ✓
$admin->hasPermission('anything');     // ✓
```

## Combining with Other Middleware

### Authentication + Permission

```php
$router->group('/api', function ($router) {
    $router->delete('/posts/{id}', [ApiPostController::class, 'destroy']);
})->middleware([
    AuthenticationMiddleware::jwt(),
    PermissionMiddleware::require('posts.delete'),
]);
```

### Role + Permission

```php
// Must be editor AND have publish permission
$router->post('/posts/{id}/publish', [PostController::class, 'publish'])
    ->middleware([
        AuthenticationMiddleware::web('/login'),
        AuthorizationMiddleware::requireRoles('editor'),
        PermissionMiddleware::require('posts.publish'),
    ]);
```

### Multiple Permission Checks

```php
// Feature that requires multiple unrelated permissions
$router->post('/report/full', [ReportController::class, 'full'])
    ->middleware([
        AuthenticationMiddleware::web('/login'),
        PermissionMiddleware::requireAll([
            'reports.view',
            'reports.export',
            'users.view',  // Needs to see user data
        ]),
    ]);
```

## In-Controller Permission Checks

For dynamic or resource-specific checks:

```php
class PostController
{
    public function update($request, $response, $id)
    {
        $user = user();
        $post = Post::find($id);
        
        // Check edit permission
        if (!$user->hasPermission('posts.edit')) {
            return $response->error('Cannot edit posts', 403);
        }
        
        // Additional check: can only edit own posts unless admin
        if ($post->user_id !== $user->id && !$user->hasPermission('posts.edit.any')) {
            return $response->error('Cannot edit this post', 403);
        }
        
        // ... update logic
    }

    public function feature($request, $response, $id)
    {
        // Special feature permission
        if (!auth_context()->hasPermission('posts.feature')) {
            return $response->error('Cannot feature posts', 403);
        }
        
        // ... feature logic
    }
}
```

### Using Helper Functions

```php
// Check permission via auth_context()
if (auth_context()->hasPermission('posts.create')) {
    // Can create posts
}

// Check any permission
if (auth_context()->hasAnyPermission(['posts.edit', 'pages.edit'])) {
    // Can edit something
}
```

## Permission System Setup

### Database Schema

```sql
-- Users table with permissions column
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255),
    email VARCHAR(255) UNIQUE,
    password VARCHAR(255),
    role VARCHAR(50) DEFAULT 'user',
    permissions JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Roles table (for more complex systems)
CREATE TABLE roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) UNIQUE,
    permissions JSON
);

-- User roles pivot (for multiple roles per user)
CREATE TABLE user_roles (
    user_id INT,
    role_id INT,
    PRIMARY KEY (user_id, role_id)
);
```

### Simple Permission Model

```php
class User extends Model implements AuthenticatableInterface
{
    // Direct permissions from JSON column
    public function getPermissions(): array
    {
        return json_decode($this->permissions ?? '[]', true);
    }

    public function grantPermission(string $permission): void
    {
        $permissions = $this->getPermissions();
        
        if (!in_array($permission, $permissions)) {
            $permissions[] = $permission;
            $this->permissions = json_encode($permissions);
            $this->save();
        }
    }

    public function revokePermission(string $permission): void
    {
        $permissions = array_filter(
            $this->getPermissions(),
            fn($p) => $p !== $permission
        );
        $this->permissions = json_encode(array_values($permissions));
        $this->save();
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->getPermissions());
    }
}
```

### Role-Based Permissions

```php
class User extends Model implements AuthenticatableInterface
{
    private static array $rolePermissions = [
        'admin' => ['*'],
        'editor' => [
            'posts.*',
            'pages.*',
            'media.*',
        ],
        'author' => [
            'posts.view',
            'posts.create',
            'posts.edit',
            'media.upload',
        ],
        'subscriber' => [
            'posts.view',
            'comments.create',
        ],
    ];

    public function getPermissions(): array
    {
        $permissions = [];
        
        // Get permissions from role
        $rolePerms = self::$rolePermissions[$this->role] ?? [];
        $permissions = array_merge($permissions, $rolePerms);
        
        // Add direct permissions
        $directPerms = json_decode($this->permissions ?? '[]', true);
        $permissions = array_merge($permissions, $directPerms);
        
        return array_unique($permissions);
    }

    public function hasPermission(string $permission): bool
    {
        $permissions = $this->getPermissions();
        
        // Check for wildcard
        if (in_array('*', $permissions)) {
            return true;
        }
        
        // Exact match
        if (in_array($permission, $permissions)) {
            return true;
        }
        
        // Category wildcard (e.g., 'posts.*' matches 'posts.create')
        [$category] = explode('.', $permission);
        if (in_array("{$category}.*", $permissions)) {
            return true;
        }
        
        return false;
    }
}
```

## Response Behavior

### Web Routes

```php
// Unauthorized access
// Returns: HTTP 403 Forbidden
```

Default error page or redirect to custom page.

### API Routes

```json
{
    "error": "Forbidden",
    "message": "You do not have the required permission"
}
```

### Custom Response

```php
class CustomPermissionMiddleware extends PermissionMiddleware
{
    protected function respondToUnauthorized($request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PERMISSION_DENIED',
                    'message' => 'You do not have permission to perform this action',
                    'required' => $this->requiredPermissions,
                ],
            ], 403);
        }
        
        session()->flash('error', 'Permission denied');
        return response()->redirect()->back();
    }
}
```

## Testing

### Unit Tests

```php
use PHPUnit\Framework\TestCase;

class PermissionMiddlewareTest extends TestCase
{
    public function test_allows_user_with_permission(): void
    {
        $user = $this->createUser(['permissions' => ['posts.create']]);
        $context = new AuthContext();
        $context->setUser($user);
        
        $middleware = PermissionMiddleware::require('posts.create');
        
        $called = false;
        $middleware->handle($request, function ($req) use (&$called) {
            $called = true;
            return response();
        });
        
        $this->assertTrue($called);
    }

    public function test_denies_user_without_permission(): void
    {
        $user = $this->createUser(['permissions' => []]);
        $context = new AuthContext();
        $context->setUser($user);
        
        $middleware = PermissionMiddleware::require('posts.create');
        
        $response = $middleware->handle($request, $next);
        
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_requires_all_permissions(): void
    {
        $user = $this->createUser(['permissions' => ['posts.create']]);
        $context = new AuthContext();
        $context->setUser($user);
        
        $middleware = PermissionMiddleware::requireAll([
            'posts.create',
            'posts.publish',  // User doesn't have this
        ]);
        
        $response = $middleware->handle($request, $next);
        
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_requires_any_permission(): void
    {
        $user = $this->createUser(['permissions' => ['posts.view']]);
        $context = new AuthContext();
        $context->setUser($user);
        
        $middleware = PermissionMiddleware::requireAny([
            'posts.view',
            'pages.view',
        ]);
        
        $called = false;
        $middleware->handle($request, function ($req) use (&$called) {
            $called = true;
            return response();
        });
        
        $this->assertTrue($called);
    }

    public function test_wildcard_permission(): void
    {
        $user = $this->createUser(['permissions' => ['posts.*']]);
        $context = new AuthContext();
        $context->setUser($user);
        
        $middleware = PermissionMiddleware::require('posts.delete');
        
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
class PermissionMiddlewareIntegrationTest extends TestCase
{
    public function test_user_can_create_post_with_permission(): void
    {
        $user = User::factory()->create([
            'permissions' => json_encode(['posts.create']),
        ]);
        $this->actingAs($user);
        
        $response = $this->post('/posts', [
            'title' => 'Test Post',
            'content' => 'Content',
        ]);
        
        $response->assertStatus(201);
    }

    public function test_user_cannot_delete_without_permission(): void
    {
        $user = User::factory()->create([
            'permissions' => json_encode(['posts.create']),
        ]);
        $this->actingAs($user);
        
        $response = $this->delete('/posts/1');
        
        $response->assertForbidden();
    }
}
```

## Troubleshooting

### Permission Not Recognized

**Problem:** User has permission but middleware denies access.

**Debug:**
```php
$user = user();
var_dump($user->getPermissions());
var_dump($user->hasPermission('posts.create'));
```

**Solutions:**
1. Check exact permission string (case-sensitive)
2. Verify JSON encoding in database
3. Check wildcard logic implementation

### Middleware Not Running

**Problem:** Route accessible without permission.

**Solutions:**
1. Verify middleware is registered
2. Check route definition includes middleware
3. Ensure authentication middleware runs first

## Next Steps

- Set up complete [Authorization System](../authorization.md)
- See [Web App Example](../examples/web-app.md)
- See [API Example](../examples/api.md)
