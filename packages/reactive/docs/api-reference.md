# API Reference

Complete API documentation for the Lalaz Reactive package.

## Table of Contents

- [ReactiveComponent](#reactivecomponent)
- [ReactiveManager](#reactivemanager)
- [ComponentRegistry](#componentregistry)
- [ReactiveController](#reactivecontroller)
- [Exceptions](#exceptions)
- [Traits](#traits)

---

## ReactiveComponent

Base class for all reactive components.

**Namespace:** `Lalaz\Reactive\ReactiveComponent`

### Properties

| Property | Type | Visibility | Description |
|----------|------|------------|-------------|
| `$id` | `string` | protected | Unique component identifier |
| `$name` | `string` | protected | Component class name |
| `$mountParams` | `array` | protected | Parameters passed to mount() |
| `$listeners` | `array` | protected | Registered event listeners |
| `$dispatchQueue` | `array` | protected | Events to dispatch |
| `$redirectTo` | `?string` | protected | Redirect URL |
| `$notifications` | `array` | protected | Queued notifications |
| `$errors` | `array` | protected | Validation errors |

### Methods

#### Lifecycle Methods

##### `mount(...$params): void`

Initialize the component with parameters.

```php
public function mount(int $productId, string $variant = 'default'): void
{
    $this->product = Product::find($productId);
    $this->variant = $variant;
}
```

**Parameters:**
- `...$params` - Variable parameters passed during mounting

---

##### `render(): string`

Render the component HTML. **Required to implement.**

```php
public function render(): string
{
    return "<div>{$this->title}</div>";
}
```

**Returns:** `string` - HTML output

---

##### `updated(string $property): void`

Called when a property is updated.

```php
public function updated(string $property): void
{
    if ($property === 'quantity') {
        $this->total = $this->price * $this->quantity;
    }
}
```

**Parameters:**
- `$property` - Name of the updated property

---

##### `hydrate(array $state): void`

Restore component from serialized state.

```php
$component->hydrate([
    'id' => 'comp-123',
    'name' => 'Counter',
    'properties' => ['count' => 5],
]);
```

**Parameters:**
- `$state` - Serialized state array

---

##### `dehydrate(): array`

Serialize component state.

```php
$state = $component->dehydrate();
// Returns: ['id' => '...', 'name' => '...', 'properties' => [...], ...]
```

**Returns:** `array` - Serialized state

---

#### Property Methods

##### `setProperty(string $property, mixed $value): void`

Set a public property value with type casting.

```php
$component->setProperty('count', '42'); // Cast to int if property is typed
```

**Parameters:**
- `$property` - Property name
- `$value` - New value

---

##### `getProperty(string $property): mixed`

Get a public property value.

```php
$count = $component->getProperty('count');
```

**Parameters:**
- `$property` - Property name

**Returns:** `mixed` - Property value or null

---

##### `reset(string ...$properties): void`

Reset properties to their default values.

```php
// Reset specific properties
$this->reset('name', 'email');

// Reset all public properties (no arguments)
$this->reset();
```

**Parameters:**
- `...$properties` - Property names to reset

---

#### State Methods

##### `getId(): string`

Get the component ID.

```php
$id = $component->getId(); // 'counter-abc123'
```

**Returns:** `string` - Component ID

---

##### `getName(): string`

Get the component name.

```php
$name = $component->getName(); // 'App\Components\Counter'
```

**Returns:** `string` - Component class name

---

##### `setId(string $id): void`

Set the component ID.

```php
$component->setId('custom-id-123');
```

**Parameters:**
- `$id` - New ID

---

##### `setName(string $name): void`

Set the component name.

```php
$component->setName('App\\Components\\Counter');
```

**Parameters:**
- `$name` - Component class name

---

##### `getMountParams(): array`

Get the mount parameters.

```php
$params = $component->getMountParams(); // ['productId' => 42]
```

**Returns:** `array` - Mount parameters

---

##### `setMountParams(array $params): void`

Set the mount parameters.

```php
$component->setMountParams(['productId' => 42]);
```

**Parameters:**
- `$params` - Mount parameters

---

#### Event Methods

##### `dispatch(string $event, array $data = []): void`

Dispatch an event.

```php
$this->dispatch('item:added', ['id' => $itemId, 'name' => $itemName]);
```

**Parameters:**
- `$event` - Event name
- `$data` - Event data

---

##### `emit(string $event, array $data = []): void`

Alias for `dispatch()`.

```php
$this->emit('user:created', ['id' => $userId]);
```

---

##### `listen(string $event, string|callable $handler): void`

Register an event listener.

```php
$this->listen('cart:updated', 'onCartUpdated');
$this->listen('order:placed', function($data) {
    // Handle event
});
```

**Parameters:**
- `$event` - Event name
- `$handler` - Method name or callable

---

##### `on(string $event, string|callable $handler): void`

Alias for `listen()`.

```php
$this->on('user:login', 'handleLogin');
```

---

##### `getListeners(): array`

Get registered listeners.

```php
$listeners = $component->getListeners();
// ['cart:updated' => 'onCartUpdated', ...]
```

**Returns:** `array` - Event listeners

---

##### `getDispatchQueue(): array`

Get queued events.

```php
$events = $component->getDispatchQueue();
// [['event' => 'item:added', 'data' => [...]], ...]
```

**Returns:** `array` - Queued events

---

#### UI Methods

##### `notify(string $message, string $type = 'success'): void`

Queue a notification.

```php
$this->notify('Saved successfully!', 'success');
$this->notify('Error occurred', 'error');
$this->notify('Please check input', 'warning');
$this->notify('New feature available', 'info');
```

**Parameters:**
- `$message` - Notification message
- `$type` - Type: `success`, `error`, `warning`, `info`

---

##### `redirect(string $url): void`

Set redirect URL.

```php
$this->redirect('/dashboard');
```

**Parameters:**
- `$url` - Redirect URL

---

##### `getRedirect(): ?string`

Get the redirect URL.

```php
$url = $component->getRedirect(); // '/dashboard' or null
```

**Returns:** `?string` - Redirect URL or null

---

##### `getNotifications(): array`

Get queued notifications.

```php
$notifications = $component->getNotifications();
// [['message' => 'Saved!', 'type' => 'success'], ...]
```

**Returns:** `array` - Notifications

---

#### Validation Methods

##### `validate(array $rules): void`

Validate properties against rules.

```php
$this->validate([
    'name' => 'required|min:2|max:100',
    'email' => 'required|email',
    'age' => 'required|integer|min:18',
]);
```

**Parameters:**
- `$rules` - Validation rules array

---

##### `hasErrors(): bool`

Check if there are validation errors.

```php
if ($this->hasErrors()) {
    return; // Don't proceed
}
```

**Returns:** `bool` - True if errors exist

---

##### `getErrors(): array`

Get all validation errors.

```php
$errors = $this->getErrors();
// ['email' => 'Invalid email', 'name' => 'Required']
```

**Returns:** `array` - Validation errors

---

##### `getError(string $field): ?string`

Get error for a specific field.

```php
$emailError = $this->getError('email'); // 'Invalid email' or null
```

**Parameters:**
- `$field` - Field name

**Returns:** `?string` - Error message or null

---

## ReactiveManager

Manages component lifecycle and interactions.

**Namespace:** `Lalaz\Reactive\ReactiveManager`

### Constructor

```php
public function __construct(
    ContainerInterface $container,
    ComponentRegistry $registry,
    string $namespace = ''
)
```

**Parameters:**
- `$container` - DI container instance
- `$registry` - Component registry
- `$namespace` - Default component namespace

### Methods

##### `mount(string $class, array $params = []): array`

Mount and initialize a component.

```php
$snapshot = $manager->mount(Counter::class, ['initialCount' => 10]);
// Returns: [
//     'id' => 'counter-abc123',
//     'name' => 'App\Components\Counter',
//     'state' => [...],
//     'html' => '<div>...',
//     'checksum' => '...',
// ]
```

**Parameters:**
- `$class` - Component class name
- `$params` - Mount parameters

**Returns:** `array` - Component snapshot

---

##### `restore(array $snapshot): ReactiveComponent`

Restore a component from a snapshot.

```php
$component = $manager->restore($snapshot);
```

**Parameters:**
- `$snapshot` - Component snapshot

**Returns:** `ReactiveComponent` - Restored component

---

##### `render(ReactiveComponent $component): string`

Render a component with wrapper attributes.

```php
$html = $manager->render($component);
// Returns HTML with reactive:* attributes
```

**Parameters:**
- `$component` - Component instance

**Returns:** `string` - Wrapped HTML output

---

##### `call(ReactiveComponent|string $component, string $method, array $params = []): mixed`

Call a method on a component.

```php
// By component instance
$result = $manager->call($component, 'increment');

// By component ID
$result = $manager->call('counter-123', 'add', [5]);
```

**Parameters:**
- `$component` - Component instance or ID
- `$method` - Method name
- `$params` - Method parameters

**Returns:** `mixed` - Method return value

---

##### `updateProperty(string $id, string $property, mixed $value): ReactiveComponent`

Update a component property.

```php
$component = $manager->updateProperty('form-123', 'email', 'new@email.com');
```

**Parameters:**
- `$id` - Component ID
- `$property` - Property name
- `$value` - New value

**Returns:** `ReactiveComponent` - Updated component

---

##### `setNamespace(string $namespace): void`

Set the default component namespace.

```php
$manager->setNamespace('App\\Components\\');
```

**Parameters:**
- `$namespace` - Namespace string

---

## ComponentRegistry

Stores and retrieves component instances.

**Namespace:** `Lalaz\Reactive\ComponentRegistry`

### Methods

##### `register(string $id, ReactiveComponent $component): void`

Register a component.

```php
$registry->register('counter-123', $component);
```

**Parameters:**
- `$id` - Component ID
- `$component` - Component instance

---

##### `get(string $id): ?ReactiveComponent`

Get a component by ID.

```php
$component = $registry->get('counter-123');
```

**Parameters:**
- `$id` - Component ID

**Returns:** `?ReactiveComponent` - Component or null

---

##### `has(string $id): bool`

Check if a component exists.

```php
if ($registry->has('counter-123')) {
    // Component exists
}
```

**Parameters:**
- `$id` - Component ID

**Returns:** `bool` - True if exists

---

##### `remove(string $id): void`

Remove a component.

```php
$registry->remove('counter-123');
```

**Parameters:**
- `$id` - Component ID

---

##### `all(): array`

Get all registered components.

```php
$components = $registry->all();
// ['counter-123' => Component, 'form-456' => Component]
```

**Returns:** `array` - All components

---

##### `clear(): void`

Remove all components.

```php
$registry->clear();
```

---

## ReactiveController

HTTP controller for AJAX requests.

**Namespace:** `Lalaz\Reactive\Http\ReactiveController`

### Constructor

```php
public function __construct(ReactiveManager $manager)
```

### Methods

##### `call(Request $request): Response`

Handle method call requests.

**Expected Request Body:**
```json
{
    "id": "component-id",
    "name": "ComponentClass",
    "method": "methodName",
    "params": ["param1", "param2"],
    "state": {...},
    "checksum": "..."
}
```

**Response:**
```json
{
    "html": "<div>...</div>",
    "state": {...},
    "dispatches": [...],
    "notifications": [...],
    "redirect": "/url"
}
```

---

##### `update(Request $request): Response`

Handle property update requests.

**Expected Request Body:**
```json
{
    "id": "component-id",
    "name": "ComponentClass",
    "property": "propertyName",
    "value": "newValue",
    "state": {...},
    "checksum": "..."
}
```

---

## Exceptions

### ReactiveException

Base exception for all reactive errors.

**Namespace:** `Lalaz\Reactive\Exceptions\ReactiveException`

---

### ComponentNotFoundException

Thrown when a component is not found.

```php
throw new ComponentNotFoundException('Counter');
// Message: "Component not found: Counter"
```

---

### InvalidRequestException

Thrown for invalid requests.

```php
throw new InvalidRequestException('Missing component ID');
```

---

### MethodNotAccessibleException

Thrown when trying to call a non-public method.

```php
throw new MethodNotAccessibleException('privateMethod');
// Message: "Method is not accessible: privateMethod"
```

---

### PropertyNotAccessibleException

Thrown when trying to access a non-public property.

```php
throw new PropertyNotAccessibleException('privateProperty');
// Message: "Property is not accessible: privateProperty"
```

---

## Traits

### HandlesEvents

Provides event handling capabilities.

**Methods:**
- `listen(string $event, string|callable $handler): void`
- `on(string $event, string|callable $handler): void`
- `dispatch(string $event, array $data = []): void`
- `emit(string $event, array $data = []): void`
- `getListeners(): array`
- `listensTo(string $event): bool`

---

### ValidatesInput

Provides validation capabilities.

**Methods:**
- `validate(array $rules): void`
- `hasErrors(): bool`
- `getErrors(): array`
- `getError(string $field): ?string`
