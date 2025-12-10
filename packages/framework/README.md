<p align="center">
  <img src="https://raw.githubusercontent.com/lalaz-foundation/art/main/packages/framework-logo.svg" width="120" alt="Lalaz Framework">
</p>

<h1 align="center">Lalaz Framework</h1>

<p align="center">
  <strong>The foundation that powers everything.</strong>
</p>

<p align="center">
  <a href="https://php.net"><img src="https://img.shields.io/badge/php-%5E8.3-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP Version"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-green?style=flat-square" alt="License"></a>
  <a href="https://packagist.org/packages/lalaz/framework"><img src="https://img.shields.io/badge/version-1.0.0-blue?style=flat-square" alt="Version"></a>
  <a href="https://github.com/lalaz-foundation/framework/actions"><img src="https://img.shields.io/badge/tests-584%20passing-brightgreen?style=flat-square" alt="Tests"></a>
</p>

<p align="center">
  <a href="#-quick-start">Quick Start</a> •
  <a href="#-features">Features</a> •
  <a href="#-documentation">Documentation</a> •
  <a href="#-examples">Examples</a> •
  <a href="#-contributing">Contributing</a>
</p>

---

## What is Lalaz Framework?

Lalaz Framework is the **core runtime** for the Lalaz PHP Framework. It provides dependency injection, HTTP handling, routing, configuration, logging, and CLI infrastructure—everything you need to build modern PHP applications.

```php
// Define a route with dependency injection
$router->get('/users/{id}', function (Request $request, Response $response, UserService $service) {
    $user = $service->find($request->routeParam('id'));
    $response->json($user);
});
```

---

## ⚡ Quick Start

### Installation

```bash
composer require lalaz/framework
```

### 1. Create Your First Route (30 seconds)

```php
use Lalaz\Web\Routing\Router;
use Lalaz\Web\Http\Request;
use Lalaz\Web\Http\Response;

$router = new Router();

$router->get('/', function (Request $request, Response $response) {
    $response->json(['message' => 'Hello, Lalaz!']);
});

$router->get('/users/{id}', function (Request $request, Response $response) {
    $id = $request->routeParam('id');
    $response->json(['user' => ['id' => $id]]);
});
```

### 2. Use Dependency Injection

```php
use Lalaz\Container\Container;

$container = new Container();

// Register services
$container->singleton(UserService::class);
$container->bind(CacheInterface::class, RedisCache::class);

// Auto-wiring resolves dependencies automatically
$userService = $container->resolve(UserService::class);
```

### 3. Access Configuration

```php
use Lalaz\Config\Config;

Config::load(__DIR__ . '/.env');

$debug = Config::getBool('app.debug', false);
$dbHost = Config::getString('database.host', 'localhost');
```

**That's the basics!** For advanced routing, middleware, and more, keep reading.

---

## ✨ Features

<table>
<tr>
<td width="50%">

### 🏗️ Dependency Injection
PSR-11 compliant container with auto-wiring, scoped bindings, and method injection.

### 🚀 High-Performance Routing
Attribute-based routing, groups, middleware, and URL generation.

### 📝 Configuration
Environment-aware config with caching, typed getters, and dot notation.

</td>
<td width="50%">

### 📊 PSR-3 Logging
Multi-channel logging with rotating files, formatters, and custom writers.

### 💻 CLI Framework
Command infrastructure with dependency injection and code generators.

### 🛡️ Resilience Patterns
Circuit breaker and retry patterns for fault-tolerant applications.

</td>
</tr>
</table>

---

## 📖 Examples

### HTTP Request Handling

```php
use Lalaz\Web\Http\Request;

$request = Request::fromGlobals();

// Access request data
$id = $request->routeParam('id');
$page = $request->queryParam('page', 1);
$name = $request->input('name');

// JSON body
$data = $request->json();

// Content negotiation
if ($request->wantsJson()) {
    // Return JSON response
}
```

### HTTP Response Building

