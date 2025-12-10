# Examples

Complete implementation examples for different use cases.

## Available Examples

### [Web Application](./web-app.md)
Complete session-based authentication for traditional web applications with login, registration, and protected routes.

### [REST API](./api.md)
JWT-based stateless authentication for APIs with token issuance, refresh, and revocation.

### [Multi-Guard Setup](./multi-guard.md)
Using multiple authentication guards for different user types (admin, API, web).

### [Segregated Login Areas](./segregated-areas.md)
Separate login pages and areas for different user types (customers vs admins) with independent sessions.

## Quick Reference

| Example | Guard Type | Use Case |
|---------|------------|----------|
| Web App | Session | Traditional websites |
| REST API | JWT | Mobile apps, SPAs |
| Multi-Guard | Multiple | Complex applications |
| Segregated Areas | Multiple Sessions | Customer + Admin portals |

## Choosing the Right Approach

```
┌────────────────────────────────────────────────────────┐
│                  What are you building?                │
└────────────────────────────────────────────────────────┘
                          │
          ┌───────────────┼───────────────┐
          ▼               ▼               ▼
    ┌──────────┐    ┌──────────┐    ┌──────────┐
    │ Web App  │    │   API    │    │   Both   │
    │ (HTML)   │    │(JSON/XML)│    │          │
    └────┬─────┘    └────┬─────┘    └────┬─────┘
         │               │               │
         ▼               ▼               ▼
    ┌──────────┐    ┌──────────┐    ┌──────────┐
    │ Session  │    │   JWT    │    │  Multi   │
    │  Guard   │    │  Guard   │    │  Guard   │
    └──────────┘    └──────────┘    └──────────┘
```

## Common Features Across Examples

All examples include:

- ✅ User registration
- ✅ User login
- ✅ Logout/token revocation
- ✅ Protected routes
- ✅ User profile access
- ✅ Role-based access control
- ✅ Error handling

## Getting Started

1. **Choose your example** based on your use case
2. **Copy the configuration** to your project
3. **Implement the User model** with `AuthenticatableInterface`
4. **Set up routes** with appropriate middlewares
5. **Test authentication** flow

## Directory Structure

Each example follows this general structure:

```
app/
├── Controllers/
│   ├── AuthController.php
│   └── DashboardController.php
├── Models/
│   └── User.php
└── Middlewares/
    └── (optional custom middlewares)

config/
└── auth.php

routes/
└── web.php (or api.php)
```
