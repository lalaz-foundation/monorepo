# Glossary

Key terms and definitions used in the Lalaz Reactive package.

---

## A

### Action
A method call or property update triggered by the user. Actions modify the component state and trigger re-rendering.

---

## C

### Checksum
A hash value computed from the component state, used to verify that the state hasn't been tampered with between requests.

### Component
A self-contained unit of UI that manages its own state and rendering. In Lalaz Reactive, components extend `ReactiveComponent`.

### Component ID
A unique identifier assigned to each component instance. Used to track and restore components across requests. Example: `counter-abc123`

### Component Registry
A container that stores component instances by their IDs. Allows retrieval of components during the request lifecycle.

---

## D

### Dehydrate
The process of serializing a component's state into a format that can be sent to the client. Opposite of hydrate.

```php
$state = $component->dehydrate();
// Returns: ['id' => '...', 'name' => '...', 'properties' => [...]]
```

### Dispatch
To send an event from one component that other components can listen to.

```php
$this->dispatch('user:created', ['id' => $userId]);
```

### Dispatch Queue
A collection of events that have been dispatched during a request cycle, waiting to be sent to the client.

---

## E

### Emit
An alias for dispatch. Both methods queue an event for other components.

### Event
A named message that components can dispatch and listen to, enabling communication between components.

### Event Listener
A method registered to handle a specific event when it occurs.

---

## H

### Handler
A method or callable that responds to an event.

### Hydrate
The process of restoring a component's state from serialized data received from the client. Opposite of dehydrate.

```php
$component->hydrate($state);
```

### Hook
A method that is automatically called at specific points in the component lifecycle (e.g., `mount()`, `updated()`).

---

## L

### Lifecycle
The sequence of phases a component goes through: mount, hydrate, action, updated, dehydrate, render.

### Listen
To register a handler for a specific event.

```php
$this->listen('cart:updated', 'onCartUpdated');
```

---

## M

### Manager
The `ReactiveManager` class that orchestrates component mounting, restoration, method calls, and rendering.

### Mount
The initial setup phase of a component where it receives parameters and initializes its state.

```php
public function mount(int $productId): void
{
    $this->product = Product::find($productId);
}
```

### Mount Parameters
The arguments passed to a component during mounting. These are preserved and can be used during re-mounting.

---

## N

### Notification
A message displayed to the user, typically as a toast or flash message. Types include: success, error, warning, info.

```php
$this->notify('Saved successfully!', 'success');
```

---

## P

### Property
A public class variable on a component that is automatically tracked and synchronized. Only public properties are reactive.

### Property Update
A change to a component's property value, typically triggered by user input.

---

## R

### Reactive
The ability of a component to automatically synchronize state between server and client, updating the UI in response to state changes.

### Reactive Attribute
HTML attributes added to component output for client-side tracking:
- `reactive:id` - Component ID
- `reactive:name` - Component class name
- `reactive:state` - Serialized state
- `reactive:params` - Mount parameters
- `reactive:listeners` - Event listeners

### Redirect
A navigation action that directs the user to a different URL after a component action.

```php
$this->redirect('/dashboard');
```

### Registry
See [Component Registry](#component-registry).

### Render
The process of generating HTML output from a component's current state.

```php
public function render(): string
{
    return "<div>{$this->title}</div>";
}
```

### Reset
Restoring component properties to their default values.

```php
$this->reset('name', 'email'); // Reset specific properties
$this->reset();                 // Reset all properties
```

### Restore
The process of recreating a component instance from a serialized snapshot.

---

## S

### Serialization
Converting component state to a string or array format for storage or transmission.

### Snapshot
A complete capture of a component's state, including ID, name, properties, and mount parameters. Used for restoration.

```php
$snapshot = $manager->mount(Counter::class, ['initial' => 10]);
```

### State
The current values of all tracked (public) properties in a component.

### State Management
The system for tracking, serializing, and restoring component data across requests.

---

## T

### Trait
A PHP mechanism for code reuse. The package provides traits like `HandlesEvents` and `ValidatesInput`.

### Type Casting
Automatic conversion of property values to their declared types when updating.

---

## U

### Updated Hook
A method called after a property value changes, allowing reaction to specific changes.

```php
public function updated(string $property): void
{
    if ($property === 'quantity') {
        $this->recalculate();
    }
}
```

---

## V

### Validation
The process of checking property values against defined rules.

```php
$this->validate([
    'email' => 'required|email',
    'password' => 'required|min:8',
]);
```

### Validation Error
A message indicating that a property value doesn't meet the validation rules.

---

## W

### Wire
Alternative term sometimes used for the reactive binding system (inspired by similar frameworks).

### Wrapper
The HTML element that surrounds component output and contains reactive attributes.

```html
<div reactive:id="counter-123" reactive:name="Counter" reactive:state="...">
    <!-- Component HTML -->
</div>
```
