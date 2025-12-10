# Testing Guide

This guide covers testing strategies and best practices for the Lalaz Events package.

## Test Structure

```
tests/
├── bootstrap.php
├── Common/
│   ├── EventsUnitTestCase.php
│   ├── EventsIntegrationTestCase.php
│   ├── FakeEventListener.php
│   ├── FakeMultiEventListener.php
│   └── FakeThrowingListener.php
├── Unit/
│   ├── EventHubTest.php
│   ├── EventManagerTest.php
│   ├── ListenerRegistryTest.php
│   ├── ListenerResolverTest.php
│   ├── Drivers/
│   │   ├── SyncDriverTest.php
│   │   ├── QueueDriverTest.php
│   │   └── NullDriverTest.php
│   └── ...
└── Integration/
    ├── EventSystemIntegrationTest.php
    ├── EventDriversIntegrationTest.php
    └── ...
```

## Running Tests

```bash
# Run all tests
./vendor/bin/phpunit

# Run with testdox output
./vendor/bin/phpunit --testdox

# Run specific test file
./vendor/bin/phpunit tests/Unit/EventHubTest.php

# Run specific test method
./vendor/bin/phpunit --filter test_registers_listener

# Generate coverage report
./vendor/bin/phpunit --coverage-html coverage/
```

## Unit Testing

### Testing EventHub

```php
use PHPUnit\Framework\TestCase;
use Lalaz\Events\EventHub;

class EventHubTest extends TestCase
{
    private EventHub $hub;

    protected function setUp(): void
    {
        $this->hub = new EventHub();
    }

    public function test_registers_listener(): void
    {
        $executed = false;
        
        $this->hub->register('test', function () use (&$executed) {
            $executed = true;
        });
        
        $this->hub->trigger('test', null);
        
        $this->assertTrue($executed);
    }

    public function test_executes_listeners_by_priority(): void
    {
        $order = [];

        $this->hub->register('test', function () use (&$order) {
            $order[] = 'second';
        }, priority: 0);

        $this->hub->register('test', function () use (&$order) {
            $order[] = 'first';
        }, priority: 100);

        $this->hub->trigger('test', null);

        $this->assertEquals(['first', 'second'], $order);
    }

    public function test_forget_removes_listener(): void
    {
        $listener = fn() => null;
        
        $this->hub->register('test', $listener);
        $this->assertTrue($this->hub->hasListeners('test'));
        
        $this->hub->forget('test', $listener);
        $this->assertFalse($this->hub->hasListeners('test'));
    }
}
```

### Testing ListenerRegistry

```php
use Lalaz\Events\ListenerRegistry;

class ListenerRegistryTest extends TestCase
{
    public function test_adds_and_retrieves_listeners(): void
    {
        $registry = new ListenerRegistry();
        $listener = fn() => null;

        $registry->add('event', $listener);

        $this->assertTrue($registry->has('event'));
        $this->assertCount(1, $registry->get('event'));
    }

    public function test_sorts_by_priority(): void
    {
        $registry = new ListenerRegistry();

        $registry->add('event', 'low', priority: 0);
        $registry->add('event', 'high', priority: 100);
        $registry->add('event', 'medium', priority: 50);

        $listeners = $registry->get('event');

        $this->assertEquals(['high', 'medium', 'low'], $listeners);
    }

    public function test_removes_specific_listener(): void
    {
        $registry = new ListenerRegistry();
        $listener1 = fn() => 'one';
        $listener2 = fn() => 'two';

        $registry->add('event', $listener1);
        $registry->add('event', $listener2);

        $registry->remove('event', $listener1);

        $this->assertCount(1, $registry->get('event'));
    }
}
```

### Testing Drivers

