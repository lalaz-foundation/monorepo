# Core Concepts

This guide explains the fundamental concepts behind the Lalaz Reactive package.

## Table of Contents

- [Reactive Components](#reactive-components)
- [Component Lifecycle](#component-lifecycle)
- [State Management](#state-management)
- [Events System](#events-system)
- [Validation](#validation)
- [Notifications](#notifications)
- [Redirects](#redirects)

## Reactive Components

A reactive component is a PHP class that manages state and renders HTML. Components extend the `ReactiveComponent` base class.

### Anatomy of a Component

```php
<?php

namespace App\Components;

use Lalaz\Reactive\ReactiveComponent;

class UserProfile extends ReactiveComponent
{
    // 1. PUBLIC PROPERTIES - Automatically tracked state
    public string $name = '';
    public string $email = '';
    public bool $isEditing = false;
    
    // 2. MOUNT METHOD - Called during initialization
    public function mount(int $userId): void
    {
        $user = User::find($userId);
        $this->name = $user->name;
        $this->email = $user->email;
        
        // Set up event listeners
        $this->listen('user:updated', 'onUserUpdated');
    }
    
    // 3. PUBLIC METHODS - Callable from client
    public function startEditing(): void
    {
        $this->isEditing = true;
    }
    
    public function save(): void
    {
        $this->validate([
            'name' => 'required|min:2',
            'email' => 'required|email',
        ]);
        
        if (!$this->hasErrors()) {
            User::update([
                'name' => $this->name,
                'email' => $this->email,
            ]);
            $this->isEditing = false;
            $this->dispatch('user:saved', ['name' => $this->name]);
        }
    }
    
    // 4. UPDATED HOOK - Called when property changes
    public function updated(string $property): void
    {
        if ($property === 'email') {
            // Clear validation errors when email changes
            unset($this->errors['email']);
        }
    }
    
    // 5. EVENT HANDLERS
    public function onUserUpdated(array $data): void
    {
        if ($data['id'] === $this->userId) {
            $this->name = $data['name'];
        }
    }
    
    // 6. RENDER METHOD - Required, generates HTML
    public function render(): string
    {
        if ($this->isEditing) {
            return $this->renderEditForm();
        }
        return $this->renderProfile();
    }
    
    private function renderProfile(): string
    {
        return "<div><h1>{$this->name}</h1><p>{$this->email}</p></div>";
    }
    
    private function renderEditForm(): string
    {
        return "<form>...</form>";
    }
}
```

### Property Types

Only **public properties** are tracked and synchronized:

```php
class Example extends ReactiveComponent
{
    // ✅ Tracked - public properties
    public string $title = '';
    public int $count = 0;
    public array $items = [];
    public bool $active = false;
    
    // ❌ Not tracked - private/protected properties
    private string $secret = '';
    protected int $internal = 0;
}
```

## Component Lifecycle

Understanding the lifecycle helps you know when to perform different actions.

### Lifecycle Phases

```
┌─────────────────────────────────────────────────────────────┐
│                     INITIAL REQUEST                          │
├─────────────────────────────────────────────────────────────┤
│  1. Constructor    → Component instance created              │
│  2. mount()        → Initialize with parameters              │
│  3. dehydrate()    → Serialize state                        │
│  4. render()       → Generate HTML                          │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                   SUBSEQUENT REQUESTS                        │
├─────────────────────────────────────────────────────────────┤
│  1. Constructor    → Component instance created              │
│  2. hydrate()      → Restore state from client              │
│  3. Action         → Method call OR property update          │
│  4. updated()      → Hook for property changes              │
│  5. dehydrate()    → Serialize new state                    │
│  6. render()       → Generate updated HTML                  │
└─────────────────────────────────────────────────────────────┘
```

### Lifecycle Hooks

#### mount()

Called when the component is first created. Use it to:
- Set initial property values
- Register event listeners
- Load data from the database

```php
public function mount(int $productId): void
{
    $product = Product::find($productId);
    $this->name = $product->name;
    $this->price = $product->price;
    
    $this->listen('cart:updated', 'refreshStock');
}
```

#### updated()

Called after any property is modified. Use it to:
- React to specific property changes
- Clear related validation errors
- Trigger side effects

```php
public function updated(string $property): void
{
    match($property) {
        'quantity' => $this->calculateTotal(),
        'coupon' => $this->applyCoupon(),
        default => null,
    };
}
```

## State Management

### State Serialization

Component state is automatically serialized (dehydrated) and deserialized (hydrated):

```php
// When sending to client
$state = $component->dehydrate();
// Returns: [
//     'id' => 'product-abc123',
//     'name' => 'App\Components\Product',
//     'mount' => ['productId' => 42],
//     'properties' => [
//         'name' => 'Widget',
//         'price' => 29.99,
//         'quantity' => 1,
//     ],
//     'dispatches' => [...],
//     'notifications' => [...],
//     'redirect' => null,
// ]

// When restoring from client
$component->hydrate($state);
```

### Property Updates

Properties can be updated through the manager:

```php
// Direct property update
$manager->updateProperty($componentId, 'quantity', 5);

// Or through method calls
$manager->call($component, 'setQuantity', [5]);
```

### Reset Properties

Reset properties to their default values:

```php
class Form extends ReactiveComponent
{
    public string $name = '';
    public string $email = '';
    
    public function clearName(): void
    {
        $this->reset('name'); // Reset only name
    }
    
    public function clearAll(): void
    {
        $this->reset('name', 'email'); // Reset specific properties
    }
}
```

## Events System

Components communicate through a publish-subscribe event system.

### Dispatching Events

```php
class ShoppingCart extends ReactiveComponent
{
    public function addItem(int $productId): void
    {
        // Add item logic...
        
        // Dispatch event for other components
        $this->dispatch('cart:itemAdded', [
            'productId' => $productId,
            'cartTotal' => $this->total,
        ]);
    }
}
```

### Listening to Events

```php
class CartCounter extends ReactiveComponent
{
    public int $count = 0;
    
    public function mount(): void
    {
        // Register listener
        $this->listen('cart:itemAdded', 'onItemAdded');
        $this->listen('cart:itemRemoved', 'onItemRemoved');
    }
    
    public function onItemAdded(array $data): void
    {
        $this->count++;
    }
    
    public function onItemRemoved(array $data): void
    {
        $this->count--;
    }
}
```

### Event Flow

```
┌──────────────┐     dispatch()     ┌──────────────────┐
│   Component  │ ─────────────────► │   Event Queue    │
│      A       │                    │  (dehydrated)    │
└──────────────┘                    └────────┬─────────┘
                                             │
                                             │ Client JS processes
                                             │ and relays to listeners
                                             ▼
┌──────────────┐     hydrate()      ┌──────────────────┐
│   Component  │ ◄───────────────── │   Event Data     │
│      B       │                    │  (from queue)    │
└──────────────┘                    └──────────────────┘
```

### Aliases

- `dispatch()` and `emit()` are equivalent
- `listen()` and `on()` are equivalent

```php
// These are the same
$this->dispatch('event', ['data' => 'value']);
$this->emit('event', ['data' => 'value']);

// These are the same
$this->listen('event', 'handler');
$this->on('event', 'handler');
```

## Validation

Built-in validation using the `ValidatesInput` trait.

### Basic Validation

```php
class RegistrationForm extends ReactiveComponent
{
    public string $username = '';
    public string $email = '';
    public string $password = '';
    
    public function register(): void
    {
        $this->validate([
            'username' => 'required|min:3|max:20',
            'email' => 'required|email',
            'password' => 'required|min:8',
        ]);
        
        if ($this->hasErrors()) {
            return; // Validation failed
        }
        
        // Create user...
    }
}
```

### Error Handling

```php
// Check if there are any errors
if ($this->hasErrors()) {
    // Handle errors
}

// Get all errors
$errors = $this->getErrors();
// ['email' => 'Invalid email format', 'password' => 'Too short']

// Get specific field error
$emailError = $this->getError('email');
// 'Invalid email format' or null
```

### Displaying Errors

```php
public function render(): string
{
    $emailError = $this->getError('email') 
        ? "<span class='error'>{$this->getError('email')}</span>" 
        : '';
    
    return <<<HTML
    <form>
        <input name="email" value="{$this->email}" />
        {$emailError}
    </form>
    HTML;
}
```

## Notifications

Display feedback messages to users.

### Types of Notifications

```php
// Success (default)
$this->notify('Changes saved successfully!');
$this->notify('Changes saved!', 'success');

// Error
$this->notify('Something went wrong.', 'error');

// Warning
$this->notify('Please review your input.', 'warning');

// Info
$this->notify('New features are available!', 'info');
```

### Notification Structure

Notifications are queued and included in the dehydrated state:

```php
$state = $component->dehydrate();
// $state['notifications'] = [
//     ['message' => 'Saved!', 'type' => 'success'],
//     ['message' => 'Warning', 'type' => 'warning'],
// ]
```

## Redirects

Navigate users to different pages.

### Basic Redirect

```php
public function save(): void
{
    // Save data...
    
    // Redirect to another page
    $this->redirect('/dashboard');
}

public function cancel(): void
{
    // Go back
    $this->redirect('/products');
}
```

### Redirect with State

The redirect URL is included in the dehydrated state for the client to handle:

```php
$state = $component->dehydrate();
// $state['redirect'] = '/dashboard'
```

### Conditional Redirects

```php
public function submit(): void
{
    $this->validate([...]);
    
    if ($this->hasErrors()) {
        return; // Stay on current page
    }
    
    // Success - redirect
    $this->redirect('/success');
}
```
