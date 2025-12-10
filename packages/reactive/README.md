# Lalaz Reactive Package

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.3-8892BF.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![Tests](https://img.shields.io/badge/tests-117%20passing-brightgreen.svg)](#testing)
[![Coverage](https://img.shields.io/badge/coverage-high-brightgreen.svg)](#testing)

A powerful reactive component system for building dynamic, real-time UI with PHP. The Reactive package enables server-side state management with automatic client synchronization, event-driven architectures, and seamless AJAX interactions.

## Table of Contents

- [Features](#features)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Core Concepts](#core-concepts)
  - [Reactive Components](#reactive-components)
  - [Component Lifecycle](#component-lifecycle)
  - [State Management](#state-management)
  - [Events System](#events-system)
- [API Reference](#api-reference)
  - [ReactiveComponent](#reactivecomponent)
  - [ReactiveManager](#reactivemanager)
  - [ComponentRegistry](#componentregistry)
  - [ReactiveController](#reactivecontroller)
- [Advanced Usage](#advanced-usage)
  - [Validation](#validation)
  - [Notifications](#notifications)
  - [Redirects](#redirects)
- [Testing](#testing)
- [Documentation](#documentation)
- [Contributing](#contributing)
- [License](#license)

## Features

- **Reactive State Management** - Automatic synchronization between server and client
- **Event-Driven Architecture** - Dispatch and listen to events across components
- **Component Lifecycle** - Mount, update, and render hooks for full control
- **Built-in Validation** - Input validation with error handling
- **Notifications System** - Flash messages and notifications
- **Redirect Support** - Programmatic navigation from components
- **AJAX Integration** - HTTP controller for handling component updates
- **State Hydration** - Serialize and restore component state seamlessly

## Installation

Install via Composer:

```bash
composer require lalaz/reactive
```

## Quick Start

### Creating a Simple Counter Component

```php
<?php

namespace App\Components;

use Lalaz\Reactive\ReactiveComponent;

class Counter extends ReactiveComponent
{
    public int $count = 0;
    public int $step = 1;

    public function mount(int $initialCount = 0, int $step = 1): void
    {
        $this->count = $initialCount;
        $this->step = $step;
    }

    public function increment(): void
    {
        $this->count += $this->step;
        $this->dispatch('count:changed', ['count' => $this->count]);
    }

    public function decrement(): void
    {
        $this->count -= $this->step;
        $this->dispatch('count:changed', ['count' => $this->count]);
    }

    public function reset(): void
    {
        $this->count = 0;
    }

    public function render(): string
    {
        return <<<HTML
        <div class="counter">
            <button onclick="reactive.call('decrement')">-</button>
            <span>{$this->count}</span>
            <button onclick="reactive.call('increment')">+</button>
        </div>
        HTML;
    }
}
```

### Using the Component

```php
<?php

use Lalaz\Reactive\ReactiveManager;
use Lalaz\Reactive\ComponentRegistry;
use App\Components\Counter;

// Initialize
$container = app(); // Your DI container
$registry = new ComponentRegistry();
$manager = new ReactiveManager($container, $registry);

// Mount the component
$snapshot = $manager->mount(Counter::class, ['initialCount' => 10, 'step' => 5]);

// Restore from state
$component = $manager->restore($snapshot);

// Call methods
$manager->call($component, 'increment');

// Update properties
$manager->updateProperty($component, 'step', 10);

// Render
$html = $manager->render($component);
```

## Core Concepts

### Reactive Components

All reactive components extend the `ReactiveComponent` base class:

```php
use Lalaz\Reactive\ReactiveComponent;

class MyComponent extends ReactiveComponent
{
    // Public properties are automatically tracked
    public string $message = '';
    public array $items = [];
    
    // Mount is called when the component is first created
    public function mount(string $initialMessage = ''): void
    {
        $this->message = $initialMessage;
    }
    
    // Called when a property is updated
    public function updated(string $property): void
    {
        // React to property changes
    }
    
    // Required: render the component HTML
    public function render(): string
    {
        return "<div>{$this->message}</div>";
    }
}
```

### Component Lifecycle

1. **Mount** - Component is instantiated with initial parameters
2. **Hydrate** - State is restored from client data
3. **Action** - Method is called or property is updated
4. **Updated** - Hook is fired for property changes
5. **Dehydrate** - State is serialized for client
6. **Render** - HTML output is generated

### State Management

Component state is automatically serialized and restored:

```php
// Dehydrate (serialize) state
$state = $component->dehydrate();
// Returns: ['id' => '...', 'name' => '...', 'properties' => [...], ...]

// Hydrate (restore) state
$component->hydrate($state);
```

### Events System

Components can dispatch and listen to events:

```php
class Publisher extends ReactiveComponent
{
    public function publishMessage(string $message): void
    {
        // Dispatch event to other components
        $this->dispatch('message:published', ['message' => $message]);
    }
}

class Subscriber extends ReactiveComponent
{
    public array $messages = [];
    
    public function mount(): void
    {
        // Listen for events
        $this->listen('message:published', 'onMessagePublished');
    }
    
    public function onMessagePublished(array $data): void
    {
        $this->messages[] = $data['message'];
    }
}
```

## API Reference

### ReactiveComponent

Base class for all reactive components.

#### Properties

| Property | Type | Description |
|----------|------|-------------|
| `$id` | `string` | Unique component identifier |
| `$name` | `string` | Component name |
| `$listeners` | `array` | Registered event listeners |
| `$errors` | `array` | Validation errors |

#### Methods

| Method | Description |
|--------|-------------|
| `mount(...$params): void` | Initialize component with parameters |
| `render(): string` | Render component HTML |
| `updated(string $property): void` | Called when a property is updated |
| `hydrate(array $state): void` | Restore component from state |
| `dehydrate(): array` | Serialize component state |
| `setProperty(string $name, mixed $value): void` | Set a property value |
| `getProperty(string $name): mixed` | Get a property value |
| `dispatch(string $event, array $data = []): void` | Dispatch an event |
| `emit(string $event, array $data = []): void` | Alias for dispatch |
| `listen(string $event, string $handler): void` | Listen for an event |
| `on(string $event, string $handler): void` | Alias for listen |
| `notify(string $message, string $type = 'success'): void` | Show notification |
| `redirect(string $url): void` | Set redirect URL |
| `validate(array $rules): void` | Validate properties |
| `hasErrors(): bool` | Check for validation errors |
| `getErrors(): array` | Get all validation errors |
| `getError(string $field): ?string` | Get error for specific field |
| `reset(string ...$properties): void` | Reset properties to defaults |

### ReactiveManager

Manages component lifecycle and interactions.

#### Methods

| Method | Description |
|--------|-------------|
| `mount(string $class, array $params = []): array` | Mount and initialize a component |
| `restore(array $snapshot): ReactiveComponent` | Restore component from snapshot |
| `render(ReactiveComponent $component): string` | Render component with wrapper |
| `call(ReactiveComponent $component, string $method, array $params = []): mixed` | Call a component method |
| `updateProperty(string $id, string $property, mixed $value): ReactiveComponent` | Update a property |

### ComponentRegistry

Stores and retrieves component instances.

#### Methods

| Method | Description |
|--------|-------------|
| `register(string $id, ReactiveComponent $component): void` | Register a component |
| `get(string $id): ?ReactiveComponent` | Get a component by ID |
| `has(string $id): bool` | Check if component exists |
| `remove(string $id): void` | Remove a component |
| `all(): array` | Get all components |
| `clear(): void` | Remove all components |

### ReactiveController

HTTP controller for AJAX requests.

#### Methods

| Method | Description |
|--------|-------------|
| `call(Request $request): Response` | Handle method call request |
| `update(Request $request): Response` | Handle property update request |

## Advanced Usage

### Validation

Built-in validation support using the Validator trait:

```php
class ContactForm extends ReactiveComponent
{
    public string $name = '';
    public string $email = '';
    public string $message = '';
    
    public function submit(): void
    {
        $this->validate([
            'name' => 'required|min:2|max:100',
            'email' => 'required|email',
            'message' => 'required|min:10',
        ]);
        
        if ($this->hasErrors()) {
            return;
        }
        
        // Process form...
        $this->notify('Message sent successfully!', 'success');
        $this->redirect('/thank-you');
    }
    
    public function render(): string
    {
        $nameError = $this->getError('name');
        return <<<HTML
        <form>
            <input name="name" value="{$this->name}" />
            {$nameError}
            <!-- ... -->
        </form>
        HTML;
    }
}
```

### Notifications

Display flash messages to users:

```php
// Success notification
$this->notify('Changes saved!', 'success');

// Error notification
$this->notify('Something went wrong.', 'error');

// Warning notification
$this->notify('Please review your input.', 'warning');

// Info notification
$this->notify('New features available!', 'info');
```

### Redirects

Programmatic navigation:

```php
public function save(): void
{
    // Save data...
    
    // Redirect after action
    $this->redirect('/dashboard');
}
```

## Testing

Run the test suite:

```bash
# All tests
./vendor/bin/phpunit

# Unit tests only
./vendor/bin/phpunit --testsuite Unit

# Integration tests only
./vendor/bin/phpunit --testsuite Integration

# With coverage report
./vendor/bin/phpunit --coverage-html coverage
```

### Test Results

- **117 tests passing** (84 Unit + 33 Integration)
- **275 assertions**
- **High code coverage**

### Test Structure

```
tests/
├── bootstrap.php              # Test bootstrap
├── TestCase.php               # Base test case
├── Common/
│   ├── ReactiveUnitTestCase.php
│   └── ReactiveIntegrationTestCase.php
├── Fixtures/
│   └── Mocks/
│       └── MockContainer.php
├── Unit/
│   ├── ReactiveComponentTest.php
│   ├── ReactiveManagerTest.php
│   ├── ComponentRegistryTest.php
│   ├── Concerns/
│   │   ├── HandlesEventsTest.php
│   │   └── ValidatesInputTest.php
│   ├── Exceptions/
│   │   └── ExceptionsTest.php
│   └── Http/
│       └── ReactiveControllerTest.php
└── Integration/
    ├── ReactiveIntegrationTest.php
    ├── ControllerIntegrationTest.php
    └── AdvancedComponentTest.php
```

## Documentation

For detailed documentation, see the [docs/](docs/) folder:

- [Getting Started](docs/quick-start.md)
- [Core Concepts](docs/concepts.md)
- [API Reference](docs/api-reference.md)
- [Testing Guide](docs/testing.md)
- [Glossary](docs/glossary.md)

## Contributing

Contributions are welcome! Please read our [Contributing Guide](../../CONTRIBUTING.md) before submitting a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Write tests for your changes
4. Ensure all tests pass (`./vendor/bin/phpunit`)
5. Commit your changes (`git commit -m 'Add amazing feature'`)
6. Push to the branch (`git push origin feature/amazing-feature`)
7. Open a Pull Request

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