```php
use Lalaz\Events\Drivers\SyncDriver;

class SyncDriverTest extends TestCase
{
    public function test_publishes_to_listeners(): void
    {
        $driver = new SyncDriver();
        $received = null;

        $driver->addListener('test', function ($data) use (&$received) {
            $received = $data;
        });

        $driver->publish('test', ['key' => 'value']);

        $this->assertEquals(['key' => 'value'], $received);
    }

    public function test_handles_errors_gracefully(): void
    {
        $driver = new SyncDriver();
        $secondExecuted = false;

        $driver->addListener('test', function () {
            throw new \Exception('First failed');
        });

        $driver->addListener('test', function () use (&$secondExecuted) {
            $secondExecuted = true;
        });

        $driver->publish('test', null);

        $this->assertTrue($secondExecuted);
    }
}
```

### Testing NullDriver

```php
use Lalaz\Events\Drivers\NullDriver;

class NullDriverTest extends TestCase
{
    public function test_records_events_when_enabled(): void
    {
        $driver = new NullDriver(recordEvents: true);

        $driver->publish('event1', ['id' => 1]);
        $driver->publish('event2', ['id' => 2]);

        $events = $driver->getRecordedEvents();

        $this->assertCount(2, $events);
        $this->assertEquals('event1', $events[0]['event']);
        $this->assertEquals('event2', $events[1]['event']);
    }

    public function test_does_not_record_when_disabled(): void
    {
        $driver = new NullDriver(recordEvents: false);

        $driver->publish('event', ['data']);

        $this->assertEmpty($driver->getRecordedEvents());
    }
}
```

## Integration Testing

### Testing Event Flow

```php
class EventSystemIntegrationTest extends TestCase
{
    public function test_complete_event_flow(): void
    {
        $hub = new EventHub();
        $results = [];

        // Register multiple listeners
        $hub->register('user.created', function ($user) use (&$results) {
            $results[] = "welcome_email:{$user['id']}";
        }, priority: 100);

        $hub->register('user.created', function ($user) use (&$results) {
            $results[] = "create_settings:{$user['id']}";
        }, priority: 50);

        $hub->register('user.created', function ($user) use (&$results) {
            $results[] = "analytics:{$user['id']}";
        }, priority: 0);

        // Trigger event
        $hub->trigger('user.created', ['id' => 123, 'email' => 'test@example.com']);

        // Assert execution order
        $this->assertEquals([
            'welcome_email:123',
            'create_settings:123',
            'analytics:123',
        ], $results);
    }
}
```

### Testing with Async Driver

```php
class AsyncDriverIntegrationTest extends TestCase
{
    public function test_async_driver_publishes(): void
    {
        $driver = new NullDriver(recordEvents: true);
        $hub = new EventHub(asyncDriver: $driver);

        $hub->trigger('test', ['data' => 'value']);

        $events = $driver->getRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertEquals('test', $events[0]['event']);
    }

    public function test_falls_back_to_sync_when_async_disabled(): void
    {
        $asyncDriver = new NullDriver(recordEvents: true);
        $hub = new EventHub(asyncDriver: $asyncDriver);
        
        $hub->setAsyncEnabled(false);
        
        $executed = false;
        $hub->register('test', function () use (&$executed) {
            $executed = true;
        });

        $hub->trigger('test', null);

        // Async driver should not be used
        $this->assertEmpty($asyncDriver->getRecordedEvents());
        // Sync should have executed
        $this->assertTrue($executed);
    }
}
```

## Testing Patterns

### Using Fake Listeners

```php
// tests/Common/FakeEventListener.php
class FakeEventListener extends EventListener
{
    public array $handledEvents = [];

    public function subscribers(): array
    {
        return ['fake.event'];
    }

    public function handle(mixed $event): void
    {
        $this->handledEvents[] = $event;
    }
}

// In test
public function test_event_listener_receives_events(): void
{
    $hub = new EventHub();
    $listener = new FakeEventListener();

    $hub->register('fake.event', $listener);
    $hub->trigger('fake.event', ['id' => 1]);
    $hub->trigger('fake.event', ['id' => 2]);

    $this->assertCount(2, $listener->handledEvents);
}
```

