# Segregated Login Areas

Complete example of implementing separate login areas for different user types (e.g., customers vs administrators).

## Overview

This pattern is useful when you need:
- **Different login pages** for each user type
- **Different redirect destinations** after login
- **Separate session management** (users can be logged in to both areas simultaneously)
- **Different user models/tables** for each area
- **Different UI/branding** for each area

## Common Use Cases

| Area | Users | Example Routes |
|------|-------|----------------|
| Customer Portal | Customers, Buyers | `/login`, `/dashboard`, `/orders` |
| Admin Panel | Staff, Managers | `/admin/login`, `/admin`, `/admin/users` |
| Vendor Portal | Sellers, Partners | `/vendor/login`, `/vendor/products` |
| Support Panel | Support Agents | `/support/login`, `/support/tickets` |

## Project Structure

```
app/
├── Controllers/
│   ├── Customer/
│   │   ├── AuthController.php
│   │   ├── DashboardController.php
│   │   └── ProfileController.php
│   └── Admin/
│       ├── AuthController.php
│       ├── DashboardController.php
│       └── UserController.php
├── Models/
│   ├── User.php          # Customer users
│   └── Admin.php         # Admin users
├── Middlewares/
│   └── AdminAreaMiddleware.php (optional)
config/
└── auth.php
routes/
├── web.php               # Customer routes
└── admin.php             # Admin routes (or in web.php)
resources/
└── views/
    ├── customer/
    │   ├── login.php
    │   ├── dashboard.php
    │   └── layout.php
    └── admin/
        ├── login.php
        ├── dashboard.php
        └── layout.php
```

## Configuration

### config/auth.php

```php
<?php

return [
    'default' => 'web',
    
    'guards' => [
        // Customer area guard
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
            'session_key' => 'customer_auth_id',  // Unique session key
        ],
        
        // Admin area guard
        'admin' => [
            'driver' => 'session',
            'provider' => 'admins',
            'session_key' => 'admin_auth_id',     // Different session key!
        ],
        
        // Optional: Vendor area guard
        'vendor' => [
            'driver' => 'session',
            'provider' => 'vendors',
            'session_key' => 'vendor_auth_id',
        ],
    ],
    
    'providers' => [
        'users' => [
            'driver' => 'model',
            'model' => \App\Models\User::class,
        ],
        'admins' => [
            'driver' => 'model',
            'model' => \App\Models\Admin::class,
        ],
        'vendors' => [
            'driver' => 'model',
            'model' => \App\Models\Vendor::class,
        ],
    ],
];
```

> **Important:** Each guard must have a unique `session_key`. This allows users to be logged in to multiple areas simultaneously.

## Database Schema

### Users Table (Customers)

```sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    email_verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Admins Table

```sql
CREATE TABLE admins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'super-admin', 'moderator') DEFAULT 'admin',
    permissions JSON,
    is_active BOOLEAN DEFAULT TRUE,
    last_login_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

## Models

### app/Models/User.php (Customer)

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
        'phone',
        'address',
    ];

    protected array $hidden = ['password'];

    public static function register(array $data): self
    {
        return self::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => password_hash($data['password'], PASSWORD_BCRYPT),
            'phone' => $data['phone'] ?? null,
        ]);
    }

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
        return $this->remember_token;
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
        return ['customer'];
    }

    public function getPermissions(): array
    {
        return [
            'orders.view',
            'orders.create',
            'profile.edit',
        ];
    }

    public function hasRole(string $role): bool
    {
        return $role === 'customer';
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->getPermissions());
    }
}
```

### app/Models/Admin.php

```php
<?php

namespace App\Models;

use Lalaz\Data\Model;
use Lalaz\Auth\Contracts\AuthenticatableInterface;

class Admin extends Model implements AuthenticatableInterface
{
    protected static string $tableName = 'admins';

    protected array $fillable = [
        'name',
        'email',
        'password',
        'role',
        'permissions',
    ];

    protected array $hidden = ['password'];

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
        return $this->remember_token;
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
        // Super admin has all permissions
        if ($this->role === 'super-admin') {
            return ['*'];
        }

        // Load from database
        $permissions = json_decode($this->permissions ?? '[]', true);

