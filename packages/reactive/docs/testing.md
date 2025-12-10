# Testing Guide

Complete guide for testing your reactive components.

## Table of Contents

- [Setup](#setup)
- [Unit Testing](#unit-testing)
- [Integration Testing](#integration-testing)
- [Test Utilities](#test-utilities)
- [Common Patterns](#common-patterns)
- [Coverage](#coverage)

## Setup

### Test Configuration

The package includes a PHPUnit configuration file. Create your own or extend the provided one:

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
    <coverage>
        <report>
            <html outputDirectory="coverage"/>
        </report>
    </coverage>
    <source>
        <include>
            <directory suffix=".php">src</directory>
        </include>
    </source>
</phpunit>
```

### Bootstrap File

```php
<?php
// tests/bootstrap.php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
```

### Base Test Case

Create a base test case for common functionality:

```php
<?php

namespace App\Tests;

use PHPUnit\Framework\TestCase;
use Lalaz\Reactive\ReactiveManager;
use Lalaz\Reactive\ComponentRegistry;
use Lalaz\Container\Container;

abstract class ReactiveTestCase extends TestCase
{
    protected Container $container;
    protected ComponentRegistry $registry;
    protected ReactiveManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->container = new Container();
        $this->registry = new ComponentRegistry();
        $this->manager = new ReactiveManager(
            $this->container,
            $this->registry
        );
    }

    protected function tearDown(): void
    {
        $this->registry->clear();
        parent::tearDown();
    }
}
```

## Unit Testing

### Testing Component Properties

```php
<?php

namespace App\Tests\Unit\Components;

use App\Components\Counter;
use App\Tests\ReactiveTestCase;

class CounterTest extends ReactiveTestCase
{
    public function test_initial_count_is_zero(): void
    {
        $counter = new Counter();
        
        $this->assertSame(0, $counter->count);
    }

    public function test_mount_sets_initial_count(): void
    {
        $counter = new Counter();
        $counter->mount(10);
        
        $this->assertSame(10, $counter->count);
    }

    public function test_increment_increases_count(): void
    {
        $counter = new Counter();
        $counter->mount(5);
        
        $counter->increment();
        
        $this->assertSame(6, $counter->count);
    }

    public function test_decrement_decreases_count(): void
    {
        $counter = new Counter();
        $counter->mount(5);
        
        $counter->decrement();
        
        $this->assertSame(4, $counter->count);
    }
}
```

### Testing Validation

```php
<?php

namespace App\Tests\Unit\Components;

use App\Components\ContactForm;
use App\Tests\ReactiveTestCase;

class ContactFormTest extends ReactiveTestCase
{
    public function test_validates_required_fields(): void
    {
        $form = new ContactForm();
        
        $form->submit();
        
        $this->assertTrue($form->hasErrors());
        $this->assertNotNull($form->getError('name'));
        $this->assertNotNull($form->getError('email'));
    }

    public function test_validates_email_format(): void
    {
        $form = new ContactForm();
        $form->name = 'John';
        $form->email = 'invalid-email';
        $form->message = 'Hello World!';
        
        $form->submit();
        
        $this->assertTrue($form->hasErrors());
        $this->assertNotNull($form->getError('email'));
    }

    public function test_passes_validation_with_valid_data(): void
    {
        $form = new ContactForm();
        $form->name = 'John';
        $form->email = 'john@example.com';
        $form->message = 'Hello World!';
        
        $form->submit();
        
        $this->assertFalse($form->hasErrors());
    }
}
```

### Testing Events

```php
<?php

namespace App\Tests\Unit\Components;

use App\Components\ShoppingCart;
use App\Tests\ReactiveTestCase;

class ShoppingCartTest extends ReactiveTestCase
{
    public function test_dispatches_event_when_item_added(): void
    {
        $cart = new ShoppingCart();
        
        $cart->addItem(1, 'Widget', 29.99);
        
        $events = $cart->getDispatchQueue();
        $this->assertCount(1, $events);
        $this->assertSame('item:added', $events[0]['event']);
        $this->assertSame(1, $events[0]['data']['productId']);
    }

    public function test_registers_event_listeners_on_mount(): void
    {
        $cart = new ShoppingCart();
        $cart->mount();
        
        $listeners = $cart->getListeners();
        $this->assertArrayHasKey('coupon:applied', $listeners);
    }
}
```

### Testing State Serialization

```php
<?php

namespace App\Tests\Unit\Components;

use App\Components\Counter;
use App\Tests\ReactiveTestCase;

class StateSerializationTest extends ReactiveTestCase
{
    public function test_dehydrate_includes_all_state(): void
    {
        $counter = new Counter();
        $counter->setId('test-123');
        $counter->setName('counter');
        $counter->mount(42);
        
        $state = $counter->dehydrate();
        
        $this->assertSame('test-123', $state['id']);
        $this->assertSame('counter', $state['name']);
        $this->assertSame(42, $state['properties']['count']);
    }

    public function test_hydrate_restores_state(): void
    {
        $state = [
            'id' => 'restored-123',
            'name' => 'counter',
            'mount' => ['initialCount' => 100],
            'properties' => ['count' => 100],
        ];
        
        $counter = new Counter();
        $counter->hydrate($state);
        
        $this->assertSame('restored-123', $counter->getId());
        $this->assertSame(100, $counter->count);
    }
}
```

## Integration Testing

### Testing Full Lifecycle

```php
<?php

namespace App\Tests\Integration\Components;

use App\Components\Counter;
use App\Tests\ReactiveTestCase;

class CounterLifecycleTest extends ReactiveTestCase
{
    public function test_full_mount_call_render_cycle(): void
    {
        // Mount
        $snapshot = $this->manager->mount(Counter::class, [
            'initialCount' => 10
        ]);
        
        $this->assertArrayHasKey('id', $snapshot);
        $this->assertArrayHasKey('html', $snapshot);
        $this->assertStringContainsString('10', $snapshot['html']);
        
        // Restore
        $counter = $this->manager->restore($snapshot);
        $this->assertSame(10, $counter->count);
        
        // Call method
        $this->manager->call($counter, 'increment');
        $this->manager->call($counter, 'increment');
        
        // Re-render
        $html = $this->manager->render($counter);
        $this->assertStringContainsString('12', $html);
    }

    public function test_state_persists_across_requests(): void
    {
        // First request - mount
        $snapshot1 = $this->manager->mount(Counter::class);
        $counter1 = $this->manager->restore($snapshot1);
        $this->manager->call($counter1, 'add', [50]);
        $snapshot2 = $counter1->dehydrate();
        
        // Second request - restore and modify
        $counter2 = new Counter();
        $counter2->hydrate($snapshot2);
        $this->manager->call($counter2, 'increment');
        
        $this->assertSame(51, $counter2->count);
    }
}
```

### Testing Component Registry

```php
<?php

namespace App\Tests\Integration;

use App\Components\Counter;
use App\Tests\ReactiveTestCase;

class ComponentRegistryTest extends ReactiveTestCase
{
    public function test_registers_and_retrieves_components(): void
    {
        $counter = new Counter();
        $counter->setId('test-123');
        
        $this->registry->register('test-123', $counter);
        
        $this->assertTrue($this->registry->has('test-123'));
        $this->assertSame($counter, $this->registry->get('test-123'));
    }

    public function test_removes_components(): void
    {
        $counter = new Counter();
        $counter->setId('removable');
        
        $this->registry->register('removable', $counter);
        $this->registry->remove('removable');
        
        $this->assertFalse($this->registry->has('removable'));
    }

    public function test_multiple_components_independent(): void
    {
        $counter1 = new Counter();
        $counter1->setId('c1');
        $counter1->mount(10);
        
        $counter2 = new Counter();
        $counter2->setId('c2');
        $counter2->mount(20);
        
        $this->registry->register('c1', $counter1);
        $this->registry->register('c2', $counter2);
        
        $this->manager->call('c1', 'increment');
        
        $this->assertSame(11, $counter1->count);
        $this->assertSame(20, $counter2->count);
    }
}
```

### Testing Controller

```php
<?php

namespace App\Tests\Integration\Http;

use App\Components\Counter;
use App\Tests\ReactiveTestCase;
use Lalaz\Reactive\Http\ReactiveController;

class ReactiveControllerTest extends ReactiveTestCase
{
    private ReactiveController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new ReactiveController($this->manager);
    }

    public function test_call_endpoint_executes_method(): void
    {
        // Setup component
        $counter = new Counter();
        $counter->setId('ajax-123');
        $counter->setName(Counter::class);
        $counter->mount(5);
        $this->registry->register('ajax-123', $counter);

        // Simulate call request
        $result = $this->manager->call('ajax-123', 'increment');
        
        $this->assertSame(6, $counter->count);
    }

    public function test_update_endpoint_modifies_property(): void
    {
        // Setup component
        $counter = new Counter();
        $counter->setId('update-123');
        $counter->setName(Counter::class);
        $counter->mount(0);
        $this->registry->register('update-123', $counter);

        // Simulate update
        $this->manager->updateProperty('update-123', 'count', 100);
        
        $this->assertSame(100, $counter->count);
    }
}
```

## Test Utilities

### Mock Container

```php
<?php

namespace App\Tests\Mocks;

use Lalaz\Container\Contracts\ContainerInterface;

class MockContainer implements ContainerInterface
{
    private array $bindings = [];
    private array $instances = [];

    public function bind(string $abstract, mixed $concrete = null): void
    {
        $this->bindings[$abstract] = $concrete ?? $abstract;
    }

    public function resolve(string $abstract, array $parameters = []): mixed
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if (isset($this->bindings[$abstract])) {
            $concrete = $this->bindings[$abstract];
            if (is_callable($concrete)) {
                return $concrete($this, $parameters);
            }
            if (class_exists($concrete)) {
                return new $concrete();
            }
            return $concrete;
        }

        if (class_exists($abstract)) {
            return new $abstract();
        }

        throw new \Exception("Cannot resolve: {$abstract}");
    }

    // ... implement other interface methods
}
```

### Test Component Factory

```php
<?php

namespace App\Tests\Factories;

use Lalaz\Reactive\ReactiveComponent;

class ComponentFactory
{
    public static function createCounter(int $initial = 0): CounterStub
    {
        $counter = new CounterStub();
        $counter->setId('counter-' . uniqid());
        $counter->setName('counter');
        $counter->mount($initial);
        return $counter;
    }

    public static function createForm(array $data = []): FormStub
    {
        $form = new FormStub();
        $form->setId('form-' . uniqid());
        $form->setName('form');
        foreach ($data as $key => $value) {
            $form->setProperty($key, $value);
        }
        return $form;
    }
}

class CounterStub extends ReactiveComponent
{
    public int $count = 0;

    public function mount(int $initial = 0): void
    {
        $this->count = $initial;
    }

    public function increment(): void
    {
        $this->count++;
    }

    public function decrement(): void
    {
        $this->count--;
    }

    public function render(): string
    {
        return "<span>{$this->count}</span>";
    }
}
```

## Common Patterns

### Testing Notifications

```php
public function test_shows_success_notification(): void
{
    $form = new ContactForm();
    $form->name = 'John';
    $form->email = 'john@example.com';
    $form->message = 'Hello!';
    
    $form->submit();
    
    $notifications = $form->getNotifications();
    $this->assertCount(1, $notifications);
    $this->assertSame('success', $notifications[0]['type']);
}
```

### Testing Redirects

```php
public function test_redirects_after_save(): void
{
    $form = new RegistrationForm();
    $form->username = 'newuser';
    $form->email = 'new@example.com';
    $form->password = 'securepassword123';
    
    $form->submit();
    
    $this->assertSame('/welcome', $form->getRedirect());
}
```

### Testing Property Updates

```php
public function test_updated_hook_called(): void
{
    $component = new TrackedComponent();
    
    $component->setProperty('name', 'New Name');
    
    $this->assertTrue($component->wasUpdated('name'));
}
```

## Coverage

### Running Tests with Coverage

```bash
# Generate HTML coverage report
./vendor/bin/phpunit --coverage-html coverage

# Generate text coverage report
./vendor/bin/phpunit --coverage-text

# Run specific test suite
./vendor/bin/phpunit --testsuite Unit
./vendor/bin/phpunit --testsuite Integration
```

### Current Coverage

The Reactive package maintains high test coverage:

| Component | Coverage |
|-----------|----------|
| ReactiveComponent | > 90% |
| ReactiveManager | > 85% |
| ComponentRegistry | 100% |
| ReactiveController | > 80% |
| Exceptions | 100% |

### Test Statistics

- **117 tests passing**
- **275 assertions**
- **84 Unit tests**
- **33 Integration tests**