### Using Spies

```php
public function test_listener_called_with_correct_data(): void
{
    $hub = new EventHub();
    $spy = new class {
        public array $calls = [];
        
        public function __invoke($data): void
        {
            $this->calls[] = $data;
        }
    };

    $hub->register('test', $spy);
    $hub->trigger('test', ['user_id' => 42]);

    $this->assertCount(1, $spy->calls);
    $this->assertEquals(['user_id' => 42], $spy->calls[0]);
}
```

### Testing Error Scenarios

```php
public function test_error_in_listener_does_not_stop_others(): void
{
    $hub = new EventHub();
    $secondListenerExecuted = false;

    $hub->register('test', function () {
        throw new \RuntimeException('First listener failed');
    }, priority: 100);

    $hub->register('test', function () use (&$secondListenerExecuted) {
        $secondListenerExecuted = true;
    }, priority: 0);

    $hub->trigger('test', null);

    $this->assertTrue($secondListenerExecuted);
}
```

### Mocking Dependencies

```php
public function test_with_mock_registry(): void
{
    $registry = $this->createMock(ListenerRegistryInterface::class);
    
    $registry->expects($this->once())
        ->method('add')
        ->with('event', $this->anything(), 10);

    $driver = new SyncDriver(registry: $registry);
    $driver->addListener('event', fn() => null, priority: 10);
}
```

## Testing the Facade

```php
class EventsFacadeTest extends TestCase
{
    protected function setUp(): void
    {
        Events::setInstance(null);
    }

    public function test_facade_delegates_to_instance(): void
    {
        $hub = new EventHub();
        Events::setInstance($hub);

        $executed = false;
        Events::register('test', function () use (&$executed) {
            $executed = true;
        });

        Events::trigger('test', null);

        $this->assertTrue($executed);
    }
}
```

## Testing Helper Functions

```php
class HelpersTest extends TestCase
{
    protected function setUp(): void
    {
        Events::setInstance(new EventHub());
    }

    public function test_dispatch_triggers_event(): void
    {
        $executed = false;
        listen('test', function () use (&$executed) {
            $executed = true;
        });

        dispatch('test', null);

        $this->assertTrue($executed);
    }

    public function test_has_listeners_returns_correct_value(): void
    {
        $this->assertFalse(has_listeners('new.event'));

        listen('new.event', fn() => null);

        $this->assertTrue(has_listeners('new.event'));
    }
}
```

## Best Practices

### 1. Isolate Tests

```php
protected function setUp(): void
{
    // Fresh instance for each test
    $this->hub = new EventHub();
    Events::setInstance($this->hub);
}

protected function tearDown(): void
{
    // Clean up
    Events::setInstance(null);
}
```

### 2. Test One Thing Per Test

```php
// Good
public function test_registers_listener(): void { }
public function test_triggers_listener(): void { }
public function test_removes_listener(): void { }

// Bad
public function test_event_system(): void {
    // Testing registration, triggering, and removal
}
```

### 3. Use Descriptive Names

```php
// Good
public function test_higher_priority_listeners_execute_first(): void
public function test_error_in_listener_does_not_stop_execution(): void

// Bad
public function test_priority(): void
public function test_errors(): void
```

### 4. Test Edge Cases

```php
public function test_trigger_with_no_listeners(): void
{
    $hub = new EventHub();
    
    // Should not throw
    $hub->trigger('no.listeners', ['data']);
    
    $this->assertTrue(true);
}

public function test_forget_nonexistent_listener(): void
{
    $hub = new EventHub();
    
    // Should not throw
    $hub->forget('nonexistent', fn() => null);
    
    $this->assertTrue(true);
}
```

## PHPUnit Configuration

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         colors="true">
    
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
    </testsuites>
    
    <source>
        <include>
            <directory>src</directory>
        </include>
    </source>
</phpunit>
```
