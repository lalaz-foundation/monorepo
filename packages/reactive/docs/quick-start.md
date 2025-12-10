# Quick Start Guide

Get started with the Lalaz Reactive package in just a few minutes.

## Prerequisites

- PHP 8.2 or higher
- Composer
- Lalaz Framework (optional, can be used standalone)

## Installation

```bash
composer require lalaz/reactive
```

## Creating Your First Component

### Step 1: Create a Component Class

Create a new file `app/Components/Counter.php`:

```php
<?php

namespace App\Components;

use Lalaz\Reactive\ReactiveComponent;

class Counter extends ReactiveComponent
{
    // Public properties are automatically tracked
    public int $count = 0;
    
    // Mount is called when the component is first created
    public function mount(int $initialCount = 0): void
    {
        $this->count = $initialCount;
    }
    
    // Public methods can be called from the client
    public function increment(): void
    {
        $this->count++;
    }
    
    public function decrement(): void
    {
        $this->count--;
    }
    
    // Required: render the component HTML
    public function render(): string
    {
        return <<<HTML
        <div class="counter">
            <button onclick="reactive.call('decrement')">-</button>
            <span class="count">{$this->count}</span>
            <button onclick="reactive.call('increment')">+</button>
        </div>
        HTML;
    }
}
```

### Step 2: Initialize the Reactive System

```php
<?php

use Lalaz\Reactive\ReactiveManager;
use Lalaz\Reactive\ComponentRegistry;
use Lalaz\Container\Container;

// Create dependencies
$container = new Container();
$registry = new ComponentRegistry();

// Create the manager
$manager = new ReactiveManager($container, $registry);
```

### Step 3: Mount and Render a Component

```php
<?php

use App\Components\Counter;

// Mount the component with initial parameters
$snapshot = $manager->mount(Counter::class, ['initialCount' => 5]);

// The snapshot contains everything needed to render and restore the component
// $snapshot = [
//     'id' => 'counter-abc123',
//     'name' => 'App\Components\Counter',
//     'state' => [...],
//     'html' => '<div class="counter">...',
//     'checksum' => '...',
// ]

echo $snapshot['html'];
```

## Handling User Interactions

### Step 4: Process Method Calls

When a user clicks a button, the client sends an AJAX request. Handle it like this:

```php
<?php

// Restore the component from the snapshot
$component = $manager->restore($snapshot);

// Call the method
$manager->call($component, 'increment');

// Get the updated HTML
$html = $manager->render($component);

// Send back the response
return json_encode([
    'html' => $html,
    'state' => $component->dehydrate(),
]);
```

### Step 5: Using the HTTP Controller

For a complete solution, use the built-in controller:

```php
<?php

use Lalaz\Reactive\Http\ReactiveController;

$controller = new ReactiveController($manager);

// In your routes
$router->post('/reactive/call', [$controller, 'call']);
$router->post('/reactive/update', [$controller, 'update']);
```

## Adding Events

Components can communicate through events:

```php
<?php

class NotificationCounter extends ReactiveComponent
{
    public int $count = 0;
    
    public function increment(): void
    {
        $this->count++;
        
        // Dispatch an event that other components can listen to
        $this->dispatch('count:changed', [
            'count' => $this->count,
        ]);
        
        // Show a notification to the user
        if ($this->count % 10 === 0) {
            $this->notify("You've reached {$this->count}!", 'success');
        }
    }
    
    public function render(): string
    {
        return "<div>{$this->count}</div>";
    }
}
```

## Adding Validation

Validate user input before processing:

```php
<?php

class ContactForm extends ReactiveComponent
{
    public string $name = '';
    public string $email = '';
    public string $message = '';
    
    public function submit(): void
    {
        // Validate the properties
        $this->validate([
            'name' => 'required|min:2',
            'email' => 'required|email',
            'message' => 'required|min:10',
        ]);
        
        // Check for errors
        if ($this->hasErrors()) {
            return;
        }
        
        // Process the form...
        $this->sendEmail();
        
        // Show success message and redirect
        $this->notify('Message sent!', 'success');
        $this->redirect('/thank-you');
    }
    
    public function render(): string
    {
        return <<<HTML
        <form onsubmit="reactive.call('submit'); return false;">
            <div>
                <input type="text" 
                       value="{$this->name}" 
                       onchange="reactive.set('name', this.value)" />
                <span class="error">{$this->getError('name')}</span>
            </div>
            <div>
                <input type="email" 
                       value="{$this->email}" 
                       onchange="reactive.set('email', this.value)" />
                <span class="error">{$this->getError('email')}</span>
            </div>
            <div>
                <textarea onchange="reactive.set('message', this.value)">{$this->message}</textarea>
                <span class="error">{$this->getError('message')}</span>
            </div>
            <button type="submit">Send</button>
        </form>
        HTML;
    }
}
```

## Next Steps

- Learn about [Core Concepts](concepts.md) for a deeper understanding
- Explore the full [API Reference](api-reference.md)
- Set up [Testing](testing.md) for your components
- Check the [Glossary](glossary.md) for terminology
