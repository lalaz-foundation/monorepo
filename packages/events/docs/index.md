# Lalaz Events Documentation

Welcome to the Lalaz Events documentation. This package provides a powerful, driver-based event dispatching system for PHP applications.

## Table of Contents

1. [Quick Start](quick-start.md) - Get up and running in minutes
2. [Concepts](concepts.md) - Core concepts and architecture
3. [Drivers](drivers.md) - Event driver system
4. [Listeners](listeners.md) - Creating and managing listeners
5. [API Reference](api-reference.md) - Complete API documentation
6. [Testing](testing.md) - Testing guide and best practices

## Overview

The Events package provides a flexible event system with:

- **Driver-Based Architecture**: Swap between sync, queue, or custom drivers
- **Priority System**: Control listener execution order
- **Multiple APIs**: Use helpers, facade, or direct instantiation
- **Async Support**: Queue events for background processing

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     Application Layer                        │
│   dispatch() / Events::trigger() / $hub->trigger()          │
├─────────────────────────────────────────────────────────────┤
│           EventHub / EventManager (Dispatcher)               │
│         Coordinates sync/async driver selection              │
├─────────────────────────────────────────────────────────────┤
│                        Drivers                               │
│  ┌───────────┐  ┌───────────┐  ┌───────────┐  ┌──────────┐ │
│  │SyncDriver │  │QueueDriver│  │NullDriver │  │ Custom   │ │
│  │ (inline)  │  │ (queued)  │  │ (testing) │  │ Driver   │ │
│  └───────────┘  └───────────┘  └───────────┘  └──────────┘ │
├─────────────────────────────────────────────────────────────┤
│                    ListenerRegistry                          │
│              Storage for event → listeners                   │
├─────────────────────────────────────────────────────────────┤
│                    ListenerResolver                          │
│             Resolves class names → instances                 │
└─────────────────────────────────────────────────────────────┘
```

## Quick Example

```php
use Lalaz\Events\EventHub;

// Create the event hub
$hub = new EventHub();

// Register listeners
$hub->register('user.registered', function ($user) {
    // Send welcome email
    sendWelcomeEmail($user['email']);
});

$hub->register('user.registered', function ($user) {
    // Create default settings
    createUserSettings($user['id']);
}, priority: -10); // Runs after email

// Trigger the event
$hub->trigger('user.registered', [
    'id' => 1,
    'email' => 'user@example.com',
    'name' => 'John Doe',
]);
```

## Key Features

### Multiple Entry Points

```php
// Helper functions
dispatch('event', $data);
listen('event', $listener);

// Static facade
Events::trigger('event', $data);
Events::register('event', $listener);

// Direct usage
$hub->trigger('event', $data);
$hub->register('event', $listener);
```

### Flexible Listeners

```php
// Closure
$hub->register('event', fn($data) => handle($data));

// Invokable class
$hub->register('event', EmailNotifier::class);

// EventListener subclass
$hub->register('event', new UserActivityListener());
```

### Async Support

```php
// Configure with queue driver
$hub = EventHub::withQueueDriver('events');

// Events are queued and processed by workers
$hub->trigger('heavy.task', $data);

// Force sync when needed
$hub->triggerSync('critical.event', $data);
```

## Getting Help

- Check the [API Reference](api-reference.md) for detailed method signatures
- Review [Concepts](concepts.md) for architectural understanding
- See [Testing](testing.md) for testing strategies
- Explore [Drivers](drivers.md) for transport options
