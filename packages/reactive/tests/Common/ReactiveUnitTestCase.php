<?php

declare(strict_types=1);

namespace Lalaz\Reactive\Tests\Common;

use PHPUnit\Framework\TestCase;
use Lalaz\Reactive\ReactiveComponent;
use Lalaz\Reactive\ReactiveManager;
use Lalaz\Reactive\ComponentRegistry;
use Lalaz\Reactive\Tests\Fixtures\Mocks\MockContainer;

/**
 * Base test case for Reactive package unit tests.
 *
 * Provides common factory methods, assertions, and utilities
 * for testing reactive components.
 */
abstract class ReactiveUnitTestCase extends TestCase
{
    protected MockContainer $container;
    protected ComponentRegistry $registry;
    protected ReactiveManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new MockContainer();
        $this->registry = new ComponentRegistry();
        $this->manager = new ReactiveManager($this->container, $this->registry);
    }

    protected function tearDown(): void
    {
        $this->registry->clear();
        parent::tearDown();
    }

    /**
     * Create a mock container.
     */
    protected function createContainer(): MockContainer
    {
        return new MockContainer();
    }

    /**
     * Create a component registry.
     */
    protected function createRegistry(): ComponentRegistry
    {
        return new ComponentRegistry();
    }

    /**
     * Create a reactive manager.
     */
    protected function createManager(
        ?MockContainer $container = null,
        ?ComponentRegistry $registry = null
    ): ReactiveManager {
        return new ReactiveManager(
            $container ?? $this->container,
            $registry ?? $this->registry
        );
    }

    /**
     * Create a concrete test component instance.
     */
    protected function createComponent(array $properties = []): TestComponent
    {
        $component = new TestComponent();
        foreach ($properties as $key => $value) {
            $component->setProperty($key, $value);
        }
        return $component;
    }

    /**
     * Create a counter component instance.
     */
    protected function createCounterComponent(int $count = 0): CounterComponent
    {
        $component = new CounterComponent();
        $component->setProperty('count', $count);
        return $component;
    }

    /**
     * Create a form component instance.
     */
    protected function createFormComponent(
        string $name = '',
        string $email = ''
    ): FormComponent {
        $component = new FormComponent();
        $component->setProperty('name', $name);
        $component->setProperty('email', $email);
        return $component;
    }

    /**
     * Create a validatable component instance.
     */
    protected function createValidatableComponent(): ValidatableComponent
    {
        return new ValidatableComponent();
    }

    /**
     * Assert that a component has a specific property value.
     */
    protected function assertComponentProperty(
        ReactiveComponent $component,
        string $property,
        mixed $expected
    ): void {
        $this->assertEquals(
            $expected,
            $component->getProperty($property),
            "Component property '{$property}' does not match expected value"
        );
    }

    /**
     * Assert that a component is registered.
     */
    protected function assertComponentRegistered(string $id): void
    {
        $this->assertTrue(
            $this->registry->has($id),
            "Component with ID '{$id}' is not registered"
        );
    }

    /**
     * Assert that a component is not registered.
     */
    protected function assertComponentNotRegistered(string $id): void
    {
        $this->assertFalse(
            $this->registry->has($id),
            "Component with ID '{$id}' should not be registered"
        );
    }

    /**
     * Assert that component can be hydrated correctly.
     */
    protected function assertCanHydrate(
        ReactiveComponent $component,
        array $data
    ): void {
        $component->hydrate($data);
        foreach ($data as $key => $value) {
            $this->assertComponentProperty($component, $key, $value);
        }
    }

    /**
     * Assert that component renders valid HTML.
     */
    protected function assertRendersHtml(ReactiveComponent $component): void
    {
        $html = $component->render();
        $this->assertIsString($html);
        $this->assertNotEmpty($html);
    }

    /**
     * Assert that a component dispatches an event.
     */
    protected function assertDispatchesEvent(
        ReactiveComponent $component,
        string $event,
        callable $action
    ): void {
        $dispatched = false;
        $component->on($event, function () use (&$dispatched) {
            $dispatched = true;
        });

        $action();

        $this->assertTrue($dispatched, "Expected event '{$event}' was not dispatched");
    }

    /**
     * Get the dehydrated state of a component.
     */
    protected function getComponentState(ReactiveComponent $component): array
    {
        return $component->dehydrate();
    }

    /**
     * Simulate mounting a component through the manager.
     */
    protected function mountComponent(
        string $class,
        array $params = []
    ): array {
        return $this->manager->mount($class, $params);
    }

    /**
     * Simulate restoring a component from snapshot.
     */
    protected function restoreComponent(array $snapshot): ReactiveComponent
    {
        return $this->manager->restore($snapshot);
    }

    /**
     * Create a mock HTTP request.
     */
    protected function createMockRequest(array $data = []): array
    {
        return array_merge([
            'id' => 'test-id',
            'name' => TestComponent::class,
            'method' => 'testMethod',
            'params' => [],
            'state' => [],
        ], $data);
    }
}

