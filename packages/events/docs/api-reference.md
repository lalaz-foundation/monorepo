# API Reference

Complete API documentation for the Lalaz Events package.

## EventHub

Main event dispatcher with driver-based architecture.

### Constructor

```php
public function __construct(
    ?EventDriverInterface $asyncDriver = null,
    ?ListenerRegistryInterface $registry = null,
    ?ListenerResolverInterface $resolver = null
)
```

### Methods

#### register

Register a listener for an event.

```php
public function register(
    string $event,
    callable|EventListener|string $listener,
    int $priority = 0
): void
```

#### forget

Remove a listener from an event.

```php
public function forget(
    string $event,
    callable|EventListener|string|null $listener = null
): void
```

#### trigger

Trigger an event (async by default if driver available).

```php
public function trigger(string $event, mixed $data): void
```

#### triggerSync

Trigger listeners synchronously.

```php
public function triggerSync(string $event, mixed $data): void
```

#### hasListeners

Check if an event has any listeners.

```php
public function hasListeners(string $event): bool
```

#### getListeners

Get all listeners for an event.

```php
public function getListeners(string $event): array
```

#### setAsyncEnabled

Enable or disable async dispatching.

```php
public function setAsyncEnabled(bool $enabled): void
```

#### isAsyncEnabled

Check if async is enabled.

```php
public function isAsyncEnabled(): bool
```

#### setAsyncDriver

Set the async driver.

```php
public function setAsyncDriver(?EventDriverInterface $driver): void
```

#### getAsyncDriver

Get the current async driver.

```php
public function getAsyncDriver(): ?EventDriverInterface
```

#### getSyncDriver

Get the sync driver.

```php
public function getSyncDriver(): SyncDriver
```

### Factory Methods

#### withQueueDriver

Create an EventHub with Queue driver.

```php
public static function withQueueDriver(
    string $queue = 'events',
    int $priority = 9
): self
```

#### syncOnly

Create an EventHub for sync-only operation.

```php
public static function syncOnly(): self
```

---

## EventManager

Alternative event dispatcher with additional features.

### Constructor

```php
public function __construct(
    ?EventDriverInterface $asyncDriver = null,
    bool $asyncEnabled = true,
    ?ListenerRegistryInterface $registry = null,
    ?ListenerResolverInterface $resolver = null
)
```

### Factory Methods

#### withQueueDriver

```php
public static function withQueueDriver(
    string $queue = 'events',
    int $priority = 9
): self
```

#### syncOnly

```php
public static function syncOnly(): self
```

#### forTesting

Create a manager with null driver for testing.

```php
public static function forTesting(bool $recordEvents = true): self
```

---

## ListenerRegistry

In-memory storage for event listeners.

### Methods

#### add

Add a listener for an event.

```php
public function add(
    string $event,
    callable|EventListener|string $listener,
    int $priority = 0
): void
```

#### remove

Remove a listener from an event.

```php
public function remove(
    string $event,
    callable|EventListener|string|null $listener = null
): void
```

#### has

Check if event has listeners.

```php
public function has(string $event): bool
```

#### get

Get listeners sorted by priority.

```php
public function get(string $event): array
```

#### getWithMetadata

Get listeners with priority metadata.

```php
public function getWithMetadata(string $event): array
// Returns: [['listener' => ..., 'priority' => int], ...]
```

#### clear

Clear listeners.

```php
public function clear(?string $event = null): void
```

#### count

Get listener count for an event.

```php
public function count(string $event): int
```

#### getEvents

Get all registered events.

```php
public function getEvents(): array
```

---

## ListenerResolver

Resolves listener class names to instances.

### Constructor

```php
public function __construct(?callable $resolver = null)
```

### Methods

#### resolve

Resolve a class name to an instance.

```php
public function resolve(string $class): object
```

### Factory Methods

#### from

Create from a resolver callable.

```php
public static function from(callable $resolver): self
```

---

## EventListener

Abstract base class for event listeners.

### Abstract Methods

#### subscribers

Return array of event names this listener subscribes to.

```php
abstract public function subscribers(): array
```

#### handle

Handle an event payload.

```php
abstract public function handle(mixed $event): void
```

---

## SyncDriver

Synchronous event driver.

### Constructor

```php
public function __construct(
    ?ListenerRegistryInterface $registry = null,
    ?ListenerResolverInterface $resolver = null
)
```

### Methods

#### publish

Publish an event.

```php
public function publish(string $event, mixed $data, array $options = []): void
```

Options:
- `stop_on_error` (bool): Stop on first error

#### addListener

Register a listener.

```php
public function addListener(
    string $event,
    callable|EventListener|string $listener,
    int $priority = 0
): void
```

#### removeListener

Remove a listener.

```php
public function removeListener(
    string $event,
    callable|EventListener|string|null $listener = null
): void
```

#### hasListeners

Check for listeners.

```php
public function hasListeners(string $event): bool
```

#### getListeners

Get listeners.

```php
public function getListeners(string $event): array
```

#### isAvailable

Always returns true.

```php
public function isAvailable(): bool
```

#### getName

Returns 'sync'.

```php
public function getName(): string
```

#### getRegistry

Get the listener registry.

