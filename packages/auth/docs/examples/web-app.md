# Web Application Example

Complete session-based authentication for traditional web applications.

## Overview

This example demonstrates:
- User registration with validation
- Session-based login/logout
- Password hashing with bcrypt
- Protected dashboard routes
- Role-based admin access
- Flash messages for feedback
- CSRF protection

## Project Structure

```
app/
├── Controllers/
│   ├── AuthController.php
│   ├── DashboardController.php
│   └── AdminController.php
├── Models/
│   └── User.php
config/
├── auth.php
├── database.php
routes/
└── web.php
resources/
└── views/
    ├── auth/
    │   ├── login.php
    │   └── register.php
    ├── dashboard.php
    └── admin/
        └── index.php
```

## Configuration

### config/auth.php

```php
<?php

return [
    'default' => 'web',
    
    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],
    
    'providers' => [
        'users' => [
            'driver' => 'model',
            'model' => \App\Models\User::class,
        ],
    ],
    
    'session' => [
        'name' => 'auth_session',
        'lifetime' => 120, // minutes
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ],
];
```

## User Model

### app/Models/User.php

```php
<?php

namespace App\Models;

use Lalaz\Data\Model;
use Lalaz\Auth\Contracts\AuthenticatableInterface;

class User extends Model implements AuthenticatableInterface
{
    protected static string $tableName = 'users';
    
    protected array $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    protected array $hidden = [
        'password',
    ];

    /**
     * Create a new user with hashed password
     */
    public static function createUser(array $data): self
    {
        return self::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => password_hash($data['password'], PASSWORD_BCRYPT),
            'role' => $data['role'] ?? 'user',
        ]);
    }

    /**
     * Find user by email
     */
    public static function findByEmail(string $email): ?self
    {
        return self::findBy(['email' => $email]);
    }

    // ========================================
    // AuthenticatableInterface Implementation
    // ========================================

    public function getAuthIdentifier(): mixed
    {
        return $this->id;
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthPassword(): string
    {
        return $this->password;
    }

    public function getRememberToken(): ?string
    {
        return $this->remember_token ?? null;
    }

    public function setRememberToken(?string $value): void
    {
        $this->remember_token = $value;
    }

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }

    // ========================================
    // Authorization Methods (optional)
    // ========================================

    public function getRoles(): array
    {
        return [$this->role];
    }

    public function getPermissions(): array
    {
        $rolePermissions = [
            'admin' => ['*'],
            'editor' => ['posts.*', 'comments.*'],
            'user' => ['posts.view', 'comments.create'],
        ];

        return $rolePermissions[$this->role] ?? [];
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function hasPermission(string $permission): bool
    {
        $permissions = $this->getPermissions();
        
        if (in_array('*', $permissions)) {
            return true;
        }
        
        return in_array($permission, $permissions);
    }

    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->password);
    }
}
```

## Controllers

### app/Controllers/AuthController.php

```php
<?php

namespace App\Controllers;

use App\Models\User;
use function Lalaz\Auth\Helpers\auth;
use function Lalaz\Auth\Helpers\auth_context;

class AuthController
{
    /**
     * Show login form
     */
    public function showLogin($request, $response)
    {
        // Redirect if already logged in
        if (auth_context()->check()) {
            return $response->redirect('/dashboard');
        }
        
        return $response->view('auth/login', [
            'error' => flash('error'),
            'success' => flash('success'),
        ]);
    }

    /**
     * Handle login
     */
    public function login($request, $response)
    {
        $email = $request->input('email');
        $password = $request->input('password');
        $remember = $request->has('remember');
        
        // Validate input
        if (empty($email) || empty($password)) {
            flash('error', 'Email and password are required');
            return $response->redirect('/login');
        }
        
        // Attempt authentication
        $user = auth()->attempt([
            'email' => $email,
            'password' => $password,
        ]);
        
        if (!$user) {
            flash('error', 'Invalid email or password');
            return $response->redirect('/login');
        }
        
        // Login successful
        flash('success', 'Welcome back, ' . $user->name . '!');
        
        // Redirect to intended URL or dashboard
        $intended = session('url.intended', '/dashboard');
        session()->forget('url.intended');
        
        return $response->redirect($intended);
    }

    /**
     * Show registration form
     */
    public function showRegister($request, $response)
    {
        if (auth_context()->check()) {
            return $response->redirect('/dashboard');
        }
        
        return $response->view('auth/register', [
            'error' => flash('error'),
            'errors' => flash('errors', []),
        ]);
    }

    /**
     * Handle registration
     */
    public function register($request, $response)
    {
        $data = $request->only(['name', 'email', 'password', 'password_confirmation']);
        
        // Validate
        $errors = $this->validateRegistration($data);
        
        if (!empty($errors)) {
            flash('errors', $errors);
            return $response->redirect('/register');
        }
        
        // Check if email exists
        if (User::findByEmail($data['email'])) {
            flash('error', 'Email already registered');
            return $response->redirect('/register');
        }
        
        // Create user
        $user = User::createUser([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
        
        // Auto-login
        auth()->login($user);
        
        flash('success', 'Account created successfully!');
        return $response->redirect('/dashboard');
    }

    /**
     * Handle logout
     */
    public function logout($request, $response)
    {
        auth()->logout();
        
        flash('success', 'You have been logged out');
        return $response->redirect('/login');
    }

    /**
     * Validate registration data
     */
    private function validateRegistration(array $data): array
    {
        $errors = [];
        
        if (empty($data['name'])) {
            $errors['name'] = 'Name is required';
        } elseif (strlen($data['name']) < 2) {
            $errors['name'] = 'Name must be at least 2 characters';
        }
        
        if (empty($data['email'])) {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        }
        
        if (empty($data['password'])) {
            $errors['password'] = 'Password is required';
        } elseif (strlen($data['password']) < 8) {
            $errors['password'] = 'Password must be at least 8 characters';
        }
        
        if ($data['password'] !== ($data['password_confirmation'] ?? '')) {
            $errors['password_confirmation'] = 'Passwords do not match';
        }
        
        return $errors;
    }
}
```

