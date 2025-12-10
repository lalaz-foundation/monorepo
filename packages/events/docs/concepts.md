# Core Concepts

This document explains the fundamental concepts and architecture of the Lalaz Events package.

## Architecture Overview

The package follows a layered architecture with clear separation of concerns:

```
Application Code (dispatch/listen helpers, Events facade)
                    ↓
         EventHub / EventManager
           (Event Dispatchers)
                    ↓
    ┌───────────────┼───────────────┐
    ↓               ↓               ↓
SyncDriver     QueueDriver     Custom Drivers
    ↓               ↓               ↓
ListenerRegistry ← Storage →  External Systems
    ↓                            (Queue, Redis)
ListenerResolver → Listener Execution
```

## Core Components

### EventHub

The primary event dispatcher that coordinates sync and async event handling:

```php
$hub = new EventHub(
    asyncDriver: $queueDriver,    // Optional async driver
    registry: $registry,          // Optional custom registry
    resolver: $resolver           // Optional custom resolver
);
```

**Responsibilities:**
- Register and remove listeners
- Route events to appropriate driver
- Manage async/sync mode switching

### EventManager

Alternative dispatcher with similar capabilities plus testing helpers:

```php
// Production use
$manager = EventManager::withQueueDriver('events');

// Testing use
$manager = EventManager::forTesting();
```

### ListenerRegistry

In-memory storage for event-to-listener mappings:

```php
$registry = new ListenerRegistry();

$registry->add('user.created', $listener, priority: 10);
$registry->add('user.created', $anotherListener, priority: 5);

// Get sorted by priority (highest first)
$listeners = $registry->get('user.created');
```

**Features:**
- Priority-based sorting
- Metadata storage (priority per listener)
- Event enumeration
- Count queries

### ListenerResolver

Converts listener class names to callable instances:

```php
// Default: uses `new $class()`
$resolver = new ListenerResolver();

// With container
$resolver = new ListenerResolver(fn($class) => $container->get($class));

// Resolve
$instance = $resolver->resolve(SendWelcomeEmail::class);
```

## Event Lifecycle

### 1. Registration

```php
$hub->register('user.created', SendWelcomeEmail::class, priority: 50);
```

Internally:
1. EventHub delegates to SyncDriver
2. SyncDriver adds to ListenerRegistry
3. ListenerRegistry stores with priority metadata

### 2. Triggering

```php
$hub->trigger('user.created', $userData);
```

Internally:
1. EventHub checks if async is available
2. If async: delegates to async driver (e.g., QueueDriver)
3. If sync: delegates to SyncDriver
4. Driver retrieves listeners from registry
5. Driver invokes each listener with event data

### 3. Execution

```php
// For sync execution
foreach ($listeners as $listener) {
    try {
        $this->invokeListener($listener, $data);
    } catch (\Throwable $e) {
        $this->handleError($event, $e, $options);
    }
}
```

## Driver System

Drivers implement `EventDriverInterface`:

```php
interface EventDriverInterface
{
    public function publish(string $event, mixed $data, array $options = []): void;
    public function isAvailable(): bool;
    public function getName(): string;
}
```

### Driver Selection

```
trigger() called
      ↓
Is async enabled? ─────────────────→ No → Use SyncDriver
      │
      Yes
      ↓
Is async driver set? ──────────────→ No → Use SyncDriver
      │
      Yes
      ↓
Is async driver available? ────────→ No → Use SyncDriver
      │
      Yes
      ↓
Use AsyncDriver (Queue, Redis, etc.)
```

### Built-in Drivers

| Driver | Purpose | Availability |
|--------|---------|--------------|
| SyncDriver | Inline execution | Always |
| QueueDriver | Background jobs | When Queue package loaded |
| NullDriver | Testing/no-op | Always |

## Listener Types

### 1. Closures

```php
$hub->register('event', function ($data) {
    // Handle inline
});
```

**Pros:** Simple, quick to write
**Cons:** Not reusable, can't be serialized

### 2. Invokable Classes

```php
class SendEmail
{
    public function __invoke($data): void
    {
        // Handle
    }
}

$hub->register('event', SendEmail::class);
```

**Pros:** Reusable, testable, can have dependencies
**Cons:** Requires class definition

### 3. EventListener Subclasses

```php
class ActivityLogger extends EventListener
{
    public function subscribers(): array
    {
        return ['user.created', 'user.updated', 'user.deleted'];
    }

    public function handle(mixed $event): void
    {
        // Handle any subscribed event
    }
}
```

**Pros:** One class handles multiple events
**Cons:** Less specific handling per event

## Priority System

Listeners are sorted by priority (descending):

```
Priority 100: ValidationListener   ← Executes first
Priority 50:  BusinessLogicListener
Priority 0:   NotificationListener
Priority -10: LoggingListener      ← Executes last
```

Use cases:
- **High (50-100):** Validation, authorization
- **Default (0):** Normal business logic
- **Low (-50 to -10):** Logging, cleanup, analytics

## Async vs Sync

### Synchronous (Default)

```php
$hub->triggerSync('event', $data);
```

- Executes immediately in current process
- Blocks until all listeners complete
- Errors can be caught

### Asynchronous

```php
$hub = EventHub::withQueueDriver('events');
$hub->trigger('event', $data);
```

- Event is serialized and queued
- Worker processes event later
- Non-blocking for caller
- Better for heavy operations

### Sticky Sync Flag

```php
$hub->setAsyncEnabled(false);
$hub->trigger('event', $data); // Runs sync
$hub->setAsyncEnabled(true);
```

## Error Handling

### Default: Log and Continue

```php
$hub->register('event', function () {
    throw new \Exception('Failed');
});

$hub->register('event', function () {
    // Still runs
});

$hub->trigger('event', $data);
// First listener fails, error logged
// Second listener still executes
```

### Stop on Error

```php
// Via driver options
$driver->publish('event', $data, ['stop_on_error' => true]);
```

## Events Facade

Static wrapper for convenient access:

```php
// Set instance once
Events::setInstance($hub);

// Or auto-resolves from Application context
$context = Application::context();
$events = $context->events();
```

Usage:
```php
Events::register('event', $listener);
Events::trigger('event', $data);
Events::triggerSync('event', $data);
Events::hasListeners('event');
Events::getListeners('event');
Events::forget('event');
```

## Helper Functions

Global convenience functions:

| Function | Description |
|----------|-------------|
| `dispatch($event, $data)` | Trigger event (async if available) |
| `dispatchSync($event, $data)` | Trigger event synchronously |
| `event($event, $data)` | Alias for dispatch |
| `listen($event, $listener, $priority)` | Register listener |
| `forget_event($event, $listener)` | Remove listener |
| `has_listeners($event)` | Check for listeners |

## Service Provider Integration

```php
use Lalaz\Events\EventServiceProvider;

$provider = new EventServiceProvider($container);
$provider->register();

// Registers:
// - ListenerRegistryInterface
// - ListenerResolverInterface
// - EventDriverInterface (SyncDriver)
// - EventDispatcherInterface (EventHub)
```

## Design Principles

### Single Responsibility (SRP)
- ListenerRegistry: Only stores listeners
- ListenerResolver: Only resolves class names
- Drivers: Only handle transport

### Dependency Inversion (DIP)
- EventHub depends on interfaces, not implementations
- Drivers can be swapped without changing EventHub

### Open/Closed (OCP)
- New drivers can be added without modifying existing code
- Custom resolvers extend behavior

### Interface Segregation (ISP)
- Small, focused interfaces
- EventDispatcherInterface extends smaller interfaces
