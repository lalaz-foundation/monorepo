<p align="center">
  <img src="https://raw.githubusercontent.com/lalaz-foundation/art/main/packages/auth-logo.svg" width="120" alt="Lalaz Auth">
</p>

<h1 align="center">Lalaz Auth</h1>

<p align="center">
  <strong>Authentication made simple. Security made right.</strong>
</p>

<p align="center">
  <a href="https://php.net"><img src="https://img.shields.io/badge/php-%5E8.3-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP Version"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-green?style=flat-square" alt="License"></a>
  <a href="https://packagist.org/packages/lalaz/auth"><img src="https://img.shields.io/badge/version-1.0.0-blue?style=flat-square" alt="Version"></a>
  <a href="https://github.com/lalaz-foundation/framework/actions"><img src="https://img.shields.io/badge/tests-passing-brightgreen?style=flat-square" alt="Tests"></a>
</p>

<p align="center">
  <a href="#-quick-start">Quick Start</a> •
  <a href="#-features">Features</a> •
  <a href="#-documentation">Documentation</a> •
  <a href="#-examples">Examples</a> •
  <a href="#-contributing">Contributing</a>
</p>

---

## What is Lalaz Auth?

Lalaz Auth is a **zero-dependency** authentication package for the Lalaz Framework. It provides everything you need to secure your application—from traditional session-based login to modern JWT APIs—without the bloat.

```php
// That's it. Your route is now protected.
$router->get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(AuthenticationMiddleware::web());
```

---

## ⚡ Quick Start

### Installation

```bash
php lalaz package:add lalaz/auth
```

### 1. Protect a Route (30 seconds)

```php
use Lalaz\Auth\Middlewares\AuthenticationMiddleware;

// Web route - redirects to /login if not authenticated
$router->get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(AuthenticationMiddleware::web('/login'));
```

### 2. Get the Logged-in User

```php
// In your controller
public function index($request, $response)
{
    $user = user(); // That's it!
    
    return $response->view('dashboard', ['user' => $user]);
}
```

### 3. Check Permissions

```php
if (auth_context()->hasRole('admin')) {
    // Admin-only logic
}

if (auth_context()->hasPermission('posts.delete')) {
    // Can delete posts
}
```

**That's the basics!** For APIs, JWT tokens, and advanced features, keep reading.

---

## ✨ Features

<table>
<tr>
<td width="50%">

### 🔐 Multiple Auth Strategies
Session, JWT, and API Key authentication out of the box. Use what fits your project.

### 🎭 Multi-Guard Support
Different guards for different contexts. Web uses sessions, API uses JWT—simultaneously.

### 🎫 JWT Done Right
Stateless tokens with automatic refresh, blacklist support, and multiple signing algorithms.

</td>
<td width="50%">

### 👥 Roles & Permissions
Built-in RBAC. Check roles and permissions with simple, expressive methods.

### 🔌 Pluggable Providers  
Use the ORM provider or bring your own. Works with any user storage.

### ⚡ Zero Config
Sensible defaults that just work. Customize only when you need to.

</td>
</tr>
</table>

---

## 📖 Examples

### Web Application (Session Auth)

```php
// routes/web.php
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);

$router->group('/dashboard', function ($router) {
    $router->get('/', [DashboardController::class, 'index']);
    $router->get('/settings', [SettingsController::class, 'index']);
})->middleware(AuthenticationMiddleware::web('/login'));
```

```php
// app/Controllers/AuthController.php
class AuthController
{
    public function login($request, $response)
    {
        $user = auth()->attempt([
            'email' => $request->input('email'),
            'password' => $request->input('password'),
        ]);

        if ($user) {
            return $response->redirect('/dashboard');
        }

        return $response->redirect('/login?error=invalid');
    }

    public function logout($request, $response)
    {
        auth()->logout();
        return $response->redirect('/');
    }
}
```

### REST API (JWT Auth)

```php
// routes/api.php
$router->post('/auth/login', [ApiAuthController::class, 'login']);

$router->group('/api/v1', function ($router) {
    $router->get('/me', [UserController::class, 'me']);
    $router->get('/posts', [PostController::class, 'index']);
})->middleware(AuthenticationMiddleware::jwt());
```

