# Event Listeners

Listeners are the handlers that respond to events. This guide covers all the ways to create and use listeners.

## Listener Types

### 1. Closure Listeners

The simplest form - anonymous functions:

```php
$hub->register('user.created', function ($user) {
    sendWelcomeEmail($user['email']);
});

// With use clause for dependencies
$mailer = new Mailer();
$hub->register('user.created', function ($user) use ($mailer) {
    $mailer->send($user['email'], 'Welcome!');
});
```

**When to use:**
- Quick prototyping
- Simple one-off handlers
- When no reuse is needed

### 2. Invokable Class Listeners

Classes with `__invoke` method:

```php
class SendWelcomeEmail
{
    private Mailer $mailer;

    public function __construct(Mailer $mailer)
    {
        $this->mailer = $mailer;
    }

    public function __invoke($user): void
    {
        $this->mailer->send(
            $user['email'],
            'Welcome to our platform!',
            'emails/welcome',
            ['user' => $user]
        );
    }
}

// Register by class name
$hub->register('user.created', SendWelcomeEmail::class);
```

**When to use:**
- Reusable listeners
- When you need dependency injection
- Testable handlers

### 3. EventListener Subclass

For listeners handling multiple events:

```php
use Lalaz\Events\EventListener;

class UserActivityListener extends EventListener
{
    private ActivityLogger $logger;

    public function __construct(ActivityLogger $logger)
    {
        $this->logger = $logger;
    }

    public function subscribers(): array
    {
        return [
            'user.created',
            'user.updated',
            'user.deleted',
            'user.logged_in',
            'user.logged_out',
        ];
    }

    public function handle(mixed $event): void
    {
        $this->logger->log('user_activity', [
            'data' => $event,
            'timestamp' => time(),
        ]);
    }
}
```

**When to use:**
- One handler for multiple events
- Cross-cutting concerns (logging, auditing)
- Event aggregation

---

## Registering Listeners

### Basic Registration

```php
// Closure
$hub->register('event', function ($data) { /* ... */ });

// Class name (resolved via ListenerResolver)
$hub->register('event', MyListener::class);

// Instance
$hub->register('event', new MyListener($dependency));
```

### With Priority

Higher priority executes first:

```php
// Priority 100 - executes first
$hub->register('order.placed', ValidateInventory::class, priority: 100);

// Priority 50 - executes second
$hub->register('order.placed', ChargePayment::class, priority: 50);

// Priority 0 (default) - executes third
$hub->register('order.placed', SendConfirmation::class);

// Priority -10 - executes last
$hub->register('order.placed', NotifyAnalytics::class, priority: -10);
```

### Using Helper Function

```php
listen('user.created', SendWelcomeEmail::class);
listen('user.created', CreateDefaultSettings::class, priority: -10);
```

---

## Listener Resolution

The `ListenerResolver` converts class names to instances:

### Default Resolution

```php
// Uses `new $class()`
$resolver = new ListenerResolver();

// Resolved via:
$instance = new SendWelcomeEmail();
```

### Container Resolution

```php
// With PSR-11 container
$resolver = new ListenerResolver(
    fn($class) => $container->get($class)
);

// Or factory method
$resolver = ListenerResolver::from(
    fn($class) => $container->make($class)
);
```

### Custom Resolution

```php
$resolver = new ListenerResolver(function ($class) {
    // Custom instantiation logic
    if ($class === SendWelcomeEmail::class) {
        return new SendWelcomeEmail(
            new Mailer(),
            new TemplateEngine()
        );
    }
    
    return new $class();
});
```

---

## Listener Registry

Direct access to listener storage:

```php
use Lalaz\Events\ListenerRegistry;

$registry = new ListenerRegistry();

// Add listener
$registry->add('user.created', SendWelcomeEmail::class, priority: 10);

// Check if event has listeners
$registry->has('user.created'); // true

// Get listeners (sorted by priority)
$listeners = $registry->get('user.created');

// Get with metadata
$entries = $registry->getWithMetadata('user.created');
// [
//     ['listener' => SendWelcomeEmail::class, 'priority' => 10],
//     ['listener' => CreateSettings::class, 'priority' => 0],
// ]

// Count listeners
$count = $registry->count('user.created');

// Get all registered events
$events = $registry->getEvents();

// Remove specific listener
$registry->remove('user.created', SendWelcomeEmail::class);

// Remove all listeners for event
$registry->remove('user.created');

// Clear specific event
$registry->clear('user.created');

// Clear all
$registry->clear();
```

---

## Advanced Patterns

### Conditional Listeners

```php
class ConditionalEmailListener
{
    public function __invoke($user): void
    {
        // Only send for verified users
        if (!$user['email_verified']) {
            return;
        }

        $this->sendEmail($user);
    }
}
```

### Async-Aware Listeners

```php
class HeavyProcessingListener
{
    public function __invoke($data): void
    {
        // This will run in background if queue driver is enabled
        $this->processLargeDataset($data);
        $this->generateReports($data);
        $this->notifyStakeholders($data);
    }
}

// Force sync for critical path
dispatchSync('critical.event', $data);
```