```php
use Lalaz\Web\Http\Response;

$response = new Response($_SERVER['HTTP_HOST']);

// JSON response
$response->json(['success' => true], 200);

// Redirect
$response->redirect('/dashboard');

// File download with streaming
$response->download('/path/to/file.pdf', 'report.pdf');

// Streaming response
$response->stream(function ($write) use ($data) {
    foreach ($data as $chunk) {
        $write(json_encode($chunk) . "\n");
    }
});
```

### Advanced Routing

```php
use Lalaz\Web\Routing\Router;
use Lalaz\Web\Routing\Attribute\Route;

$router = new Router();

// Route groups with middleware
$router->group([
    'prefix' => '/api/v1',
    'middleware' => [AuthMiddleware::class],
], function ($router) {
    $router->get('/users', [UserController::class, 'index']);
    $router->post('/users', [UserController::class, 'store']);
});

// Resource routes (RESTful)
$router->resource('posts', PostController::class);

// Named routes with URL generation
$router->get('/users/{id}', [UserController::class, 'show'])->name('users.show');
$url = $router->url()->to('users.show', ['id' => 123]); // /users/123
```

### Attribute-Based Routing

```php
use Lalaz\Web\Routing\Attribute\Route;

class UserController
{
    #[Route('GET', '/users')]
    public function index(): void { }

    #[Route('GET', '/users/{id}')]
    public function show(Request $request): void { }

    #[Route(['GET', 'POST'], '/users/search', middleware: [AuthMiddleware::class])]
    public function search(): void { }
}

$router->registerControllers([UserController::class]);
```

### Container & Dependency Injection

```php
use Lalaz\Container\Container;

$container = new Container();

// Singleton binding
$container->singleton(CacheInterface::class, RedisCache::class);

// Scoped binding (per-request)
$container->scoped(RequestContext::class);

// Method injection
$container->when(UserService::class)
    ->needs('setLogger')
    ->give(FileLogger::class);

// Service tagging
$container->tag(FileLogger::class, 'loggers');
$container->tag(DatabaseLogger::class, 'loggers');
$loggers = $container->tagged('loggers');
```

### Circuit Breaker Pattern

```php
use Lalaz\Support\Resilience\CircuitBreaker;

$breaker = CircuitBreaker::create()
    ->withFailureThreshold(5)
    ->withRecoveryTimeout(30)
    ->onTrip(fn ($e, $failures) => Log::error("Circuit tripped: {$failures} failures"))
    ->withFallback(fn () => ['status' => 'degraded']);

$result = $breaker->execute(function () use ($httpClient) {
    return $httpClient->get('/external-api');
});
```

---

## 🏗️ Architecture

```
┌──────────────────────────────────────────────────────────────────┐
│                        Your Application                          │
├──────────────────────────────────────────────────────────────────┤
│                                                                  │
│  Web Layer       Request → Router → Controller → Response        │
│                                                                  │
├──────────────────────────────────────────────────────────────────┤
│  Container       Dependency Injection & Service Resolution       │
├──────────────────────────────────────────────────────────────────┤
│  Config          Environment & Configuration Management          │
├──────────────────────────────────────────────────────────────────┤
│  Logging         Multi-channel PSR-3 Logging                     │
├──────────────────────────────────────────────────────────────────┤
│  Console         CLI Commands & Code Generation                  │
├──────────────────────────────────────────────────────────────────┤
│  Support         Collections, Resilience, Helpers                │
└──────────────────────────────────────────────────────────────────┘
```

**Key Components:**
- **Container** — PSR-11 DI container with auto-wiring
- **Router** — High-performance HTTP routing with groups and middleware
- **Request/Response** — Full HTTP abstraction with streaming
- **Config** — Environment-aware configuration with caching
- **LogManager** — Multi-channel PSR-3 logging
- **Console** — CLI framework with dependency injection

---

## 📁 Package Structure

