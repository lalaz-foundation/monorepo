# Lalaz Auth Documentation

Welcome to the Lalaz Auth documentation. This guide will help you understand and implement authentication and authorization in your Lalaz applications.

## What is Lalaz Auth?

Lalaz Auth is a comprehensive authentication and authorization package that provides:

- **Authentication**: Verifying who a user is (login, tokens, API keys)
- **Authorization**: Determining what a user can do (roles, permissions)

## Table of Contents

### Getting Started
- [Quick Start](./quick-start.md) - Get authentication working in 5 minutes ⚡
- [Installation](./installation.md) - How to install and configure the package
- [Core Concepts](./concepts.md) - Understanding guards, providers, and context
- [Glossary](./glossary.md) - Authentication terminology explained

### Authentication Guards
- [Guards Overview](./guards/index.md) - Introduction to authentication guards
- [Session Guard](./guards/session.md) - Traditional session-based authentication
- [JWT Guard](./guards/jwt.md) - Token-based stateless authentication
- [API Key Guard](./guards/api-key.md) - Simple key-based authentication

### User Providers
- [Providers Overview](./providers/index.md) - How user data is retrieved
- [Model Provider](./providers/model.md) - ORM-based user retrieval
- [Generic Provider](./providers/generic.md) - Callback-based user retrieval

### Middlewares
- [Middlewares Overview](./middlewares/index.md) - Protecting routes
- [Authentication Middleware](./middlewares/authentication.md) - Requiring login
- [Authorization Middleware](./middlewares/authorization.md) - Role checks
- [Permission Middleware](./middlewares/permission.md) - Permission checks

### JWT Authentication
- [JWT Overview](./jwt/index.md) - Understanding JWT authentication
- [Token Encoding](./jwt/encoding.md) - Creating and validating tokens
- [Token Blacklist](./jwt/blacklist.md) - Revoking tokens
- [Signing Algorithms](./jwt/signers.md) - HMAC and RSA algorithms

### Authorization
- [Roles & Permissions](./authorization.md) - Role-based access control

### Helpers
- [Helper Functions](./helpers.md) - Convenient global functions

### Testing
- [Testing Guide](./testing.md) - How to run and write tests

### Examples
- [Web Application](./examples/web-app.md) - Full-stack example
- [REST API](./examples/api.md) - API-only example
- [Multi-Guard Setup](./examples/multi-guard.md) - Using multiple guards
- [Segregated Login Areas](./examples/segregated-areas.md) - Customer vs Admin portals

### Reference
- [API Reference](./api-reference.md) - Complete class and method reference

## Quick Example

Here's a simple example to get you started:

```php
<?php

use Lalaz\Auth\Middlewares\AuthenticationMiddleware;

// 1. Protect your routes
$router->get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(AuthenticationMiddleware::web('/login'));

// 2. In your controller, access the authenticated user
class DashboardController
{
    public function index($request, $response)
    {
        $user = user(); // Get authenticated user
        
        $response->view('dashboard', [
            'user' => $user,
            'isAdmin' => auth_context()->hasRole('admin'),
        ]);
    }
}
```

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                      Your Application                        │
├─────────────────────────────────────────────────────────────┤
│                      Middlewares                             │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────┐  │
│  │ Auth        │  │ Author-     │  │ Permission          │  │
│  │ Middleware  │  │ ization     │  │ Middleware          │  │
│  └──────┬──────┘  └──────┬──────┘  └──────────┬──────────┘  │
├─────────┼────────────────┼───────────────────┼──────────────┤
│         │                │                   │              │
│         ▼                ▼                   ▼              │
│  ┌──────────────────────────────────────────────────────┐   │
│  │                    AuthContext                        │   │
│  │  (Stores authenticated users per guard per request)   │   │
│  └───────────────────────┬──────────────────────────────┘   │
│                          │                                   │
│         ┌────────────────┼────────────────┐                 │
│         ▼                ▼                ▼                 │
│  ┌────────────┐   ┌────────────┐   ┌────────────┐          │
│  │  Session   │   │    JWT     │   │  API Key   │          │
│  │   Guard    │   │   Guard    │   │   Guard    │          │
│  └─────┬──────┘   └─────┬──────┘   └─────┬──────┘          │
│        │                │                │                  │
│        └────────────────┼────────────────┘                  │
│                         ▼                                    │
│              ┌─────────────────────┐                        │
│              │   User Provider     │                        │
│              │  (Model / Generic)  │                        │
│              └──────────┬──────────┘                        │
│                         │                                    │
│                         ▼                                    │
│              ┌─────────────────────┐                        │
│              │     Your Database   │                        │
│              └─────────────────────┘                        │
└─────────────────────────────────────────────────────────────┘
```

## Key Concepts at a Glance

| Concept | Description | Example |
|---------|-------------|---------|
| **Guard** | Handles how users authenticate | Session stores user in session, JWT validates tokens |
| **Provider** | Retrieves user data from storage | ModelProvider queries database using your User model |
| **Context** | Holds authenticated user state | Stores current user, allows role/permission checks |
| **Middleware** | Protects routes | Redirects to login or returns 401 if not authenticated |

## Next Steps

1. **New to Lalaz Auth?** Start with the [Quick Start](./quick-start.md) guide
2. Already familiar? Jump to [Installation](./installation.md) for setup details
3. Read [Core Concepts](./concepts.md) to understand the architecture
4. Choose your authentication strategy in [Guards](./guards/index.md)
5. Protect your routes with [Middlewares](./middlewares/index.md)
6. Check out [Examples](./examples/web-app.md) for complete implementations
7. Use the [Glossary](./glossary.md) as a reference for terminology
