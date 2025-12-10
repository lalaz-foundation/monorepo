# Session Guard

The Session Guard is the traditional approach for web application authentication. It stores the user ID in a server-side session.

## How It Works

```
1. User submits login form (email + password)
        │
        ▼
2. SessionGuard::attempt() validates credentials
        │
        ├─── Provider retrieves user by email
        ├─── Password is verified
        └─── User ID stored in session
                │
                ▼
3. Browser receives session cookie
        │
        ▼
4. Subsequent requests include cookie
        │
        ▼
5. SessionGuard reads user ID from session
        │
        ▼
6. Provider loads full user object
```

## Configuration

```php
// config/auth.php
return [
    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],
    
    'providers' => [
        'users' => [
            'driver' => 'model',
            'model' => App\Models\User::class,
        ],
    ],
];
```

## Basic Usage

### Login

```php
use Lalaz\Auth\AuthManager;

$auth = resolve(AuthManager::class);
$guard = $auth->guard('web');

// Attempt login with credentials
$user = $guard->attempt([
    'email' => 'user@example.com',
    'password' => 'secret123',
]);

if ($user) {
    // Login successful
    $guard->login($user);
    redirect('/dashboard');
} else {
    // Login failed
    redirect('/login?error=invalid');
}
```

### Check Authentication

```php
// Is user logged in?
if ($guard->check()) {
    echo "Welcome back!";
}

// Is user a guest?
if ($guard->guest()) {
    echo "Please log in";
}
```

### Get Current User

```php
// Get the authenticated user
$user = $guard->user();

if ($user) {
    echo "Hello, " . $user->name;
}

// Get just the user ID
$userId = $guard->id();
```

### Logout

```php
$guard->logout();
redirect('/login');
```

### Validate Without Login

```php
// Check if credentials are valid without logging in
$isValid = $guard->validate([
    'email' => 'user@example.com',
    'password' => 'secret123',
]);

if ($isValid) {
    echo "Credentials are correct!";
}
```

## Login Form Example

### Route

```php
// routes/web.php
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);
```

### Controller

```php
<?php

namespace App\Controllers;

use Lalaz\Auth\AuthManager;

class AuthController
{
    public function __construct(
        private AuthManager $auth
    ) {}

    public function showLogin($request, $response)
    {
        // Redirect if already logged in
        if ($this->auth->guard('web')->check()) {
            return $response->redirect('/dashboard');
        }
        
        $response->view('auth/login', [
            'error' => $request->query('error'),
        ]);
    }

    public function login($request, $response)
    {
        $email = $request->input('email');
        $password = $request->input('password');
        $remember = $request->input('remember', false);

        // Validate input
        if (empty($email) || empty($password)) {
            return $response->redirect('/login?error=missing_fields');
        }

        // Attempt login
        $user = $this->auth->guard('web')->attempt([
            'email' => $email,
            'password' => $password,
        ]);

        if (!$user) {
            return $response->redirect('/login?error=invalid_credentials');
        }

        // Login the user
        $this->auth->guard('web')->login($user);

        // Redirect to intended URL or dashboard
        $intended = $request->session()->get('intended_url', '/dashboard');
        $request->session()->remove('intended_url');
        
        $response->redirect($intended);
    }

    public function logout($request, $response)
    {
        $this->auth->guard('web')->logout();
        $response->redirect('/login');
    }
}
```

### View (login.php)

```php
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
</head>
<body>
    <h1>Login</h1>
    
    <?php if (isset($error)): ?>
        <div class="error">
            <?php if ($error === 'invalid_credentials'): ?>
                Invalid email or password.
            <?php elseif ($error === 'missing_fields'): ?>
                Please fill in all fields.
            <?php else: ?>
                An error occurred.
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="/login">
        <div>
            <label>Email:</label>
            <input type="email" name="email" required>
        </div>
        
        <div>
            <label>Password:</label>
            <input type="password" name="password" required>
        </div>
        
        <div>
            <label>
                <input type="checkbox" name="remember" value="1">
                Remember me
            </label>
        </div>
        
        <button type="submit">Login</button>
    </form>
</body>
</html>
```

## Protecting Routes

### Using Middleware

```php
use Lalaz\Auth\Middlewares\AuthenticationMiddleware;

// Protect routes - redirect to /login if not authenticated
$router->group('/dashboard', function ($router) {
    $router->get('/', [DashboardController::class, 'index']);
    $router->get('/profile', [DashboardController::class, 'profile']);
})->middleware(AuthenticationMiddleware::web('/login'));

// Alternative: session() method is equivalent
$router->group('/admin', function ($router) {
    $router->get('/', [AdminController::class, 'index']);
})->middleware(AuthenticationMiddleware::session('web', '/admin/login'));
```

### In Controllers

```php
class DashboardController
{
    public function index($request, $response)
    {
        // At this point, user is guaranteed to be authenticated
        // (middleware already checked)
        
        $user = user();  // Helper function
        
        $response->view('dashboard', [
            'user' => $user,
        ]);
    }
}
```

## Session Adapter

The SessionGuard uses a session adapter to interact with the session storage. By default, it uses `WebSessionAdapter` which wraps PHP's `$_SESSION`.

### Default Adapter

```php
use Lalaz\Auth\Adapters\WebSessionAdapter;

$adapter = new WebSessionAdapter();
$adapter->set('user_id', 123);
$adapter->get('user_id');  // Returns 123
$adapter->remove('user_id');
$adapter->destroy();       // Destroys entire session
```

### Custom Session Adapter

You can create a custom adapter by implementing `SessionInterface`:

```php
use Lalaz\Auth\Contracts\SessionInterface;

class RedisSessionAdapter implements SessionInterface
{
    public function __construct(
        private Redis $redis,
        private string $sessionId
    ) {}

    public function get(string $key): mixed
    {
        $data = $this->redis->hGet("session:{$this->sessionId}", $key);
        return $data !== false ? unserialize($data) : null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->redis->hSet(
            "session:{$this->sessionId}",
            $key,
            serialize($value)
        );
    }

    public function has(string $key): bool
    {
        return $this->redis->hExists("session:{$this->sessionId}", $key);
    }

    public function remove(string $key): void
    {
        $this->redis->hDel("session:{$this->sessionId}", $key);
    }

    public function destroy(): void
    {
        $this->redis->del("session:{$this->sessionId}");
    }

    public function regenerate(): void
    {
        // Generate new session ID and migrate data
        $data = $this->redis->hGetAll("session:{$this->sessionId}");
        $this->destroy();
        $this->sessionId = bin2hex(random_bytes(32));
        foreach ($data as $key => $value) {
            $this->redis->hSet("session:{$this->sessionId}", $key, $value);
        }
    }
}
```

## Security Best Practices

### 1. Regenerate Session on Login

Prevent session fixation attacks:

```php
// The SessionGuard handles this internally, but if doing manually:
$request->session()->regenerate();
$this->auth->guard('web')->login($user);
```

### 2. Use HTTPS

Always use HTTPS in production to protect session cookies:

```php
// In your server config or .htaccess
// Redirect HTTP to HTTPS
```

### 3. Configure Session Cookies

```php
// php.ini or runtime
ini_set('session.cookie_httponly', 1);  // Prevent JavaScript access
ini_set('session.cookie_secure', 1);    // HTTPS only
ini_set('session.cookie_samesite', 'Lax');  // CSRF protection
```

### 4. Set Session Lifetime

```php
// php.ini or runtime
ini_set('session.gc_maxlifetime', 3600);  // 1 hour
```

### 5. Implement CSRF Protection

Session-based auth requires CSRF tokens for forms:

```php
// In form
<input type="hidden" name="_token" value="<?= csrf_token() ?>">

// In middleware
if ($request->input('_token') !== $request->session()->get('csrf_token')) {
    abort(403, 'CSRF token mismatch');
}
```

## Remember Me Functionality

To implement "remember me":

```php
class AuthController
{
    public function login($request, $response)
    {
        $user = $this->auth->guard('web')->attempt([
            'email' => $request->input('email'),
            'password' => $request->input('password'),
        ]);

        if ($user) {
            $this->auth->guard('web')->login($user);
            
            // Remember me?
            if ($request->input('remember')) {
                // Create a remember token
                $token = bin2hex(random_bytes(32));
                $user->remember_token = hash('sha256', $token);
                $user->save();
                
                // Set long-lived cookie
                setcookie('remember_token', $token, [
                    'expires' => time() + (30 * 24 * 60 * 60), // 30 days
                    'path' => '/',
                    'httponly' => true,
                    'secure' => true,
                    'samesite' => 'Lax',
                ]);
            }
            
            return $response->redirect('/dashboard');
        }
    }
}
```

## Testing

### Unit Testing

```php
use PHPUnit\Framework\TestCase;
use Lalaz\Auth\Guards\SessionGuard;
use Lalaz\Auth\Contracts\UserProviderInterface;
use Lalaz\Auth\Contracts\SessionInterface;

class SessionGuardTest extends TestCase
{
    private SessionGuard $guard;
    private MockObject $provider;
    private MockObject $session;

    protected function setUp(): void
    {
        $this->session = $this->createMock(SessionInterface::class);
        $this->provider = $this->createMock(UserProviderInterface::class);
        $this->guard = new SessionGuard($this->session, $this->provider);
    }

    public function test_attempt_with_valid_credentials(): void
    {
        $user = new User(['id' => 1, 'email' => 'test@example.com']);
        
        $this->provider->method('retrieveByCredentials')->willReturn($user);
        $this->provider->method('validateCredentials')->willReturn(true);
        
        $result = $this->guard->attempt([
            'email' => 'test@example.com',
            'password' => 'password',
        ]);
        
        $this->assertSame($user, $result);
    }

    public function test_login_stores_user_id_in_session(): void
    {
        $user = new User(['id' => 123]);
        
        $this->session->expects($this->once())
            ->method('set')
            ->with('auth_user_id', 123);
        
        $this->guard->login($user);
    }
}
```

## Troubleshooting

### Session Not Persisting

**Problem:** User is logged out on every request.

**Solutions:**
1. Ensure `session_start()` is called
2. Check session save path permissions
3. Verify session cookie settings
4. Check if session is being destroyed

```php
// Debug session
var_dump(session_id());
var_dump($_SESSION);
```

### "Headers already sent" Error

**Problem:** Error when calling `session_start()`.

**Solution:** Ensure no output before session_start():
```php
<?php
// Must be at very top, no whitespace before
session_start();
```

### Session Expiring Too Soon

**Problem:** Users are logged out unexpectedly.

**Solution:** Increase session lifetime:
```php
ini_set('session.gc_maxlifetime', 7200);  // 2 hours
session_set_cookie_params(7200);
```

## Next Steps

- Learn about [JWT Guard](./jwt.md) for APIs
- Learn about [API Key Guard](./api-key.md) for integrations
- Set up [User Providers](../providers/index.md)
- Add [Authorization](../authorization.md) with roles and permissions