        // Add default permissions based on role
        $rolePermissions = [
            'admin' => ['users.view', 'users.edit', 'orders.view', 'orders.edit'],
            'moderator' => ['users.view', 'orders.view', 'comments.moderate'],
        ];

        return array_unique(array_merge(
            $permissions,
            $rolePermissions[$this->role] ?? []
        ));
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

    /**
     * Record login timestamp
     */
    public function recordLogin(): void
    {
        $this->last_login_at = date('Y-m-d H:i:s');
        $this->save();
    }
}
```

## Routes

### routes/web.php

```php
<?php

use App\Controllers\Customer\AuthController as CustomerAuthController;
use App\Controllers\Customer\DashboardController as CustomerDashboardController;
use App\Controllers\Customer\ProfileController;
use App\Controllers\Customer\OrderController;
use App\Controllers\Admin\AuthController as AdminAuthController;
use App\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Controllers\Admin\UserController as AdminUserController;
use Lalaz\Auth\Middlewares\AuthenticationMiddleware;
use Lalaz\Auth\Middlewares\AuthorizationMiddleware;
use Lalaz\Auth\Middlewares\PermissionMiddleware;

// =====================================================
// PUBLIC ROUTES
// =====================================================

$router->get('/', function ($request, $response) {
    return $response->view('welcome');
});

// =====================================================
// CUSTOMER AREA
// =====================================================

// Customer authentication (public)
$router->get('/login', [CustomerAuthController::class, 'showLogin']);
$router->post('/login', [CustomerAuthController::class, 'login']);
$router->get('/register', [CustomerAuthController::class, 'showRegister']);
$router->post('/register', [CustomerAuthController::class, 'register']);
$router->get('/forgot-password', [CustomerAuthController::class, 'showForgotPassword']);
$router->post('/forgot-password', [CustomerAuthController::class, 'forgotPassword']);

// Customer protected routes
$router->group('/', function ($router) {
    $router->post('/logout', [CustomerAuthController::class, 'logout']);
    $router->get('/dashboard', [CustomerDashboardController::class, 'index']);
    $router->get('/profile', [ProfileController::class, 'show']);
    $router->put('/profile', [ProfileController::class, 'update']);
    $router->get('/orders', [OrderController::class, 'index']);
    $router->get('/orders/{id}', [OrderController::class, 'show']);
})->middleware(AuthenticationMiddleware::web('/login'));

// =====================================================
// ADMIN AREA
// =====================================================

// Admin authentication (public)
$router->get('/admin/login', [AdminAuthController::class, 'showLogin']);
$router->post('/admin/login', [AdminAuthController::class, 'login']);

// Admin protected routes
$router->group('/admin', function ($router) {
    // All admins
    $router->post('/logout', [AdminAuthController::class, 'logout']);
    $router->get('/', [AdminDashboardController::class, 'index']);
    $router->get('/profile', [AdminDashboardController::class, 'profile']);
    
    // User management (needs permission)
    $router->group('/users', function ($router) {
        $router->get('/', [AdminUserController::class, 'index']);
        $router->get('/{id}', [AdminUserController::class, 'show']);
        $router->get('/create', [AdminUserController::class, 'create'])
            ->middleware(PermissionMiddleware::require('users.create'));
        $router->post('/', [AdminUserController::class, 'store'])
            ->middleware(PermissionMiddleware::require('users.create'));
        $router->get('/{id}/edit', [AdminUserController::class, 'edit'])
            ->middleware(PermissionMiddleware::require('users.edit'));
        $router->put('/{id}', [AdminUserController::class, 'update'])
            ->middleware(PermissionMiddleware::require('users.edit'));
        $router->delete('/{id}', [AdminUserController::class, 'destroy'])
            ->middleware(PermissionMiddleware::require('users.delete'));
    })->middleware(PermissionMiddleware::require('users.view'));
    
    // Settings (super-admin only)
    $router->group('/settings', function ($router) {
        $router->get('/', [AdminDashboardController::class, 'settings']);
        $router->put('/', [AdminDashboardController::class, 'updateSettings']);
    })->middleware(AuthorizationMiddleware::requireRoles('super-admin'));

})->middleware(AuthenticationMiddleware::forGuard('admin', '/admin/login'));
```

## Controllers

### app/Controllers/Customer/AuthController.php

```php
<?php