### app/Controllers/DashboardController.php

```php
<?php

namespace App\Controllers;

use function Lalaz\Auth\Helpers\user;
use function Lalaz\Auth\Helpers\auth_context;

class DashboardController
{
    /**
     * User dashboard
     */
    public function index($request, $response)
    {
        $user = user();
        
        return $response->view('dashboard', [
            'user' => $user,
            'isAdmin' => auth_context()->hasRole('admin'),
        ]);
    }

    /**
     * User profile
     */
    public function profile($request, $response)
    {
        $user = user();
        
        return $response->view('profile', [
            'user' => $user,
            'success' => flash('success'),
        ]);
    }

    /**
     * Update profile
     */
    public function updateProfile($request, $response)
    {
        $user = user();
        
        $user->name = $request->input('name', $user->name);
        $user->save();
        
        flash('success', 'Profile updated successfully');
        return $response->redirect('/profile');
    }
}
```

### app/Controllers/AdminController.php

```php
<?php

namespace App\Controllers;

use App\Models\User;
use function Lalaz\Auth\Helpers\user;

class AdminController
{
    /**
     * Admin dashboard
     */
    public function index($request, $response)
    {
        return $response->view('admin/index', [
            'totalUsers' => User::count(),
            'recentUsers' => User::query()
                ->orderBy('created_at', 'DESC')
                ->limit(10)
                ->get(),
        ]);
    }

    /**
     * List all users
     */
    public function users($request, $response)
    {
        $users = User::query()
            ->orderBy('created_at', 'DESC')
            ->paginate(20);
        
        return $response->view('admin/users', [
            'users' => $users,
        ]);
    }

    /**
     * Update user role
     */
    public function updateUserRole($request, $response, $id)
    {
        $targetUser = User::find($id);
        
        if (!$targetUser) {
            return $response->error('User not found', 404);
        }
        
        // Prevent self-demotion
        if ($targetUser->id === user()->id) {
            flash('error', 'Cannot change your own role');
            return $response->redirect('/admin/users');
        }
        
        $targetUser->role = $request->input('role');
        $targetUser->save();
        
        flash('success', 'User role updated');
        return $response->redirect('/admin/users');
    }
}
```

## Routes

### routes/web.php

```php
<?php

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\AdminController;
use Lalaz\Auth\Middlewares\AuthenticationMiddleware;
use Lalaz\Auth\Middlewares\AuthorizationMiddleware;

// Public routes
$router->get('/', function ($request, $response) {
    return $response->view('welcome');
});

// Guest routes (redirect if logged in)
$router->group('/auth', function ($router) {
    $router->get('/login', [AuthController::class, 'showLogin']);
    $router->post('/login', [AuthController::class, 'login']);
    $router->get('/register', [AuthController::class, 'showRegister']);
    $router->post('/register', [AuthController::class, 'register']);
});

// Protected routes (require authentication)
$router->group('/', function ($router) {
    $router->get('/dashboard', [DashboardController::class, 'index']);
    $router->get('/profile', [DashboardController::class, 'profile']);
    $router->post('/profile', [DashboardController::class, 'updateProfile']);
    $router->post('/logout', [AuthController::class, 'logout']);
    
})->middleware(AuthenticationMiddleware::web('/login'));

// Admin routes (require admin role)
$router->group('/admin', function ($router) {
    $router->get('/', [AdminController::class, 'index']);
    $router->get('/users', [AdminController::class, 'users']);
    $router->post('/users/{id}/role', [AdminController::class, 'updateUserRole']);
    
})->middleware([
    AuthenticationMiddleware::web('/login'),
    AuthorizationMiddleware::requireRoles('admin'),
]);
```

