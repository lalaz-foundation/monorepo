```markdown
# Lalaz Web Documentation

Welcome to the Lalaz Web documentation. This guide will help you understand and use the web components in your Lalaz applications.

## What is Lalaz Web?

Lalaz Web is a comprehensive web package that provides:

- **HTTP Utilities**: Session management, cookies, redirects, and request environment
- **Security**: CSRF protection, session fingerprinting, and security headers
- **View System**: Twig-based templating, components, and form builders

## Table of Contents

### Getting Started
- [Quick Start](./quick-start.md) - Get started with web features in 5 minutes ⚡
- [Installation](./installation.md) - How to install and configure the package
- [Core Concepts](./concepts.md) - Understanding sessions, views, and security
- [Glossary](./glossary.md) - Web terminology explained

### HTTP Features
- [HTTP Overview](./http/index.md) - Introduction to HTTP utilities
- [Session Manager](./http/session-manager.md) - Server-side session handling
- [Redirect Response](./http/redirect-response.md) - Fluent redirects
- [Cookie Policy](./http/cookie-policy.md) - Secure cookie management
- [HTTP Environment](./http/http-environment.md) - Request detection utilities
- [View Data Bag](./http/view-data-bag.md) - Request data passing
- [Flash Messages](./http/flash-messages.md) - One-time session messages

### Security Features
- [Security Overview](./security/index.md) - Security components guide
- [CSRF Protection](./security/csrf-protection.md) - Stateless CSRF tokens
- [Session Fingerprinting](./security/fingerprint.md) - Session hijacking prevention
- [CSRF Middleware](./security/csrf-middleware.md) - Route protection
- [Security Headers](./security/security-headers.md) - HTTP security headers

### View System
- [View Overview](./view/index.md) - Introduction to the view system
- [Template Engine](./view/template-engine.md) - Twig integration
- [View Context](./view/view-context.md) - Shared view data
- [Form Builder](./view/form-builder.md) - HTML form generation
- [Error Bag](./view/error-bag.md) - Validation error handling
- [View Functions](./view/view-functions.md) - Custom Twig functions
- [Components](./view/components.md) - Reusable view components

### Middlewares
- [Middlewares Overview](./middlewares/index.md) - HTTP middleware guide
- [Method Spoofing](./middlewares/method-spoofing.md) - PUT/PATCH/DELETE support
- [CSRF Middleware](./middlewares/csrf.md) - CSRF validation
- [Security Headers](./middlewares/security-headers.md) - Header injection

### Helpers
- [Helper Functions](./helpers.md) - Convenient global functions

### Testing
- [Testing Guide](./testing.md) - How to run and write tests

### Examples
- [Form Handling](./examples/form-handling.md) - Complete form example
- [Flash Messages](./examples/flash-messages.md) - User notifications
- [Components](./examples/components.md) - Building UI components

### Reference
- [API Reference](./api-reference.md) - Complete class and method reference

## Quick Example

Here's a simple example to get you started:

```php
<?php

use Lalaz\Web\View\View;
use Lalaz\Web\Http\RedirectResponse;

class PostController
{
    public function create($request, $response)
    {
        return View::make('posts/create', [
            'errors' => errors(),
            'old' => old(),
        ]);
    }

    public function store($request, $response)
    {
        // Validate and save...
        
        flash('success', 'Post created successfully!');
        
        return redirect('/posts');
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
│  │ Method      │  │ CSRF        │  │ Security Headers    │  │
│  │ Spoofing    │  │ Middleware  │  │ Middleware          │  │
│  └──────┬──────┘  └──────┬──────┘  └──────────┬──────────┘  │
├─────────┼────────────────┼───────────────────┼──────────────┤
│         │                │                   │              │
│         ▼                ▼                   ▼              │
│  ┌──────────────────────────────────────────────────────┐   │
│  │                  Session Manager                      │   │
│  │  (Manages sessions with fingerprint protection)       │   │
│  └───────────────────────┬──────────────────────────────┘   │
│                          │                                   │
│         ┌────────────────┼────────────────┐                 │
│         ▼                ▼                ▼                 │
│  ┌────────────┐   ┌────────────┐   ┌────────────┐          │
│  │  ViewData  │   │   Flash    │   │  CSRF      │          │
│  │   Bag      │   │  Messages  │   │  Tokens    │          │
│  └─────┬──────┘   └─────┬──────┘   └─────┬──────┘          │
│        │                │                │                  │
│        └────────────────┼────────────────┘                  │
│                         ▼                                    │
│              ┌─────────────────────┐                        │
│              │   Template Engine   │                        │
│              │      (Twig)         │                        │
│              └──────────┬──────────┘                        │
│                         │                                    │
│                         ▼                                    │
│              ┌─────────────────────┐                        │
│              │     HTML Output     │                        │
│              └─────────────────────┘                        │
└─────────────────────────────────────────────────────────────┘
```

## Key Concepts at a Glance

| Concept | Description | Example |
|---------|-------------|---------|
| **Session Manager** | Handles server-side sessions | Stores user data securely with fingerprinting |
| **CSRF Protection** | Prevents cross-site request forgery | Stateless tokens via cookies |
| **View Context** | Shares data across views | Pass user data to all templates |
| **Component** | Reusable UI building blocks | Create buttons, cards, modals |
| **Flash Message** | One-time session messages | Show success/error notifications |

## Next Steps

1. **New to Lalaz Web?** Start with the [Quick Start](./quick-start.md) guide
2. Already familiar? Jump to [Installation](./installation.md) for setup details
3. Read [Core Concepts](./concepts.md) to understand the architecture
4. Explore [HTTP Features](./http/index.md) for session and request handling
5. Learn about [Security](./security/index.md) for CSRF and headers
6. Master the [View System](./view/index.md) for templating
7. Use the [Glossary](./glossary.md) as a reference for terminology

```
