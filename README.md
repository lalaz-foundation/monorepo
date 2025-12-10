<p align="center">
  <img src="https://lalaz.dev/logo.svg" width="120" alt="Lalaz Logo">
</p>

<h1 align="center">Lalaz</h1>

<p align="center">
  <strong>A modern, modular PHP framework for building fast APIs and web applications.</strong>
</p>

<p align="center">
  <a href="https://github.com/lalaz-foundation/lalaz/actions/workflows/tests.yml">
    <img src="https://github.com/lalaz-foundation/lalaz/actions/workflows/tests.yml/badge.svg" alt="Tests">
  </a>
  <a href="https://github.com/lalaz-foundation/lalaz/actions/workflows/quality.yml">
    <img src="https://github.com/lalaz-foundation/lalaz/actions/workflows/quality.yml/badge.svg" alt="Code Quality">
  </a>
  <a href="https://packagist.org/packages/lalaz/framework">
    <img src="https://img.shields.io/packagist/v/lalaz/framework.svg" alt="Latest Version">
  </a>
  <a href="https://php.net/">
    <img src="https://img.shields.io/badge/php-%3E%3D8.3-8892BF.svg" alt="PHP Version">
  </a>
  <a href="LICENSE">
    <img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="License">
  </a>
</p>

<p align="center">
  <a href="https://lalaz.dev/docs">Documentation</a> •
  <a href="#quick-start">Quick Start</a> •
  <a href="#packages">Packages</a> •
  <a href="#contributing">Contributing</a>
</p>

---

## ✨ Features

- **🚀 Express-style routing** — Simple, intuitive API for defining routes
- **📦 Modular architecture** — Use only what you need, add packages as you grow
- **⚡ High performance** — Optimized for speed with route caching and efficient DI
- **🔒 Security first** — Built-in WAF, CSRF protection, and secure defaults
- **🛠️ Developer experience** — CLI tools, hot reload, and clear error messages
- **📚 Well documented** — Comprehensive docs and real-world examples

## 🚀 Quick Start

### Create a new project

```bash
composer create-project lalaz/api my-api
cd my-api
php lalaz serve
```

Visit `http://localhost:8000` — your API is running!

### Or add to an existing project

```bash
composer require lalaz/framework
```

### Hello World

```php
<?php

use Lalaz\Runtime\Http\HttpApplication;

require __DIR__ . '/vendor/autoload.php';

$app = new HttpApplication();

$app->get('/', fn() => ['message' => 'Hello, Lalaz!']);

$app->get('/users/{id}', fn(string $id) => [
    'id' => $id,
    'name' => "User {$id}"
]);

$app->run();
```

## 📦 Packages

Lalaz is built as a collection of independent packages. Install only what you need:

| Package | Description |
|---------|-------------|
| [lalaz/framework](packages/framework) | Core runtime, routing, DI container |
| [lalaz/auth](packages/auth) | Authentication & authorization |
| [lalaz/database](packages/database) | Database connections & query builder |
| [lalaz/orm](packages/orm) | Active Record ORM |
| [lalaz/cache](packages/cache) | Caching (Redis, Memcached, File) |
| [lalaz/queue](packages/queue) | Background job processing |
| [lalaz/events](packages/events) | Event dispatcher |
| [lalaz/validator](packages/validator) | Data validation |
| [lalaz/storage](packages/storage) | File storage abstraction |
| [lalaz/scheduler](packages/scheduler) | Task scheduling |
| [lalaz/reactive](packages/reactive) | Reactive components |
| [lalaz/wire](packages/wire) | Livewire-style components |
| [lalaz/waf](packages/waf) | Web Application Firewall |
| [lalaz/web](packages/web) | HTTP utilities & middleware |

### Installing packages

```bash
php lalaz package:add lalaz/orm
```

Packages auto-register their providers and publish configurations automatically.

## 🏗️ Project Starters

Get started quickly with our project templates:

### API Starter (Minimal)

Perfect for REST APIs and microservices:

```bash
composer create-project lalaz/api my-api
```

### App Starter (Full-stack)

Complete web application with views, auth, and more:

```bash
composer create-project lalaz/app my-app
```

## 📖 Documentation

Visit [lalaz.dev/docs](https://lalaz.dev/docs) for comprehensive documentation:

- [Getting Started](https://lalaz.dev/docs/getting-started)
- [Routing](https://lalaz.dev/docs/routing)
- [Controllers](https://lalaz.dev/docs/http/controllers)
- [Middleware](https://lalaz.dev/docs/http/middleware)
- [Database](https://lalaz.dev/docs/database)
- [Authentication](https://lalaz.dev/docs/security/authentication)
- [Testing](https://lalaz.dev/docs/testing)
- [Deployment](https://lalaz.dev/docs/deployment)

## 🗂️ Repository Structure

```
lalaz/
├── packages/           # Framework packages (published to Packagist)
│   ├── framework/      # Core runtime
│   ├── auth/           # Authentication
│   ├── orm/            # ORM
│   └── ...
├── starters/           # Project templates
│   ├── api/            # API starter
│   └── app/            # Full-stack starter
├── docs/               # Documentation source
└── tools/              # Development scripts
```

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guide](CONTRIBUTING.md) for details.

### Development Setup

```bash
git clone https://github.com/lalaz-foundation/lalaz.git
cd lalaz
composer install

# Run tests
cd packages/framework
composer install
vendor/bin/phpunit
```

### Branch Structure

- `main` — Stable releases
- `develop` — Active development

## 📋 Requirements

- PHP 8.3 or higher
- Composer 2.x
- Extensions: mbstring, json, openssl

## 📄 License

Lalaz is open-source software licensed under the [MIT License](LICENSE).

---

<p align="center">
  Made with ❤️ by the <a href="https://github.com/lalaz-foundation">Lalaz Foundation</a>
</p>
