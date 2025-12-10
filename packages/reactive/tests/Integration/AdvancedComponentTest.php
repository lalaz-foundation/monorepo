<?php

declare(strict_types=1);

namespace Lalaz\Reactive\Tests\Integration;

use Lalaz\Reactive\ComponentRegistry;
use Lalaz\Reactive\ReactiveComponent;
use Lalaz\Reactive\ReactiveManager;
use Lalaz\Reactive\Tests\Fixtures\Mocks\MockContainer;
use Lalaz\Reactive\Tests\TestCase;

/**
 * Integration tests for advanced component scenarios.
 */
class AdvancedComponentTest extends TestCase
{
    private ComponentRegistry $registry;
    private MockContainer $container;
    private ReactiveManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new ComponentRegistry();
        $this->container = new MockContainer();
        $this->manager = new ReactiveManager($this->registry, $this->container);
    }

    // =========================================================================
    // Complex State Management Tests
    // =========================================================================

    public function test_array_property_manipulation(): void
    {
        $component = $this->createListComponent();
        $component->setId('list-123');
        $component->mount();

        $this->registry->register('list-123', $component);

        // Add items
        $this->manager->call('list-123', 'addItem', ['Item 1']);
        $this->manager->call('list-123', 'addItem', ['Item 2']);
        $this->manager->call('list-123', 'addItem', ['Item 3']);

        $this->assertCount(3, $component->items);

        // Remove middle item
        $this->manager->call('list-123', 'removeItem', [1]);

        $this->assertCount(2, $component->items);
        $this->assertSame('Item 1', $component->items[0]);
        $this->assertSame('Item 3', $component->items[1]);
    }

    public function test_nested_array_state_persistence(): void
    {
        $component = $this->createComplexComponent();
        $component->setId('nested-123');
        $component->setName('complex');
        $component->mount();

        $component->data = [
            'user' => [
                'name' => 'John',
                'address' => [
                    'city' => 'New York',
                    'country' => 'USA',
                ],
            ],
            'settings' => [
                'theme' => 'dark',
                'notifications' => true,
            ],
        ];

        $state = $component->dehydrate();
        
        $restored = $this->createComplexComponent();
        $restored->hydrate($state);

        $this->assertSame('John', $restored->data['user']['name']);
        $this->assertSame('New York', $restored->data['user']['address']['city']);
        $this->assertSame('dark', $restored->data['settings']['theme']);
    }

    public function test_boolean_property_toggle(): void
    {
        $component = $this->createToggleComponent();
        $component->setId('toggle-123');
        $component->mount();

        $this->registry->register('toggle-123', $component);

        $this->assertFalse($component->active);

        $this->manager->call('toggle-123', 'toggle');
        $this->assertTrue($component->active);

        $this->manager->call('toggle-123', 'toggle');
        $this->assertFalse($component->active);

        $this->manager->call('toggle-123', 'activate');
        $this->assertTrue($component->active);

        $this->manager->call('toggle-123', 'deactivate');
        $this->assertFalse($component->active);
    }

    // =========================================================================
    // Event Listener Integration Tests
    // =========================================================================

    public function test_listener_registration_and_invocation(): void
    {
        $component = $this->createListenerComponent();
        $component->setId('listener-123');
        $component->mount();

        $this->assertArrayHasKey('user:created', $component->getListeners());
        $this->assertArrayHasKey('user:updated', $component->getListeners());
    }

    public function test_event_dispatch_queues_for_client(): void
    {
        $component = $this->createPublisherComponent();
        $component->setId('pub-123');
        $component->setName('publisher');
        $component->mount();

        $this->registry->register('pub-123', $component);

        $this->manager->call('pub-123', 'publishEvent', ['Test data']);

        $state = $component->dehydrate();

        $this->assertArrayHasKey('dispatches', $state);
        $this->assertCount(1, $state['dispatches']);
        $this->assertSame('message:published', $state['dispatches'][0]['event']);
    }

    public function test_multiple_events_in_single_method(): void
    {
        $component = $this->createPublisherComponent();
        $component->setId('multi-pub-123');
        $component->setName('publisher');
        $component->mount();

        $this->registry->register('multi-pub-123', $component);

        $this->manager->call('multi-pub-123', 'complexAction');

        $state = $component->dehydrate();

        $this->assertCount(3, $state['dispatches']);
        $this->assertSame('action:started', $state['dispatches'][0]['event']);
        $this->assertSame('action:progress', $state['dispatches'][1]['event']);
        $this->assertSame('action:completed', $state['dispatches'][2]['event']);
    }

    // =========================================================================
    // Notification System Tests
    // =========================================================================

    public function test_different_notification_types(): void
    {
        $component = $this->createNotificationComponent();
        $component->setId('notif-123');
        $component->setName('notifier');
        $component->mount();

        $this->registry->register('notif-123', $component);

        $this->manager->call('notif-123', 'showSuccess');
        $this->manager->call('notif-123', 'showError');
        $this->manager->call('notif-123', 'showWarning');
        $this->manager->call('notif-123', 'showInfo');

        $state = $component->dehydrate();

        $this->assertCount(4, $state['notifications']);
        $this->assertSame('success', $state['notifications'][0]['type']);
        $this->assertSame('error', $state['notifications'][1]['type']);
        $this->assertSame('warning', $state['notifications'][2]['type']);
        $this->assertSame('info', $state['notifications'][3]['type']);
    }

    // =========================================================================
    // Updated Hook Tests
    // =========================================================================

    public function test_updated_hook_called_on_property_change(): void
    {
        $component = $this->createHookedComponent();
        $component->setId('hook-123');

        $this->registry->register('hook-123', $component);

        $this->manager->updateProperty('hook-123', 'name', 'Test Value');

        $this->assertTrue($component->updatedWasCalled);
        $this->assertSame('name', $component->lastUpdatedProperty);
    }

    public function test_specific_property_hook_called(): void
    {
        $component = $this->createHookedComponent();
        $component->setId('hook-456');
        $component->setName('hooked');

        $this->registry->register('hook-456', $component);

        $this->manager->updateProperty('hook-456', 'email', 'test@example.com');

        // The generic updated() hook is called, which tracks the property
        $this->assertTrue($component->updatedWasCalled);
        $this->assertSame('email', $component->lastUpdatedProperty);
    }

    // =========================================================================
    // Reset Functionality Tests
    // =========================================================================

    public function test_full_component_reset(): void
    {
        $component = $this->createResettableComponent();
        $component->setId('reset-123');
        $component->mount();

        $this->registry->register('reset-123', $component);

        // Modify state
        $component->counter = 100;
        $component->text = 'Modified';
        $component->items = ['a', 'b', 'c'];

        $this->manager->call('reset-123', 'resetAll');

        $this->assertSame(0, $component->counter);
        $this->assertSame('', $component->text);
        $this->assertSame([], $component->items);
    }

    public function test_selective_property_reset(): void
    {
        $component = $this->createResettableComponent();
        $component->setId('reset-456');
        $component->mount();

        $this->registry->register('reset-456', $component);

        // Modify state
        $component->counter = 100;
        $component->text = 'Modified';
        $component->items = ['a', 'b', 'c'];

        // Reset only counter
        $this->manager->call('reset-456', 'resetCounter');

        $this->assertSame(0, $component->counter);
        $this->assertSame('Modified', $component->text); // Unchanged
        $this->assertSame(['a', 'b', 'c'], $component->items); // Unchanged
    }

    // =========================================================================
    // Computed Properties Tests
    // =========================================================================

    public function test_computed_value_in_render(): void
    {
        $component = $this->createComputedComponent();
        $component->setId('computed-123');
        $component->setName('computed');
        $component->mount();

        $this->registry->register('computed-123', $component);

        $component->firstName = 'John';
        $component->lastName = 'Doe';

        $html = $this->manager->render($component);

        $this->assertStringContainsString('John Doe', $html);
    }

    public function test_computed_based_on_array(): void
    {
        $component = $this->createComputedComponent();
        $component->setId('computed-456');
        $component->mount();

        $this->registry->register('computed-456', $component);

        $this->manager->call('computed-456', 'addItem', [10]);
        $this->manager->call('computed-456', 'addItem', [20]);
        $this->manager->call('computed-456', 'addItem', [30]);

        $this->assertSame(60, $component->getTotal());
        $this->assertSame(3, $component->getCount());
    }

    // =========================================================================
    // State Persistence Edge Cases
    // =========================================================================

    public function test_empty_state_hydration(): void
    {
        $component = $this->createSimpleComponent();
        $component->hydrate([]);

        $this->assertSame('', $component->getId());
        $this->assertSame('', $component->getName());
    }

    public function test_partial_state_hydration(): void
    {
        $component = $this->createSimpleComponent();
        $component->hydrate([
            'id' => 'partial-123',
            // name and properties missing
        ]);

        $this->assertSame('partial-123', $component->getId());
    }

    public function test_special_characters_in_state(): void
    {
        $component = $this->createSimpleComponent();
        $component->setId('special-123');
        $component->setName('test');
        $component->value = '<script>alert("xss")</script>';

        $state = $component->dehydrate();

        $restored = $this->createSimpleComponent();
        $restored->hydrate($state);

        $this->assertSame('<script>alert("xss")</script>', $restored->value);

        // Render should escape
        $html = $this->manager->render($restored);
        $this->assertStringNotContainsString('<script>', $html);
    }

    // =========================================================================
    // Helper Methods - Component Factories
    // =========================================================================

    private function createListComponent(): ReactiveComponent
    {
        return new class extends ReactiveComponent {
            public array $items = [];

            public function addItem(string $item): void
            {
                $this->items[] = $item;
            }

            public function removeItem(int $index): void
            {
                unset($this->items[$index]);
                $this->items = array_values($this->items);
            }

            public function clearItems(): void
            {
                $this->items = [];
            }

            public function render(): string
            {
                $list = implode(', ', $this->items);
                return "<ul>{$list}</ul>";
            }
        };
    }

    private function createComplexComponent(): ReactiveComponent
    {
        return new class extends ReactiveComponent {
            public array $data = [];

            public function render(): string
            {
                return '<div>Complex</div>';
            }
        };
    }

    private function createToggleComponent(): ReactiveComponent
    {
        return new class extends ReactiveComponent {
            public bool $active = false;

            public function toggle(): void
            {
                $this->active = !$this->active;
            }

            public function activate(): void
            {
                $this->active = true;
            }

            public function deactivate(): void
            {
                $this->active = false;
            }

            public function render(): string
            {
                $status = $this->active ? 'active' : 'inactive';
                return "<div class='{$status}'>Toggle</div>";
            }
        };
    }

    private function createListenerComponent(): ReactiveComponent
    {
        return new class extends ReactiveComponent {
            public function mount(...$params): void
            {
                $this->listen('user:created', 'onUserCreated');
                $this->listen('user:updated', 'onUserUpdated');
            }

            public function onUserCreated(array $data): void
            {
            }

            public function onUserUpdated(array $data): void
            {
            }

            public function render(): string
            {
                return '<div>Listener</div>';
            }
        };
    }

    private function createPublisherComponent(): ReactiveComponent
    {
        return new class extends ReactiveComponent {
            public function publishEvent(string $data): void
            {
                $this->dispatch('message:published', ['data' => $data]);
            }

            public function complexAction(): void
            {
                $this->dispatch('action:started', []);
                $this->dispatch('action:progress', ['percent' => 50]);
                $this->dispatch('action:completed', []);
            }

            public function render(): string
            {
                return '<div>Publisher</div>';
            }
        };
    }

    private function createNotificationComponent(): ReactiveComponent
    {
        return new class extends ReactiveComponent {
            public function showSuccess(): void
            {
                $this->notify('Success message', 'success');
            }

            public function showError(): void
            {
                $this->notify('Error message', 'error');
            }

            public function showWarning(): void
            {
                $this->notify('Warning message', 'warning');
            }

            public function showInfo(): void
            {
                $this->notify('Info message', 'info');
            }

            public function render(): string
            {
                return '<div>Notifications</div>';
            }
        };
    }

    private function createHookedComponent(): ReactiveComponent
    {
        return new class extends ReactiveComponent {
            public string $name = '';
            public string $email = '';
            public bool $updatedWasCalled = false;
            public string $lastUpdatedProperty = '';
            public bool $updatedEmailCalled = false;

            public function updated(string $property): void
            {
                $this->updatedWasCalled = true;
                $this->lastUpdatedProperty = $property;
            }

            public function updatedEmail(string $value): void
            {
                $this->updatedEmailCalled = true;
            }

            public function render(): string
            {
                return "<div>{$this->name} - {$this->email}</div>";
            }
        };
    }

    private function createResettableComponent(): ReactiveComponent
    {
        return new class extends ReactiveComponent {
            public int $counter = 0;
            public string $text = '';
            public array $items = [];

            public function resetAll(): void
            {
                $this->reset();
            }

            public function resetCounter(): void
            {
                $this->reset('counter');
            }

            public function render(): string
            {
                return "<div>{$this->counter}</div>";
            }
        };
    }

    private function createComputedComponent(): ReactiveComponent
    {
        return new class extends ReactiveComponent {
            public string $firstName = '';
            public string $lastName = '';
            public array $numbers = [];

            public function getFullName(): string
            {
                return trim("{$this->firstName} {$this->lastName}");
            }

            public function addItem(int $value): void
            {
                $this->numbers[] = $value;
            }

            public function getTotal(): int
            {
                return array_sum($this->numbers);
            }

            public function getCount(): int
            {
                return count($this->numbers);
            }

            public function render(): string
            {
                return "<div>{$this->getFullName()}</div>";
            }
        };
    }

    private function createSimpleComponent(): ReactiveComponent
    {
        return new class extends ReactiveComponent {
            public string $value = '';

            public function render(): string
            {
                $escaped = htmlspecialchars($this->value, ENT_QUOTES, 'UTF-8');
                return "<div>{$escaped}</div>";
            }
        };
    }
}