namespace App\Controllers\Customer;

use App\Models\User;
use Lalaz\Auth\AuthManager;

class AuthController
{
    private AuthManager $auth;

    public function __construct()
    {
        $this->auth = new AuthManager();
    }

    /**
     * Show customer login page
     * GET /login
     */
    public function showLogin($request, $response)
    {
        // Already logged in? Go to dashboard
        if ($this->auth->guard('web')->check()) {
            return $response->redirect('/dashboard');
        }

        return $response->view('customer/login', [
            'title' => 'Customer Login',
        ]);
    }

    /**
     * Process customer login
     * POST /login
     */
    public function login($request, $response)
    {
        $email = $request->input('email');
        $password = $request->input('password');
        $remember = $request->input('remember', false);

        // Validate input
        if (empty($email) || empty($password)) {
            flash('error', 'Please enter email and password');
            return $response->redirect('/login');
        }

        // Attempt authentication with 'web' guard
        $user = $this->auth->guard('web')->attempt([
            'email' => $email,
            'password' => $password,
        ]);

        if (!$user) {
            flash('error', 'Invalid email or password');
            return $response->redirect('/login');
        }

        // Check if account is active
        if (!$user->is_active) {
            $this->auth->guard('web')->logout();
            flash('error', 'Your account has been deactivated');
            return $response->redirect('/login');
        }

        // Handle "remember me"
        if ($remember) {
            // Extend session lifetime
            session()->regenerate(true);
        }

        flash('success', "Welcome back, {$user->name}!");

        // Redirect to intended URL or dashboard
        $intended = session('url.intended', '/dashboard');
        session()->forget('url.intended');

        return $response->redirect($intended);
    }

    /**
     * Show registration page
     * GET /register
     */
    public function showRegister($request, $response)
    {
        if ($this->auth->guard('web')->check()) {
            return $response->redirect('/dashboard');
        }

        return $response->view('customer/register', [
            'title' => 'Create Account',
        ]);
    }