/**
 * Simple test component for unit tests.
 */
class TestComponent extends ReactiveComponent
{
    public string $title = '';
    public string $content = '';
    public int $counter = 0;
    public array $items = [];

    public function mount(string $title = '', string $content = ''): void
    {
        $this->title = $title;
        $this->content = $content;
    }

    public function increment(): void
    {
        $this->counter++;
        $this->dispatch('incremented', ['count' => $this->counter]);
    }

    public function decrement(): void
    {
        $this->counter--;
    }

    public function addItem(string $item): void
    {
        $this->items[] = $item;
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function reset(): void
    {
        $this->counter = 0;
        $this->items = [];
    }

    public function render(): string
    {
        $itemsList = implode(', ', $this->items);
        return "<div><h1>{$this->title}</h1><p>{$this->content}</p><span>{$this->counter}</span><ul>{$itemsList}</ul></div>";
    }

    public function testMethod(): string
    {
        return 'test result';
    }
}

/**
 * Counter component for testing.
 */
class CounterComponent extends ReactiveComponent
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

    public function setCount(int $count): void
    {
        $this->count = $count;
    }

    public function render(): string
    {
        return "<div class='counter'><span>{$this->count}</span></div>";
    }
}

/**
 * Form component for testing validation.
 */
class FormComponent extends ReactiveComponent
{
    public string $name = '';
    public string $email = '';
    public bool $submitted = false;

    public function mount(string $name = '', string $email = ''): void
    {
        $this->name = $name;
        $this->email = $email;
    }

    public function submit(): void
    {
        $this->validate([
            'name' => 'required|min:2',
            'email' => 'required|email',
        ]);

        if (!$this->hasErrors()) {
            $this->submitted = true;
            $this->dispatch('form:submitted', [
                'name' => $this->name,
                'email' => $this->email,
            ]);
        }
    }

    public function clearForm(): void
    {
        $this->name = '';
        $this->email = '';
        $this->submitted = false;
    }

    public function render(): string
    {
        return "<form><input name='name' value='{$this->name}' /><input name='email' value='{$this->email}' /></form>";
    }
}

/**
 * Component with validation for testing.
 */
class ValidatableComponent extends ReactiveComponent
{
    public string $username = '';
    public string $password = '';
    public int $age = 0;

    public function validateCredentials(): bool
    {
        $this->validate([
            'username' => 'required|min:3|max:20',
            'password' => 'required|min:8',
            'age' => 'required|integer|min:18',
        ]);

        return !$this->hasErrors();
    }

    public function validateUsername(): bool
    {
        $this->validate(['username' => 'required|min:3']);
        return !$this->hasErrors();
    }

    public function render(): string
    {
        return "<div>Username: {$this->username}</div>";
    }
}