```
src/
├── Config/              # Configuration management
│   ├── Config.php           # Static facade
│   └── ConfigRepository.php # Repository implementation
├── Console/             # CLI infrastructure
│   ├── Application.php      # Console application
│   ├── Input.php            # Input handling
│   ├── Output.php           # Output handling
│   └── Commands/            # Built-in commands
├── Container/           # Dependency injection
│   ├── Container.php        # Main container
│   ├── ContainerScope.php   # Scoped lifecycle
│   └── ServiceProvider.php  # Base provider
├── Logging/             # PSR-3 logging
│   ├── Log.php              # Static facade
│   ├── LogManager.php       # Multi-channel manager
│   └── Logger.php           # Logger implementation
├── Runtime/             # Application runtime
│   └── Application.php      # Global app singleton
├── Support/             # Utilities
│   ├── Collections/         # Collection class
│   └── Resilience/          # Circuit breaker, retry
└── Web/                 # HTTP layer
    ├── Http/                # Request/Response
    └── Routing/             # Router
```

---

## 📚 Documentation (where to read)

The canonical framework documentation is published on the site and in this repository under two locations:

- Site docs (recommended, used by the public docs site):
    - https://lalaz.dev/packages/framework
    - Repository path: `/docs/packages/framework`

- Local package docs for quick developer reference (inside this package):
    - `./docs/index.md` (package overview)
    - `./docs/quick-start.md` (quick start)
    - `./docs/api/*` (API reference pages, e.g. `./docs/api/container.md`, `./docs/api/request.md`)

If you previously clicked links that looked like `./docs/http.md` or `./docs/routing.md` — those were reorganized into the `api/` and `concepts/` folders. Use the site docs above (recommended) or browse `./docs/` in this package.

---

## 🔧 Configuration

### Logging Configuration

```php
// config/logging.php
return [
    'default' => 'app',
    'channels' => [
        'app' => [
            'driver' => 'daily',
            'path' => 'storage/logs/app.log',
            'level' => 'debug',
            'days' => 14,
        ],
        'security' => [
            'driver' => 'single',
            'path' => 'storage/logs/security.log',
            'level' => 'warning',
        ],
    ],
];
```

### Built-in CLI Commands

```bash
# Configuration
php lalaz config:cache          # Cache configuration
php lalaz config:clear          # Clear configuration cache

# Routes
php lalaz routes:list           # List all routes
php lalaz routes:cache          # Cache routes

# Code Generation
php lalaz craft:controller      # Create a new controller
php lalaz craft:model           # Create a new model
php lalaz craft:middleware      # Create a new middleware
php lalaz craft:command         # Create a new command

# Development
php lalaz serve                 # Start development server
```

---

## 📋 Requirements

| Requirement | Version |
|-------------|---------|
| PHP | ^8.3 |
| psr/log | ^3.0 |
| psr/container | ^2.0 |

---

## 🧪 Testing

```bash
# Run all tests
composer test

# Run with coverage
composer test:coverage

# Run specific test suite
./vendor/bin/phpunit --testsuite=Unit
./vendor/bin/phpunit --testsuite=Integration
```

---

## 🤝 Contributing

We welcome contributions! Here's how you can help:

1. **Fork** the repository
2. **Create** a feature branch (`git checkout -b feature/amazing-feature`)
3. **Commit** your changes (`git commit -m 'Add amazing feature'`)
4. **Push** to the branch (`git push origin feature/amazing-feature`)
5. **Open** a Pull Request

Please read our [Contributing Guide](../../CONTRIBUTING.md) for details on our code of conduct and development process.

---

## 🔒 Security

If you discover a security vulnerability, please **do not** open a public issue. Instead, email us at:

📧 **security@lalaz.dev**

We take security seriously and will respond promptly to verified vulnerabilities.

---

## 📄 License

Lalaz Framework is open-source software licensed under the [MIT License](LICENSE).

---

<p align="center">
  <sub>Built with ❤️ by the <a href="https://github.com/lalaz-foundation">Lalaz Foundation</a></sub>
</p>
