# Installation Guide

Complete guide for installing and configuring the Lalaz Reactive package.

## Requirements

- **PHP 8.2** or higher
- **Composer** 2.0 or higher
- **ext-json** - JSON extension (usually enabled by default)

## Installation

### Using Composer

```bash
composer require lalaz/reactive
```

### Manual Installation

1. Add the package to your `composer.json`:

```json
{
    "require": {
        "lalaz/reactive": "^1.0"
    }
}
```

2. Run composer update:

```bash
composer update lalaz/reactive
```

## Configuration

### Basic Setup

Create a configuration file or use environment variables:

```php
<?php
// config/reactive.php

return [
    // Component namespace for auto-resolution
    'namespace' => 'App\\Components\\',
    
    // Secret key for checksum validation
    'checksum_secret' => env('REACTIVE_SECRET', 'your-secret-key'),
    
    // Enable/disable debug mode
    'debug' => env('APP_DEBUG', false),
];
```

### Service Provider (Laravel)

If using Laravel, register the service provider:

```php
<?php
// config/app.php

'providers' => [
    // ...
    Lalaz\Reactive\ReactiveServiceProvider::class,
],
```

### Manual Initialization

For standalone usage:

```php
<?php

use Lalaz\Reactive\ReactiveManager;
use Lalaz\Reactive\ComponentRegistry;
use Lalaz\Container\Container;

// Create a DI container (or use your own)
$container = new Container();

// Create the component registry
$registry = new ComponentRegistry();

// Create the manager with optional namespace
$manager = new ReactiveManager($container, $registry);
$manager->setNamespace('App\\Components\\');
```

## Route Configuration

### Using Lalaz Framework

```php
<?php
// routes/web.php

use Lalaz\Reactive\Http\ReactiveController;

$router->group(['prefix' => '/reactive'], function ($router) {
    $router->post('/call', [ReactiveController::class, 'call']);
    $router->post('/update', [ReactiveController::class, 'update']);
});
```

### Using Laravel

```php
<?php
// routes/web.php

use Lalaz\Reactive\Http\ReactiveController;

Route::post('/reactive/call', [ReactiveController::class, 'call']);
Route::post('/reactive/update', [ReactiveController::class, 'update']);
```

### Vanilla PHP

```php
<?php

use Lalaz\Reactive\Http\ReactiveController;
use Lalaz\Reactive\ReactiveManager;
use Lalaz\Reactive\ComponentRegistry;

$container = new YourContainer();
$registry = new ComponentRegistry();
$manager = new ReactiveManager($container, $registry);
$controller = new ReactiveController($manager);

// Route handling
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST' && $path === '/reactive/call') {
    $response = $controller->call(createRequestFromGlobals());
    sendResponse($response);
}

if ($method === 'POST' && $path === '/reactive/update') {
    $response = $controller->update(createRequestFromGlobals());
    sendResponse($response);
}
```

## Client-Side Setup

### JavaScript Integration

Include the reactive client library in your HTML:

```html
<!-- Using CDN -->
<script src="https://cdn.lalaz.dev/reactive/client.min.js"></script>

<!-- Or local file -->
<script src="/js/reactive.js"></script>
```

### Initialize the Client

```javascript
// Initialize with configuration
const reactive = new ReactiveClient({
    baseUrl: '/reactive',
    csrfToken: document.querySelector('meta[name="csrf-token"]').content,
});

// The client handles:
// - Calling component methods
// - Updating properties
// - Processing events
// - Handling notifications
// - Managing redirects
```

### Basic Client Usage

```javascript
// Call a component method
reactive.call('increment');
reactive.call('add', { amount: 5 });

// Update a property
reactive.set('quantity', 10);

// Listen for events
reactive.on('item:added', (data) => {
    console.log('Item added:', data);
});
```

## Directory Structure

Recommended project structure:

```
app/
├── Components/              # Your reactive components
│   ├── Counter.php
│   ├── ContactForm.php
│   └── ShoppingCart/
│       ├── Cart.php
│       ├── CartItem.php
│       └── CartSummary.php
├── Http/
│   └── Controllers/
config/
├── reactive.php             # Reactive configuration
public/
├── js/
│   └── reactive.js          # Client library
resources/
├── views/
│   └── components/          # Component view templates (optional)
tests/
├── Unit/
│   └── Components/          # Component unit tests
└── Integration/
    └── Components/          # Component integration tests
```

## Verification

Verify the installation:

```php
<?php

use Lalaz\Reactive\ReactiveComponent;
use Lalaz\Reactive\ReactiveManager;
use Lalaz\Reactive\ComponentRegistry;

// Create a test component
class TestComponent extends ReactiveComponent
{
    public string $message = 'Hello, Reactive!';
    
    public function render(): string
    {
        return "<div>{$this->message}</div>";
    }
}

// Initialize and test
$container = new Container();
$registry = new ComponentRegistry();
$manager = new ReactiveManager($container, $registry);

$snapshot = $manager->mount(TestComponent::class);

if (str_contains($snapshot['html'], 'Hello, Reactive!')) {
    echo "✅ Installation successful!";
} else {
    echo "❌ Something went wrong.";
}
```

## Troubleshooting

### Common Issues

#### Class Not Found

```
Error: Class 'App\Components\Counter' not found
```

**Solution**: Ensure autoloading is configured correctly in `composer.json`:

```json
{
    "autoload": {
        "psr-4": {
            "App\\": "app/"
        }
    }
}
```

Run `composer dump-autoload` after changes.

#### Method Not Accessible

```
Error: Method 'privateMethod' is not accessible
```

**Solution**: Only `public` methods can be called from the client. Make sure your action methods are public.

#### State Checksum Mismatch

```
Error: Invalid state checksum
```

**Solution**: The checksum secret must be the same on all requests. Check your configuration and ensure the secret key is consistent.

### Debug Mode

Enable debug mode for detailed error messages:

```php
// In development
$manager->setDebug(true);
```

## Next Steps

- [Quick Start Guide](quick-start.md) - Build your first component
- [Core Concepts](concepts.md) - Understand how it works
- [API Reference](api-reference.md) - Full API documentation