```php
public function getRegistry(): ListenerRegistryInterface
```

#### getResolver

Get the listener resolver.

```php
public function getResolver(): ListenerResolverInterface
```

---

## QueueDriver

Queue-based event driver.

### Constructor

```php
public function __construct(
    string $queue = 'events',
    int $priority = 9,
    ?int $delay = null,
    ?callable $dispatcher = null,
    ?EventJobFactoryInterface $jobFactory = null,
    ?QueueAvailabilityCheckerInterface $availabilityChecker = null
)
```

### Methods

#### publish

Publish an event to queue.

```php
public function publish(string $event, mixed $data, array $options = []): void
```

Options:
- `queue` (string): Override queue name
- `priority` (int): Override priority
- `delay` (int): Override delay

#### isAvailable

Check if queue is available.

```php
public function isAvailable(): bool
```

#### getName

Returns 'queue'.

```php
public function getName(): string
```

#### getQueue

Get queue name.

```php
public function getQueue(): string
```

#### getPriority

Get default priority.

```php
public function getPriority(): int
```

#### getDelay

Get default delay.

```php
public function getDelay(): ?int
```

---

## NullDriver

No-op driver for testing.

### Constructor

```php
public function __construct(bool $recordEvents = false)
```

### Methods

#### publish

Record event (if enabled) but don't execute.

```php
public function publish(string $event, mixed $data, array $options = []): void
```

#### isAvailable

Always returns true.

```php
public function isAvailable(): bool
```

#### getName

Returns 'null'.

```php
public function getName(): string
```

#### getRecordedEvents

Get recorded events.

```php
public function getRecordedEvents(): array
// Returns: [['event' => string, 'data' => mixed, 'options' => array], ...]
```

#### clearRecordedEvents

Clear recorded events.

```php
public function clearRecordedEvents(): void
```

---

## Events (Facade)

Static helper facade.

### Methods

#### setInstance

Set the event dispatcher instance.

```php
public static function setInstance(?EventDispatcherInterface $instance): void
```

#### getInstance

Get the event dispatcher instance.

```php
public static function getInstance(): ?EventDispatcherInterface
```

#### register

Register a listener.

```php
public static function register(
    string $eventName,
    callable|EventListener|string $listener,
    int $priority = 0
): void
```

#### forget

Remove a listener.

```php
public static function forget(
    string $eventName,
    callable|EventListener|string|null $listener = null
): void
```

#### trigger

Trigger an event (async if enabled).

```php
public static function trigger(string $eventName, mixed $event): void
```

#### triggerSync

Trigger an event synchronously.

```php
public static function triggerSync(string $eventName, mixed $event): void
```

#### hasListeners

Check for listeners.

```php
public static function hasListeners(string $eventName): bool
```

#### getListeners

Get listeners.

```php
public static function getListeners(string $eventName): array
```

---

## Helper Functions

### dispatch

Dispatch an event (async if available).

```php
function dispatch(string $event, mixed $payload = null): void
```

### dispatchSync

Dispatch an event synchronously.

```php
function dispatchSync(string $event, mixed $payload = null): void
```

### event

Alias for dispatch.

```php
function event(string $event, mixed $payload = null): void
```

### listen

Register a listener.

```php
function listen(
    string $event,
    callable|string $listener,
    int $priority = 0
): void
```

### forget_event

Remove a listener.

```php
function forget_event(
    string $event,
    callable|string|null $listener = null
): void
```

### has_listeners

Check for listeners.

```php
function has_listeners(string $event): bool
```

---

## Interfaces

### EventDispatcherInterface

Main dispatcher interface (extends EventRegistrarInterface, EventPublisherInterface, EventIntrospectionInterface).

```php
interface EventDispatcherInterface extends
    EventRegistrarInterface,
    EventPublisherInterface,
    EventIntrospectionInterface
{
}
```

### EventRegistrarInterface

```php
interface EventRegistrarInterface
{
    public function register(
        string $event,
        callable|EventListener|string $listener,
        int $priority = 0
    ): void;
    
    public function forget(
        string $event,
        callable|EventListener|string|null $listener = null
    ): void;
}
```

### EventPublisherInterface

```php
interface EventPublisherInterface
{
    public function trigger(string $event, mixed $data): void;
    public function triggerSync(string $event, mixed $data): void;
}
```

### EventIntrospectionInterface

```php
interface EventIntrospectionInterface
{
    public function hasListeners(string $event): bool;
    public function getListeners(string $event): array;
}
```

### EventDriverInterface

```php
interface EventDriverInterface
{
    public function publish(string $event, mixed $data, array $options = []): void;
    public function isAvailable(): bool;
    public function getName(): string;
}
```

### ListenerRegistryInterface

```php
interface ListenerRegistryInterface
{
    public function add(
        string $event,
        callable|EventListener|string $listener,
        int $priority = 0
    ): void;
    
    public function remove(
        string $event,
        callable|EventListener|string|null $listener = null
    ): void;
    
    public function has(string $event): bool;
    public function get(string $event): array;
    public function getWithMetadata(string $event): array;
    public function clear(?string $event = null): void;
}
```

### ListenerResolverInterface

```php
interface ListenerResolverInterface
{
    public function resolve(string $class): object;
}
```