    /**
     * Process registration
     * POST /register
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
        $user = User::register($data);

        // Auto login
        $this->auth->guard('web')->login($user);

        flash('success', 'Account created successfully!');
        return $response->redirect('/dashboard');
    }

    /**
     * Logout customer
     * POST /logout
     */
    public function logout($request, $response)
    {
        $this->auth->guard('web')->logout();
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

### app/Controllers/Admin/AuthController.php

```php
<?php

namespace App\Controllers\Admin;

use App\Models\Admin;
use Lalaz\Auth\AuthManager;

class AuthController
{
    private AuthManager $auth;

    public function __construct()
    {
        $this->auth = new AuthManager();
    }

    /**
     * Show admin login page
     * GET /admin/login
     */
    public function showLogin($request, $response)
    {
        // Already logged in as admin? Go to admin dashboard
        if ($this->auth->guard('admin')->check()) {
            return $response->redirect('/admin');
        }

        return $response->view('admin/login', [
            'title' => 'Admin Login',
        ]);
    }

    /**
     * Process admin login
     * POST /admin/login
     */
    public function login($request, $response)
    {
        $email = $request->input('email');
        $password = $request->input('password');

        // Validate input
        if (empty($email) || empty($password)) {
            flash('error', 'Please enter email and password');
            return $response->redirect('/admin/login');
        }

        // Attempt authentication with 'admin' guard
        $admin = $this->auth->guard('admin')->attempt([
            'email' => $email,
            'password' => $password,
        ]);

        if (!$admin) {
            // Log failed attempt (security)
            $this->logFailedAttempt($email, $request->ip());
            
            flash('error', 'Invalid credentials');
            return $response->redirect('/admin/login');
        }

        // Check if admin account is active
        if (!$admin->is_active) {
            $this->auth->guard('admin')->logout();
            flash('error', 'Your admin account has been deactivated');
            return $response->redirect('/admin/login');
        }

        // Record login
        $admin->recordLogin();

        // Log successful login
        $this->logSuccessfulLogin($admin, $request->ip());

        flash('success', "Welcome, {$admin->name}!");

        // Redirect to intended admin URL or dashboard
        $intended = session('url.admin_intended', '/admin');
        session()->forget('url.admin_intended');

        return $response->redirect($intended);
    }

    /**
     * Logout admin
     * POST /admin/logout
     */
    public function logout($request, $response)
    {
        $admin = $this->auth->guard('admin')->user();
        
        $this->auth->guard('admin')->logout();
        
        // Log logout
        if ($admin) {
            $this->logLogout($admin);
        }

        flash('success', 'You have been logged out');
        return $response->redirect('/admin/login');
    }

    /**
     * Log failed login attempt (for security monitoring)
     */
    private function logFailedAttempt(string $email, string $ip): void
    {
        // Implementation depends on your logging system
        // Example: AdminLoginLog::create([...])
    }

    /**
     * Log successful login
     */
    private function logSuccessfulLogin(Admin $admin, string $ip): void
    {
        // Implementation depends on your logging system
    }

    /**
     * Log logout
     */
    private function logLogout(Admin $admin): void
    {
        // Implementation depends on your logging system
    }
}
```

### app/Controllers/Customer/DashboardController.php

```php
<?php

namespace App\Controllers\Customer;

use function Lalaz\Auth\Helpers\user;

class DashboardController
{
    /**
     * Customer dashboard
     * GET /dashboard
     */
    public function index($request, $response)
    {
        $user = user();  // Uses default 'web' guard

        return $response->view('customer/dashboard', [
            'title' => 'Dashboard',
            'user' => $user,
            'recentOrders' => $user->orders()->recent(5)->get(),
        ]);
    }
}
```

### app/Controllers/Admin/DashboardController.php

```php
<?php

namespace App\Controllers\Admin;

use App\Models\User;
use App\Models\Order;
use function Lalaz\Auth\Helpers\user;
use function Lalaz\Auth\Helpers\auth_context;

class DashboardController
{
    /**
     * Admin dashboard
     * GET /admin
     */
    public function index($request, $response)
    {
        $admin = user('admin');  // Explicitly use 'admin' guard

        return $response->view('admin/dashboard', [
            'title' => 'Admin Dashboard',
            'admin' => $admin,
            'stats' => [
                'totalUsers' => User::count(),
                'totalOrders' => Order::count(),
                'pendingOrders' => Order::where('status', '=', 'pending')->count(),
            ],
            'canManageUsers' => auth_context()->hasPermission('users.view'),
        ]);
    }

    /**
     * Admin settings (super-admin only)
     * GET /admin/settings
     */
    public function settings($request, $response)
    {
        return $response->view('admin/settings', [
            'title' => 'System Settings',
        ]);
    }
}
```

## Views

### resources/views/customer/login.php

```php
<!DOCTYPE html>
<html>
<head>
    <title><?= $title ?> - MyStore</title>
    <link rel="stylesheet" href="/css/customer.css">
</head>
<body class="customer-area">
    <div class="login-container">
        <div class="login-box">
            <img src="/images/logo.png" alt="MyStore" class="logo">
            <h1>Customer Login</h1>
            
            <?php if ($error = flash('error')): ?>
                <div class="alert alert-error"><?= $error ?></div>
            <?php endif; ?>
            
            <form action="/login" method="POST">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required 
                           placeholder="your@email.com">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required
                           placeholder="Your password">
                </div>
                
                <div class="form-group checkbox">
                    <label>
                        <input type="checkbox" name="remember" value="1">
                        Remember me
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">
                    Login
                </button>
            </form>
            
            <div class="links">
                <a href="/forgot-password">Forgot password?</a>
                <a href="/register">Create account</a>
            </div>
        </div>
    </div>
</body>
</html>
```

### resources/views/admin/login.php

```php
<!DOCTYPE html>
<html>
<head>
    <title><?= $title ?> - Admin Panel</title>
    <link rel="stylesheet" href="/css/admin.css">
</head>
<body class="admin-area">
    <div class="login-container admin-login">
        <div class="login-box">
            <div class="admin-header">
                <i class="icon-shield"></i>
                <h1>Admin Panel</h1>
                <p>Restricted Access</p>
            </div>
            
            <?php if ($error = flash('error')): ?>
                <div class="alert alert-error"><?= $error ?></div>
            <?php endif; ?>
            
            <form action="/admin/login" method="POST">
                <div class="form-group">
                    <label for="email">Admin Email</label>
                    <input type="email" id="email" name="email" required
                           placeholder="admin@company.com">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required
                           placeholder="••••••••">
                </div>
                
                <button type="submit" class="btn btn-admin btn-block">
                    <i class="icon-lock"></i> Secure Login
                </button>
            </form>
            
            <div class="security-notice">
                <i class="icon-info"></i>
                This area is monitored. All access attempts are logged.
            </div>
        </div>
    </div>
</body>
</html>
```

## Custom Middleware (Optional)

### app/Middlewares/AdminAreaMiddleware.php

For more complex admin area requirements:

```php
<?php

namespace App\Middlewares;

use Lalaz\Auth\AuthManager;

class AdminAreaMiddleware
{
    private AuthManager $auth;
    
    // Optional: Restrict admin access by IP
    private array $allowedIps = [
        '127.0.0.1',
        '192.168.1.0/24',  // Office network
    ];

    public function __construct()
    {
        $this->auth = new AuthManager();
    }

    public function handle($request, $next)
    {
        $guard = $this->auth->guard('admin');

        // Check authentication
        if (!$guard->check()) {
            // Store intended URL
            session(['url.admin_intended' => $request->getUri()]);
            return redirect('/admin/login');
        }

        $admin = $guard->user();

        // Check if account is active
        if (!$admin->is_active) {
            $guard->logout();
            flash('error', 'Your account has been deactivated');
            return redirect('/admin/login');
        }

        // Optional: IP whitelist check
        if (!$this->isIpAllowed($request->ip())) {
            $guard->logout();
            flash('error', 'Access denied from your location');
            return redirect('/admin/login');
        }

        // Optional: Check for session timeout (e.g., 30 min inactivity)
        $lastActivity = session('admin_last_activity', 0);
        $timeout = 30 * 60; // 30 minutes
        
        if (time() - $lastActivity > $timeout) {
            $guard->logout();
            flash('error', 'Session expired due to inactivity');
            return redirect('/admin/login');
        }
        
        // Update last activity
        session(['admin_last_activity' => time()]);

        // Continue to the route
        return $next($request);
    }

    private function isIpAllowed(string $ip): bool
    {
        // In development, allow all
        if (env('APP_ENV') === 'development') {
            return true;
        }

        foreach ($this->allowedIps as $allowed) {
            if ($this->ipMatches($ip, $allowed)) {
                return true;
            }
        }

        return false;
    }

    private function ipMatches(string $ip, string $pattern): bool
    {
        // Direct match
        if ($ip === $pattern) {
            return true;
        }

        // CIDR match
        if (strpos($pattern, '/') !== false) {
            [$subnet, $bits] = explode('/', $pattern);
            $ip = ip2long($ip);
            $subnet = ip2long($subnet);
            $mask = -1 << (32 - $bits);
            return ($ip & $mask) === ($subnet & $mask);
        }

        return false;
    }
}
```

Use in routes:

```php
$router->group('/admin', function ($router) {
    // Admin routes...
})->middleware(AdminAreaMiddleware::class);
```

## Helper Functions by Area

```php
use function Lalaz\Auth\Helpers\user;
use function Lalaz\Auth\Helpers\auth_context;
use Lalaz\Auth\AuthManager;

// In customer controllers
$customer = user();          // Default 'web' guard
$customer = user('web');     // Explicit

// In admin controllers
$admin = user('admin');      // Must specify 'admin' guard

// Check which area user is in
$auth = new AuthManager();

$isCustomerLoggedIn = $auth->guard('web')->check();
$isAdminLoggedIn = $auth->guard('admin')->check();

// User can be logged into BOTH areas simultaneously!
if ($isCustomerLoggedIn && $isAdminLoggedIn) {
    // User has both customer and admin accounts
}
```

## Simultaneous Login Example

A person can be logged in as both a customer AND an admin at the same time:

```php
// User visits /dashboard (customer area)
$customer = user('web');     // Returns User model (id: 42)

// Same user visits /admin (admin area)
$admin = user('admin');      // Returns Admin model (id: 5)

// Different sessions, different models, different permissions
// They don't interfere with each other!
```

## Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                        BROWSER SESSION                           │
│  ┌─────────────────────┐       ┌─────────────────────┐          │
│  │ customer_auth_id: 42│       │ admin_auth_id: 5    │          │
│  └─────────────────────┘       └─────────────────────┘          │
└─────────────────────────────────────────────────────────────────┘
                │                           │
                ▼                           ▼
┌───────────────────────────┐   ┌───────────────────────────┐
│      CUSTOMER AREA        │   │       ADMIN AREA          │
├───────────────────────────┤   ├───────────────────────────┤
│ Guard: 'web'              │   │ Guard: 'admin'            │
│ Provider: 'users'         │   │ Provider: 'admins'        │
│ Model: User               │   │ Model: Admin              │
│ Login: /login             │   │ Login: /admin/login       │
│ Home: /dashboard          │   │ Home: /admin              │
│ Logout: /logout           │   │ Logout: /admin/logout     │
├───────────────────────────┤   ├───────────────────────────┤
│ Middleware:               │   │ Middleware:               │
│ AuthenticationMiddleware  │   │ AuthenticationMiddleware  │
│ ::web('/login')           │   │ ::forGuard('admin',       │
│                           │   │   '/admin/login')         │
├───────────────────────────┤   ├───────────────────────────┤
│ Views:                    │   │ Views:                    │
│ customer/login.php        │   │ admin/login.php           │
│ customer/dashboard.php    │   │ admin/dashboard.php       │
│ customer/layout.php       │   │ admin/layout.php          │
└───────────────────────────┘   └───────────────────────────┘
                │                           │
                ▼                           ▼
        ┌───────────────┐           ┌───────────────┐
        │  users table  │           │  admins table │
        └───────────────┘           └───────────────┘
```

## Security Considerations

1. **Different Session Keys** - Prevents session fixation between areas
2. **Separate User Tables** - Isolates customer and admin data
3. **Audit Logging** - Log all admin access attempts
4. **IP Restrictions** - Optional IP whitelist for admin area
5. **Session Timeout** - Shorter timeout for admin area
6. **HTTPS** - Always use HTTPS, especially for admin area
7. **Rate Limiting** - Limit login attempts per IP
8. **2FA** - Consider two-factor authentication for admin area

## Testing

```php
<?php

namespace Tests;

use App\Models\User;
use App\Models\Admin;
use Lalaz\Auth\AuthManager;
use PHPUnit\Framework\TestCase;

class SegregatedAreasTest extends TestCase
{
    public function test_customer_cannot_access_admin_area(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        // Logged in as customer
        $this->assertTrue(auth()->guard('web')->check());

        // But NOT as admin
        $this->assertFalse(auth()->guard('admin')->check());

        // Admin route should redirect to admin login
        $response = $this->get('/admin');
        $response->assertRedirect('/admin/login');
    }

    public function test_admin_cannot_access_customer_dashboard_as_customer(): void
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        // Logged in as admin
        $this->assertTrue(auth()->guard('admin')->check());

        // But NOT as customer
        $this->assertFalse(auth()->guard('web')->check());

        // Customer dashboard should redirect to customer login
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_user_can_be_logged_into_both_areas(): void
    {
        $user = User::factory()->create();
        $admin = Admin::factory()->create();

        // Login as customer
        $this->actingAs($user, 'web');

        // Also login as admin
        $this->actingAs($admin, 'admin');

        // Both should be authenticated
        $this->assertTrue(auth()->guard('web')->check());
        $this->assertTrue(auth()->guard('admin')->check());

        // Different users
        $this->assertEquals($user->id, user('web')->getAuthIdentifier());
        $this->assertEquals($admin->id, user('admin')->getAuthIdentifier());
    }

    public function test_customer_logout_does_not_affect_admin_session(): void
    {
        $user = User::factory()->create();
        $admin = Admin::factory()->create();

        $this->actingAs($user, 'web');
        $this->actingAs($admin, 'admin');

        // Logout from customer area
        auth()->guard('web')->logout();

        // Customer is logged out
        $this->assertFalse(auth()->guard('web')->check());

        // Admin is still logged in!
        $this->assertTrue(auth()->guard('admin')->check());
    }
}
```

## Next Steps

- [Multi-Guard Setup](./multi-guard.md) - More guard configurations
- [Guards Overview](../guards/index.md) - Understanding guards
- [Middlewares](../middlewares/index.md) - Route protection
- [Authorization](../authorization.md) - Roles and permissions
