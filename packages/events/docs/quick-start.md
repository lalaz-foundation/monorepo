# Quick Start

Get started with Lalaz Events in under 5 minutes.

## Installation

```bash
composer require lalaz/events
```

## Basic Setup

### 1. Create an Event Hub

```php
use Lalaz\Events\EventHub;

$hub = new EventHub();
```

### 2. Register Listeners

```php
// Simple closure listener
$hub->register('user.created', function ($user) {
    echo "User created: {$user['name']}\n";
});

// Multiple listeners for same event
$hub->register('user.created', function ($user) {
    sendWelcomeEmail($user['email']);
});
```

### 3. Trigger Events

```php
$hub->trigger('user.created', [
    'id' => 1,
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);
```

## Using Helper Functions

The simplest way to use events:

```php
// Register a listener
listen('order.placed', function ($order) {
    notifyWarehouse($order);
    sendConfirmationEmail($order);
});

// Dispatch the event
dispatch('order.placed', [
    'id' => 123,
    'items' => ['Widget A', 'Gadget B'],
    'total' => 99.99,
]);
```

## Using the Static Facade

```php
use Lalaz\Events\Events;

// First, set up an instance (or let it auto-resolve)
Events::setInstance(new EventHub());

// Register listeners
Events::register('payment.received', function ($payment) {
    recordTransaction($payment);
});

// Trigger events
Events::trigger('payment.received', $payment);
```

## Listener Priority

Control execution order with priorities (higher = first):

```php
// Validation runs first (priority 100)
$hub->register('order.placed', ValidateOrder::class, priority: 100);

// Payment processing second (priority 50)
$hub->register('order.placed', ProcessPayment::class, priority: 50);

// Notification last (default priority 0)
$hub->register('order.placed', SendNotification::class);
```

## Class-Based Listeners

Create reusable listener classes:

```php
class SendWelcomeEmail
{
    public function __invoke($user): void
    {
        $mailer = new Mailer();
        $mailer->send($user['email'], 'Welcome!', 'welcome-template');
    }
}

// Register the class
$hub->register('user.created', SendWelcomeEmail::class);
```

## Synchronous vs Asynchronous

```php
// Async (queued if driver available)
dispatch('heavy.task', $data);

// Always synchronous
dispatchSync('critical.event', $data);
```

## Checking for Listeners

```php
if (has_listeners('notification.send')) {
    dispatch('notification.send', $notification);
}

// Get all listeners
$listeners = $hub->getListeners('user.created');
```

## Removing Listeners

```php
// Remove specific listener
$hub->forget('user.created', SendWelcomeEmail::class);

// Remove all listeners for event
$hub->forget('user.created');

// Using helper
forget_event('user.created');
```

## Real-World Example

```php
use Lalaz\Events\EventHub;

$events = new EventHub();

// User registration flow
$events->register('user.registered', function ($user) {
    // Send welcome email
    $mailer->send($user['email'], 'Welcome to our platform!');
}, priority: 100);

$events->register('user.registered', function ($user) {
    // Create default user settings
    $settings->createDefaults($user['id']);
}, priority: 50);

$events->register('user.registered', function ($user) {
    // Track registration analytics
    $analytics->track('user_registered', $user);
}, priority: 10);

// In your registration controller
public function register(Request $request)
{
    $user = User::create($request->validated());
    
    dispatch('user.registered', [
        'id' => $user->id,
        'email' => $user->email,
        'name' => $user->name,
    ]);
    
    return redirect('/dashboard');
}
```

## Next Steps

- [Concepts](concepts.md) - Understand the architecture
- [Drivers](drivers.md) - Learn about sync, queue, and custom drivers
- [Listeners](listeners.md) - Create advanced listener classes
- [API Reference](api-reference.md) - Explore all methods
