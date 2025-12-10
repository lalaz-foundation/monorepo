# Lalaz Events

A powerful, driver-based event dispatching system for PHP 8.3+ with support for synchronous and asynchronous event handling.

## Features

- **Driver-Based Architecture**: Extensible driver system for different transport mechanisms
- **Sync & Async Support**: Execute listeners inline or via queue workers
- **Priority-Based Listeners**: Control listener execution order with priorities
- **Multiple Drivers**: Sync, Queue, and Null drivers included
- **Helper Functions**: Convenient global functions for common operations
- **Static Facade**: Simple static API for quick event dispatching
- **Service Provider**: Easy integration with Lalaz framework
- **Listener Classes**: Object-oriented event listener support
- **Type-Safe**: Full PHP 8.3+ type declarations

## Installation

```bash
composer require lalaz/events
```

## Quick Start

### Basic Usage

```php
use Lalaz\Events\EventHub;

$hub = new EventHub();

// Register a listener
$hub->register('user.created', function ($data) {
    sendWelcomeEmail($data['email']);
});

// Trigger the event
$hub->trigger('user.created', [
    'id' => 1,
    'email' => 'john@example.com',
]);
```

### Using Helper Functions

```php
// Register a listener
listen('order.placed', function ($order) {
    notifyWarehouse($order);
});

// Dispatch an event
dispatch('order.placed', $order);

// Dispatch synchronously
dispatchSync('cache.cleared', ['keys' => $keys]);

// Check for listeners
if (has_listeners('payment.received')) {
    dispatch('payment.received', $payment);
}
```

### Using the Static Facade

```php
use Lalaz\Events\Events;

Events::register('user.logged_in', function ($user) {
    updateLastLogin($user);
});

Events::trigger('user.logged_in', $user);
```

## Event Dispatching

### EventHub

The main event dispatcher with driver-based architecture:

```php
use Lalaz\Events\EventHub;

// Create with default settings (sync only)
$hub = new EventHub();

// Create with queue driver for async
$hub = EventHub::withQueueDriver('events', priority: 5);

// Create sync-only instance
$hub = EventHub::syncOnly();
```

### EventManager

Alternative dispatcher with more configuration options:

```php
use Lalaz\Events\EventManager;

// With queue driver
$manager = EventManager::withQueueDriver('events');

// Sync only
$manager = EventManager::syncOnly();

// For testing
$manager = EventManager::forTesting();
```

### Registering Listeners

```php
// Closure listener
$hub->register('user.created', function ($data) {
    // Handle event
});

// Class listener
$hub->register('user.created', SendWelcomeEmail::class);

// EventListener instance
$hub->register('user.created', new SendWelcomeEmailListener());

// With priority (higher = first)
$hub->register('user.created', AuditLog::class, priority: 100);
$hub->register('user.created', SendEmail::class, priority: 50);
```

### Triggering Events

```php
// Async (if driver available) or sync fallback
$hub->trigger('user.created', $userData);

// Always synchronous
$hub->triggerSync('cache.invalidated', $keys);
```

### Removing Listeners

```php
// Remove specific listener
$hub->forget('user.created', SendWelcomeEmail::class);

// Remove all listeners for event
$hub->forget('user.created');
```

### Checking Listeners

```php
if ($hub->hasListeners('order.shipped')) {
    $hub->trigger('order.shipped', $order);
}

// Get all listeners
$listeners = $hub->getListeners('user.created');
```

## Drivers

### SyncDriver

Executes listeners inline in the current process:

```php
use Lalaz\Events\Drivers\SyncDriver;

$driver = new SyncDriver();
$driver->addListener('test', fn($data) => print_r($data));
$driver->publish('test', ['message' => 'Hello']);
```

### QueueDriver

Dispatches events to a queue for async processing:

```php
use Lalaz\Events\Drivers\QueueDriver;

$driver = new QueueDriver(
    queue: 'events',
    priority: 9,
    delay: null
);

// Events are queued and processed by workers
$driver->publish('email.send', $emailData);
```

### NullDriver

For testing - optionally records events:

```php
use Lalaz\Events\Drivers\NullDriver;

$driver = new NullDriver(recordEvents: true);
$driver->publish('test', ['data' => 'value']);

// Get recorded events
$events = $driver->getRecordedEvents();
```

### Custom Drivers

Implement `EventDriverInterface`:

```php
use Lalaz\Events\Contracts\EventDriverInterface;

class RedisDriver implements EventDriverInterface
{
    public function publish(string $event, mixed $data, array $options = []): void
    {
        $this->redis->publish($event, json_encode($data));
    }

    public function isAvailable(): bool
    {
        return $this->redis->isConnected();
    }

    public function getName(): string
    {
        return 'redis';
    }
}
```

## Event Listeners

### Closure Listeners

```php
$hub->register('user.created', function ($user) {
    // Handle the event
    sendWelcomeEmail($user['email']);
});
```

### Class Listeners

```php
class SendWelcomeEmail
{
    public function __invoke($user): void
    {
        // Send email
    }
}

$hub->register('user.created', SendWelcomeEmail::class);
```

### EventListener Class

For listeners subscribing to multiple events:

```php
use Lalaz\Events\EventListener;

class UserActivityListener extends EventListener
{
    public function subscribers(): array
    {
        return [
            'user.created',
            'user.updated',
            'user.deleted',
        ];
    }

    public function handle(mixed $event): void
    {
        // Handle any of the subscribed events
        $this->logActivity($event);
    }
}
```

### Priority

Higher priority listeners execute first:

