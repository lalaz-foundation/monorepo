# Introduction

Lalaz is a modern PHP framework designed for building web applications and APIs with simplicity and performance in mind.

## Requirements

- **PHP 8.3** or higher
- **Composer** 2.x

## Starters

Lalaz offers two starter templates to help you get started quickly:

### API Starter (`lalaz/api`)

A minimal REST API starter for building JSON APIs. This starter uses only the `lalaz/framework` package and is ideal for:

- RESTful APIs
- Microservices
- Backend services
- Headless applications

**What's included:**
- HTTP routing with support for route groups and resource routes
- Request/Response handling with JSON support
- Configuration management via `.env` files
- Built-in development server
- CLI scaffolding commands

[Get started with the API Starter →](/start-here/api-quickstart)

### Web Starter (`lalaz/web`)

A full-featured web application starter that includes the `lalaz/framework` and `lalaz/web` packages. This starter is ideal for:

- Traditional web applications
- Full-stack applications with views
- Applications requiring sessions and cookies
- CSRF-protected forms

**What's included:**
- Everything from the API starter
- Twig templating engine
- Session management
- Cookie handling
- CSRF protection

[Get started with the Web Starter →](/start-here/web-quickstart)

## Project Structure

Both starters follow a similar directory structure:

```
my-app/
├── app/
│   └── Controllers/          # Your application controllers
├── config/
│   └── app.php               # Application configuration
├── public/
│   └── index.php             # Entry point
├── routes/
│   └── api.php               # Route definitions
├── storage/                  # Logs and cache (writable)
├── .env                      # Environment variables
├── .env.example              # Example environment file
├── composer.json
└── lalaz                     # CLI binary
```

## Next Steps

Choose your path:

- **Building an API?** Start with the [API Quickstart](/start-here/api-quickstart)
- **Building a web app with views?** Start with the [Web Quickstart](/start-here/web-quickstart)

Or dive into the essentials:

- [Configuration](/essentials/configuration) - Environment variables and config files
- [Routing](/essentials/routing) - Define your application routes
- [Controllers](/essentials/controllers) - Handle HTTP requests
- [CLI Commands](/essentials/cli) - Use the Lalaz command-line tools