### Event Transformation

```php
class EventEnricher
{
    public function __invoke($event): void
    {
        // Enrich event data
        $event['enriched_at'] = time();
        $event['environment'] = getenv('APP_ENV');
        
        // Trigger enriched event
        dispatch('enriched.event', $event);
    }
}
```

### Chain of Responsibility

```php
// Validation chain
$hub->register('order.placed', function ($order) {
    if ($order['total'] < 0) {
        throw new InvalidOrderException('Invalid total');
    }
}, priority: 100);

$hub->register('order.placed', function ($order) {
    if (empty($order['items'])) {
        throw new InvalidOrderException('No items');
    }
}, priority: 99);

// Processing (only runs if validation passes)
$hub->register('order.placed', ProcessOrder::class, priority: 50);
```

### Decorator Pattern

```php
class LoggingListenerDecorator
{
    private $inner;
    private Logger $logger;

    public function __construct($inner, Logger $logger)
    {
        $this->inner = $inner;
        $this->logger = $logger;
    }

    public function __invoke($data): void
    {
        $this->logger->info('Listener started', ['data' => $data]);
        
        try {
            ($this->inner)($data);
            $this->logger->info('Listener completed');
        } catch (\Exception $e) {
            $this->logger->error('Listener failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}

// Usage
$hub->register('event', new LoggingListenerDecorator(
    new ActualListener(),
    $logger
));
```

---

## Error Handling

### Default Behavior

Errors are logged, execution continues:

```php
$hub->register('event', function () {
    throw new \Exception('First listener failed');
});

$hub->register('event', function () {
    echo "Second listener runs anyway\n";
});

$hub->trigger('event', null);
// Output: Error logged, then "Second listener runs anyway"
```

### Stop on Error

```php
// Via driver
$driver->publish('event', $data, ['stop_on_error' => true]);

// First error stops execution
```

### Try-Catch in Listeners

```php
class SafeListener
{
    public function __invoke($data): void
    {
        try {
            $this->riskyOperation($data);
        } catch (RiskyException $e) {
            // Handle gracefully
            $this->logError($e);
            $this->notifyAdmin($e);
        }
    }
}
```

---

## Testing Listeners

### Unit Testing

```php
class SendWelcomeEmailTest extends TestCase
{
    public function test_sends_email_to_user(): void
    {
        $mailer = $this->createMock(Mailer::class);
        $mailer->expects($this->once())
            ->method('send')
            ->with('user@example.com', $this->anything());

        $listener = new SendWelcomeEmail($mailer);
        
        $listener(['email' => 'user@example.com', 'name' => 'John']);
    }
}
```

### Integration Testing

```php
class UserCreatedListenersTest extends TestCase
{
    public function test_all_listeners_execute(): void
    {
        $hub = new EventHub();
        $executed = [];

        $hub->register('user.created', function () use (&$executed) {
            $executed[] = 'listener1';
        });

        $hub->register('user.created', function () use (&$executed) {
            $executed[] = 'listener2';
        });

        $hub->trigger('user.created', ['id' => 1]);

        $this->assertEquals(['listener1', 'listener2'], $executed);
    }
}
```

### Testing with NullDriver

```php
public function test_events_are_dispatched(): void
{
    $driver = new NullDriver(recordEvents: true);
    $manager = new EventManager($driver);

    $manager->trigger('user.created', ['id' => 1]);

    $events = $driver->getRecordedEvents();
    $this->assertCount(1, $events);
    $this->assertEquals('user.created', $events[0]['event']);
}
```

---

## Best Practices

### 1. Single Responsibility

Each listener should do one thing:

```php
// Good
class SendWelcomeEmail { /* only sends email */ }
class CreateUserSettings { /* only creates settings */ }

// Bad
class HandleUserCreated {
    public function __invoke($user): void
    {
        $this->sendEmail($user);     // Multiple responsibilities
        $this->createSettings($user);
        $this->notifyAdmin($user);
    }
}
```

### 2. Use Dependency Injection

```php
// Good
class SendWelcomeEmail
{
    public function __construct(
        private Mailer $mailer,
        private TemplateEngine $templates
    ) {}
}

// Bad
class SendWelcomeEmail
{
    public function __invoke($user): void
    {
        $mailer = new Mailer();  // Hard dependency
    }
}
```

### 3. Keep Listeners Fast

```php
// Good - queue heavy work
class ProcessOrderListener
{
    public function __invoke($order): void
    {
        // Just dispatch to queue
        dispatch('order.process.heavy', $order);
    }
}

// Bad - block with heavy work
class ProcessOrderListener
{
    public function __invoke($order): void
    {
        sleep(30); // Long-running task
    }
}
```

### 4. Use Meaningful Event Names

```php
// Good
'user.registered'
'order.placed'
'payment.succeeded'
'cache.cleared'

// Bad
'event1'
'do_stuff'
'handle'
```