```php
// Executes first (priority 100)
$hub->register('order.placed', ValidateInventory::class, priority: 100);

// Executes second (priority 50)
$hub->register('order.placed', ProcessPayment::class, priority: 50);

// Executes last (priority 0, default)
$hub->register('order.placed', SendConfirmation::class);
```

## Listener Registry

Direct access to listener storage:

```php
use Lalaz\Events\ListenerRegistry;

$registry = new ListenerRegistry();

// Add listeners
$registry->add('event', $listener, priority: 10);

// Check existence
$registry->has('event'); // true

// Get listeners (sorted by priority)
$listeners = $registry->get('event');

// Get with metadata
$data = $registry->getWithMetadata('event');
// [['listener' => ..., 'priority' => 10], ...]

// Count
$count = $registry->count('event');

// Get all events
$events = $registry->getEvents();

// Clear
$registry->clear('event'); // Clear specific
$registry->clear();        // Clear all
```

## Listener Resolver

Resolves listener class names to instances:

```php
use Lalaz\Events\ListenerResolver;

// Default (uses new)
$resolver = new ListenerResolver();

// With container
$resolver = new ListenerResolver(
    fn($class) => $container->get($class)
);

// Or using factory method
$resolver = ListenerResolver::from(
    fn($class) => $container->make($class)
);

// Resolve listener
$instance = $resolver->resolve(SendWelcomeEmail::class);
```

## Async with Queue Driver

### Configuration

```php
use Lalaz\Events\EventHub;
use Lalaz\Events\Drivers\QueueDriver;

// Create hub with queue driver
$hub = new EventHub(
    asyncDriver: new QueueDriver(
        queue: 'events',
        priority: 9,
        delay: 5 // 5 second delay
    )
);

// Or use factory method
$hub = EventHub::withQueueDriver('events', priority: 5);
```

### Control Async Behavior

```php
// Disable async temporarily
$hub->setAsyncEnabled(false);
$hub->trigger('event', $data); // Runs sync

// Re-enable
$hub->setAsyncEnabled(true);

// Check status
if ($hub->isAsyncEnabled()) {
    // Async is enabled
}
```

### Per-Event Options

```php
// Queue driver accepts options
$driver->publish('email.send', $data, [
    'queue' => 'high-priority',
    'priority' => 1,
    'delay' => 60,
]);
```

## Event Jobs

### EventJob

Used by QueueDriver to process events:

```php
use Lalaz\Events\EventJob;

$job = new EventJob();
$job->handle([
    'event_name' => 'user.created',
    'event_data' => json_encode($userData),
]);
```

### EventJobFactory

Creates event job instances:

```php
use Lalaz\Events\EventJobFactory;

$factory = new EventJobFactory();
$dispatcher = $factory->create('events', priority: 5, delay: 10);
$dispatcher->dispatch($payload);
```

## Service Provider

Register the event system with Lalaz:

```php
use Lalaz\Events\EventServiceProvider;

$provider = new EventServiceProvider($container);
$provider->register();

// Access from container
$events = $container->get(EventDispatcherInterface::class);
```

## Static Facade

The `Events` class provides a static interface:

```php
use Lalaz\Events\Events;

// Set instance
Events::setInstance($hub);

// Or auto-resolves from Application context

// Register
Events::register('event', $listener, priority: 10);

// Trigger
Events::trigger('event', $data);
Events::triggerSync('event', $data);

// Query
Events::hasListeners('event');
Events::getListeners('event');

// Remove
Events::forget('event', $listener);
Events::forget('event'); // All
```

## Helper Functions

Available globally when the package is loaded:

```php
// Dispatch (async if available)
dispatch('event.name', $payload);

// Dispatch sync
dispatchSync('event.name', $payload);

// Alias for dispatch
event('event.name', $payload);

// Register listener
listen('event.name', $listener, priority: 0);

// Remove listener
forget_event('event.name', $listener);
forget_event('event.name'); // All

// Check listeners
has_listeners('event.name');
```

## Error Handling

### Default Behavior

Errors are logged but don't stop other listeners:

```php
$hub->register('test', function () {
    throw new \Exception('Listener failed');
});

$hub->register('test', function () {
    // This still runs
});

$hub->trigger('test', null);
// First listener error is logged
// Second listener executes
```

### Stop on Error

```php
// Via driver options
$driver->publish('event', $data, [
    'stop_on_error' => true
]);
```

## Testing

### Using NullDriver

```php
use Lalaz\Events\EventManager;
use Lalaz\Events\Drivers\NullDriver;

$driver = new NullDriver(recordEvents: true);
$manager = new EventManager($driver);

// Trigger events
$manager->trigger('user.created', ['id' => 1]);
$manager->trigger('user.updated', ['id' => 1]);

// Assert events were dispatched
$events = $driver->getRecordedEvents();
$this->assertCount(2, $events);
$this->assertEquals('user.created', $events[0]['event']);
```

### Using forTesting Factory

```php
$manager = EventManager::forTesting();

$manager->trigger('event', $data);

$events = $manager->getAsyncDriver()->getRecordedEvents();
```

### Mocking

```php
$hub = $this->createMock(EventDispatcherInterface::class);

$hub->expects($this->once())
    ->method('trigger')
    ->with('user.created', $this->anything());
```

## Running Tests

```bash
# Run tests
composer test

# Run with coverage
composer test:coverage

# Run specific test
./vendor/bin/phpunit tests/Unit/EventHubTest.php
```

## Requirements

- PHP 8.3 or higher
- Optional: lalaz/queue for async support

## License

MIT License. See [LICENSE](LICENSE) for details.