```php
// app/Controllers/Api/ApiAuthController.php
class ApiAuthController
{
    public function login($request, $response)
    {
        $tokens = auth()->guard('jwt')->attemptWithTokens([
            'email' => $request->input('email'),
            'password' => $request->input('password'),
        ]);

        if (!$tokens) {
            return $response->json(['error' => 'Invalid credentials'], 401);
        }

        return $response->json([
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ]);
    }
}
```

### Role-Based Access Control

```php
use Lalaz\Auth\Middlewares\AuthorizationMiddleware;
use Lalaz\Auth\Middlewares\PermissionMiddleware;

// Only admins can access
$router->group('/admin', function ($router) {
    $router->get('/users', [AdminController::class, 'users']);
})->middleware([
    AuthenticationMiddleware::web('/login'),
    AuthorizationMiddleware::requireRoles('admin'),
]);

// Requires specific permission
$router->delete('/posts/{id}', [PostController::class, 'destroy'])
    ->middleware(PermissionMiddleware::all('posts.delete'));
```

---

## 🏗️ Architecture

```
┌──────────────────────────────────────────────────────────────────┐
│                        Your Application                          │
├──────────────────────────────────────────────────────────────────┤
│                                                                  │
│  Middlewares     AuthenticationMiddleware                        │
│                  AuthorizationMiddleware                         │
│                  PermissionMiddleware                            │
│                                                                  │
├──────────────────────────────────────────────────────────────────┤
│  AuthContext     Stores authenticated user per request           │
├──────────────────────────────────────────────────────────────────┤
│  Guards          SessionGuard   JwtGuard   ApiKeyGuard           │
├──────────────────────────────────────────────────────────────────┤
│  Providers       ModelUserProvider   GenericUserProvider         │
├──────────────────────────────────────────────────────────────────┤
│  Your Database   users, roles, permissions                       │
└──────────────────────────────────────────────────────────────────┘
```

**Key Concepts:**
- **Guard** — *How* users authenticate (session cookie, JWT token, API key)
- **Provider** — *Where* user data comes from (database, API, custom)
- **Context** — *Who* is currently authenticated (per-request state)

---

## 📚 Documentation

| Topic | Description |
|-------|-------------|
| [Installation](./docs/installation.md) | Setup and configuration |
| [Core Concepts](./docs/concepts.md) | Guards, Providers, and Context explained |
| [Session Auth](./docs/guards/session.md) | Traditional web authentication |
| [JWT Auth](./docs/guards/jwt.md) | Stateless API authentication |
| [API Keys](./docs/guards/api-key.md) | Service-to-service auth |
| [Middlewares](./docs/middlewares/index.md) | Protecting routes |
| [Roles & Permissions](./docs/authorization.md) | RBAC implementation |
| [Examples](./docs/examples/) | Complete working examples |
| [API Reference](./docs/api-reference.md) | Full class documentation |

---

## 🔧 Configuration

```php
// config/auth.php
return [
    'defaults' => [
        'guard' => 'web',
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        'api' => [
            'driver' => 'jwt',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'model',
            'model' => App\Models\User::class,
        ],
    ],

    'jwt' => [
        'secret' => env('JWT_SECRET'),
        'ttl' => 3600,
    ],
];
```

---

## 📋 Requirements

| Requirement | Version |
|-------------|---------||
| PHP | ^8.3 |
| lalaz/framework | ^1.0 |

**Optional:**
- `lalaz/web` — For session-based authentication
- `lalaz/orm` — For database user models

---

## 🤝 Contributing

We welcome contributions! Here's how you can help:

1. **Fork** the repository
2. **Create** a feature branch (`git checkout -b feature/amazing-feature`)
3. **Commit** your changes (`git commit -m 'Add amazing feature'`)
4. **Push** to the branch (`git push origin feature/amazing-feature`)
5. **Open** a Pull Request

Please read our [Contributing Guide](../../CONTRIBUTING.md) for details on our code of conduct and development process.

### Running Tests

```bash
composer test
```

---

## 🔒 Security

If you discover a security vulnerability, please **do not** open a public issue. Instead, email us at:

📧 **security@lalaz.dev**

We take security seriously and will respond promptly to verified vulnerabilities.

---

## 📄 License

Lalaz Auth is open-source software licensed under the [MIT License](LICENSE).

---

<p align="center">
  <sub>Built with ❤️ by the <a href="https://github.com/lalaz-foundation">Lalaz Foundation</a></sub>
</p>