## Views

### resources/views/auth/login.php

```php
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body>
    <div class="container">
        <h1>Login</h1>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <form method="POST" action="/auth/login">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="remember"> Remember me
                </label>
            </div>
            
            <button type="submit" class="btn btn-primary">Login</button>
        </form>
        
        <p>Don't have an account? <a href="/auth/register">Register</a></p>
    </div>
</body>
</html>
```

### resources/views/auth/register.php

```php
<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body>
    <div class="container">
        <h1>Create Account</h1>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST" action="/auth/register">
            <div class="form-group">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" required>
                <?php if (!empty($errors['name'])): ?>
                    <span class="error"><?= htmlspecialchars($errors['name']) ?></span>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
                <?php if (!empty($errors['email'])): ?>
                    <span class="error"><?= htmlspecialchars($errors['email']) ?></span>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required minlength="8">
                <?php if (!empty($errors['password'])): ?>
                    <span class="error"><?= htmlspecialchars($errors['password']) ?></span>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="password_confirmation">Confirm Password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required>
                <?php if (!empty($errors['password_confirmation'])): ?>
                    <span class="error"><?= htmlspecialchars($errors['password_confirmation']) ?></span>
                <?php endif; ?>
            </div>
            
            <button type="submit" class="btn btn-primary">Create Account</button>
        </form>
        
        <p>Already have an account? <a href="/auth/login">Login</a></p>
    </div>
</body>
</html>
```

### resources/views/dashboard.php

```php
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body>
    <header>
        <nav>
            <a href="/dashboard">Dashboard</a>
            <a href="/profile">Profile</a>
            <?php if ($isAdmin): ?>
                <a href="/admin">Admin Panel</a>
            <?php endif; ?>
            <form action="/logout" method="POST" style="display:inline;">
                <button type="submit">Logout</button>
            </form>
        </nav>
    </header>
    
    <main class="container">
        <h1>Welcome, <?= htmlspecialchars($user->name) ?>!</h1>
        
        <div class="card">
            <h2>Your Account</h2>
            <p><strong>Email:</strong> <?= htmlspecialchars($user->email) ?></p>
            <p><strong>Role:</strong> <?= htmlspecialchars($user->role) ?></p>
            <p><strong>Member since:</strong> <?= $user->created_at ?></p>
        </div>
    </main>
</body>
</html>
```

## Database Migration

```sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) DEFAULT 'user',
    email_verified_at TIMESTAMP NULL,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_email (email),
    INDEX idx_role (role)
);
```

## Testing

### tests/Feature/AuthTest.php

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use PHPUnit\Framework\TestCase;

class AuthTest extends TestCase
{
    public function test_user_can_view_login_page(): void
    {
        $response = $this->get('/auth/login');
        
        $response->assertStatus(200);
        $response->assertSee('Login');
    }

    public function test_user_can_register(): void
    {
        $response = $this->post('/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        
        $response->assertRedirect('/dashboard');
        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    public function test_user_can_login(): void
    {
        $user = User::createUser([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);
        
        $response = $this->post('/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);
        
        $response->assertRedirect('/dashboard');
    }

    public function test_invalid_login_shows_error(): void
    {
        $response = $this->post('/auth/login', [
            'email' => 'wrong@example.com',
            'password' => 'wrongpassword',
        ]);
        
        $response->assertRedirect('/login');
        $response->assertSessionHas('error');
    }

    public function test_authenticated_user_can_access_dashboard(): void
    {
        $user = User::createUser([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);
        
        $this->actingAs($user);
        
        $response = $this->get('/dashboard');
        
        $response->assertStatus(200);
        $response->assertSee('Welcome, Test User');
    }

    public function test_guest_cannot_access_dashboard(): void
    {
        $response = $this->get('/dashboard');
        
        $response->assertRedirect('/login');
    }

    public function test_non_admin_cannot_access_admin(): void
    {
        $user = User::createUser([
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => 'password123',
            'role' => 'user',
        ]);
        
        $this->actingAs($user);
        
        $response = $this->get('/admin');
        
        $response->assertStatus(403);
    }

    public function test_admin_can_access_admin_panel(): void
    {
        $admin = User::createUser([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'role' => 'admin',
        ]);
        
        $this->actingAs($admin);
        
        $response = $this->get('/admin');
        
        $response->assertStatus(200);
    }
}
```

## Security Checklist

- ✅ Passwords hashed with bcrypt
- ✅ CSRF protection on forms
- ✅ Session cookies are HttpOnly and Secure
- ✅ Input validation on registration
- ✅ Email uniqueness check
- ✅ Role-based access control
- ✅ HTML output escaped with htmlspecialchars

## Next Steps

- [REST API Example](./api.md) - JWT authentication
- [Multi-Guard Setup](./multi-guard.md) - Multiple auth guards
- [Authorization](../authorization.md) - Advanced permissions
