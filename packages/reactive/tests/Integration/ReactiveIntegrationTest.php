<?php

declare(strict_types=1);

namespace Lalaz\Reactive\Tests\Integration;

use Lalaz\Reactive\ComponentRegistry;
use Lalaz\Reactive\ReactiveComponent;
use Lalaz\Reactive\ReactiveManager;
use Lalaz\Reactive\Tests\Fixtures\Mocks\MockContainer;
use Lalaz\Reactive\Tests\TestCase;

/**
 * Integration tests for the Reactive package.
 *
 * These tests verify that multiple components work together correctly,
 * including the full component lifecycle and state management.
 */
class ReactiveIntegrationTest extends TestCase
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
    // Component Lifecycle Tests
    // =========================================================================

    public function test_full_component_lifecycle_mount_call_render(): void
    {
        $component = $this->createCounterComponent();

        // 1. Set up component (simulating mount)
        $component->setId('counter-123');
        $component->setName('counter');
        $component->setMountParams(['initial' => 5]);
        $component->mount(5);

        $this->registry->register('counter-123', $component);

        // 2. Call method that changes state
        $this->manager->call('counter-123', 'increment');
        $this->manager->call('counter-123', 'increment');

        // 3. Render and verify HTML contains updated state
        $html = $this->manager->render($component);

        $this->assertStringContainsString('reactive:id="counter-123"', $html);
        $this->assertStringContainsString('reactive:name="counter"', $html);
        $this->assertStringContainsString('7', $html); // 5 + 2 increments
    }

    public function test_state_persistence_through_dehydrate_and_hydrate(): void
    {
        $original = $this->createCounterComponent();
        $original->setId('counter-456');
        $original->setName('counter');
        $original->setMountParams(['initial' => 10]);
        $original->mount(10);

        // Make changes
        $original->increment();
        $original->increment();
        $original->increment();

        // Dehydrate state
        $state = $original->dehydrate();

        $this->assertSame('counter-456', $state['id']);
        $this->assertSame('counter', $state['name']);
        $this->assertSame(13, $state['properties']['count']);
        $this->assertSame(['initial' => 10], $state['mount']);

        // Create new instance and hydrate
        $restored = $this->createCounterComponent();
        $restored->hydrate($state);

        // Verify state was restored
        $this->assertSame('counter-456', $restored->getId());
        $this->assertSame('counter', $restored->getName());
        $this->assertSame(13, $restored->count);
        $this->assertSame(['initial' => 10], $restored->getMountParams());
    }

    public function test_multiple_method_calls_accumulate_state_changes(): void
    {
        $component = $this->createCounterComponent();
        $component->setId('multi-123');
        $component->setMountParams([]);
        $component->mount();

        $this->registry->register('multi-123', $component);

        // Multiple operations
        $this->manager->call('multi-123', 'increment');
        $this->manager->call('multi-123', 'add', [5]);
        $this->manager->call('multi-123', 'increment');
        $this->manager->call('multi-123', 'decrement');
        $this->manager->call('multi-123', 'add', [10]);

        $this->assertSame(16, $component->count); // 0 + 1 + 5 + 1 - 1 + 10
    }

    public function test_property_update_triggers_updated_callback(): void
    {
        $component = $this->createTrackingComponent();
        $component->setId('tracking-123');

        $this->registry->register('tracking-123', $component);

        // Update multiple properties
        $this->manager->updateProperty('tracking-123', 'name', 'Alice');
        $this->manager->updateProperty('tracking-123', 'email', 'alice@example.com');
        $this->manager->updateProperty('tracking-123', 'name', 'Bob');

        // Verify updated() was called for each property
        $this->assertSame(['name', 'email', 'name'], $component->updatedProperties);
    }

    // =========================================================================
    // Event System Tests
    // =========================================================================

    public function test_events_can_be_dispatched_and_queued(): void
    {
        $component = $this->createEventComponent();
        $component->setId('event-123');
        $component->setName('event-emitter');
        $component->mount();

        $this->registry->register('event-123', $component);

        // Trigger method that dispatches events
        $this->manager->call('event-123', 'createUser', [['id' => 42, 'name' => 'John']]);

        $queue = $component->getDispatchQueue();

        $this->assertCount(2, $queue);
        $this->assertSame('user-created', $queue[0]['event']);
        $this->assertSame(['userId' => 42], $queue[0]['data']);
        $this->assertSame('activity-logged', $queue[1]['event']);
    }

    public function test_listeners_are_registered_during_mount(): void
    {
        $component = $this->createListenerComponent();
        $component->setId('listener-123');
        $component->setName('listener');
        $component->mount();

        $listeners = $component->getListeners();

        $this->assertArrayHasKey('user-created', $listeners);
        $this->assertArrayHasKey('user-deleted', $listeners);
        $this->assertSame('onUserCreated', $listeners['user-created']);
        $this->assertSame('onUserDeleted', $listeners['user-deleted']);
    }

    public function test_dehydrated_state_includes_event_metadata(): void
    {
        $component = $this->createEventComponent();
        $component->setId('event-456');
        $component->setName('event-emitter');
        $component->mount();

        // Trigger events
        $component->createUser(['id' => 1, 'name' => 'Test']);

        // Add notifications
        $component->showSuccess('User created!');

        // Set redirect
        $component->redirectToUsers();

        $state = $component->dehydrate();

        // Verify all metadata is included
        $this->assertCount(2, $state['dispatches']);
        $this->assertSame('/users', $state['redirect']);
        $this->assertCount(1, $state['notifications']);
        $this->assertSame('User created!', $state['notifications'][0]['message']);
        $this->assertSame('success', $state['notifications'][0]['type']);
    }

    // =========================================================================
    // Render Tests
    // =========================================================================

    public function test_render_includes_all_reactive_attributes(): void
    {
        $component = $this->createFullComponent();
        $component->setId('full-123');
        $component->setName('full-component');
        $component->setMountParams(['userId' => 42]);
        $component->mount(42);

        $html = $this->manager->render($component);

        // Check all reactive attributes are present
        $this->assertStringContainsString('reactive:id="full-123"', $html);
        $this->assertStringContainsString('reactive:name="full-component"', $html);
        $this->assertStringContainsString('reactive:state=', $html);
        $this->assertStringContainsString('reactive:params=', $html);
        $this->assertStringContainsString('reactive:listeners=', $html);

        // Check state includes properties (HTML escaped quotes)
        $this->assertStringContainsString('count', $html);
        $this->assertStringContainsString('name', $html);

        // Check listeners are included
        $this->assertStringContainsString('data-updated', $html);
    }

    public function test_render_escapes_html_in_attributes(): void
    {
        $component = $this->createCounterComponent();
        $component->setId('escape-<script>');
        $component->setName('counter');

        $html = $this->manager->render($component);

        // ID should be HTML escaped
        $this->assertStringContainsString('escape-&lt;script&gt;', $html);
        $this->assertStringNotContainsString('escape-<script>', $html);
    }

    // =========================================================================
    // Registry Integration Tests
    // =========================================================================

    public function test_multiple_components_can_be_registered_and_called(): void
    {
        $counter1 = $this->createCounterComponent();
        $counter1->setId('counter-1');
        $counter1->mount();

        $counter2 = $this->createCounterComponent();
        $counter2->setId('counter-2');
        $counter2->mount(100);

        $this->registry->register('counter-1', $counter1);
        $this->registry->register('counter-2', $counter2);

        // Call methods on different components
        $this->manager->call('counter-1', 'add', [5]);
        $this->manager->call('counter-2', 'decrement');

        $this->assertSame(5, $counter1->count);
        $this->assertSame(99, $counter2->count);
    }

    public function test_component_removal_and_cleanup(): void
    {
        $component = $this->createCounterComponent();
        $component->setId('removable-123');

        $this->registry->register('removable-123', $component);
        $this->assertTrue($this->registry->has('removable-123'));

        $this->registry->remove('removable-123');
        $this->assertFalse($this->registry->has('removable-123'));
        $this->assertNull($this->registry->get('removable-123'));
    }

    public function test_registry_clear_removes_all_components(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $component = $this->createCounterComponent();
            $component->setId("counter-{$i}");
            $this->registry->register("counter-{$i}", $component);
        }

        $this->assertCount(5, $this->registry->all());

        $this->registry->clear();

        $this->assertCount(0, $this->registry->all());
    }

    // =========================================================================
    // Validation Integration Tests
    // =========================================================================

    public function test_validation_errors_persist_across_operations(): void
    {
        $component = $this->createFormComponent();
        $component->setId('form-123');

        $this->registry->register('form-123', $component);

        // Set invalid data
        $this->manager->updateProperty('form-123', 'email', 'invalid');
        $this->manager->call('form-123', 'simulateValidationError');

        $this->assertTrue($component->hasErrors());
        $this->assertSame('Invalid email format', $component->getError('email'));

        // Render should still work
        $html = $this->manager->render($component);
        $this->assertStringContainsString('form-123', $html);
    }

    // =========================================================================
    // Reset Functionality Tests
    // =========================================================================

    public function test_reset_restores_all_properties_to_defaults(): void
    {
        $component = $this->createResettableComponent();
        $component->setId('reset-123');

        // Change all properties
        $component->count = 100;
        $component->name = 'Changed';
        $component->items = ['a', 'b', 'c'];

        // Reset all
        $component->resetAll();

        $this->assertSame(0, $component->count);
        $this->assertSame('default', $component->name);
        $this->assertSame([], $component->items);
    }

    public function test_reset_specific_properties(): void
    {
        $component = $this->createResettableComponent();
        $component->setId('reset-456');

        // Change all properties
        $component->count = 100;
        $component->name = 'Changed';
        $component->items = ['a', 'b', 'c'];

        // Reset only count
        $component->resetCount();

        $this->assertSame(0, $component->count);
        $this->assertSame('Changed', $component->name); // unchanged
        $this->assertSame(['a', 'b', 'c'], $component->items); // unchanged
    }

    // =========================================================================
    // Helper Methods - Component Factories
    // =========================================================================

    private function createCounterComponent(): ReactiveComponent
    {
        return new class extends ReactiveComponent {
            public int $count = 0;

            public function mount(...$params): void
            {
                if (isset($params[0])) {
                    $this->count = (int) $params[0];
                }
            }

            public function increment(): void
            {
                $this->count++;
            }

            public function decrement(): void
            {
                $this->count--;
            }

            public function add(int $amount): void
            {
                $this->count += $amount;
            }

            public function render(): string
            {
                return "<span>Count: {$this->count}</span>";
            }
        };
    }

    private function createTrackingComponent(): ReactiveComponent
    {
        return new class extends ReactiveComponent {
            public string $name = '';
            public string $email = '';
            public array $updatedProperties = [];

            public function updated(string $property): void
            {
                $this->updatedProperties[] = $property;
            }

            public function render(): string
            {
                return "<div>{$this->name} - {$this->email}</div>";
            }
        };
    }

    private function createEventComponent(): ReactiveComponent
    {
        return new class extends ReactiveComponent {
            public function createUser(array $data): void
            {
                $this->dispatch('user-created', ['userId' => $data['id']]);
                $this->dispatch('activity-logged', ['action' => 'create']);
            }

            public function showSuccess(string $message): void
            {
                $this->notify($message, 'success');
            }

            public function redirectToUsers(): void
            {
                $this->redirect('/users');
            }

            public function render(): string
            {
                return '<div>Event Component</div>';
            }
        };
    }

    private function createListenerComponent(): ReactiveComponent
    {
        return new class extends ReactiveComponent {
            public array $receivedEvents = [];

            public function mount(...$params): void
            {
                $this->listen('user-created', 'onUserCreated');
                $this->listen('user-deleted', 'onUserDeleted');
            }

            public function onUserCreated(array $data): void
            {
                $this->receivedEvents[] = ['user-created', $data];
            }

            public function onUserDeleted(array $data): void
            {
                $this->receivedEvents[] = ['user-deleted', $data];
            }

            public function render(): string
            {
                return '<div>Listener Component</div>';
            }
        };
    }

    private function createFullComponent(): ReactiveComponent
    {
        return new class extends ReactiveComponent {
            public int $count = 0;
            public string $name = '';

            public function mount(...$params): void
            {
                $this->listen('data-updated', 'onDataUpdated');
            }

            public function onDataUpdated(array $data): void
            {
            }

            public function render(): string
            {
                return "<div>Full: {$this->count} - {$this->name}</div>";
            }
        };
    }

    private function createFormComponent(): ReactiveComponent
    {
        return new class extends ReactiveComponent {
            public string $email = '';
            public string $name = '';

            public function simulateValidationError(): void
            {
                $this->errors = ['email' => 'Invalid email format'];
            }

            public function render(): string
            {
                return '<form>Form</form>';
            }
        };
    }

    private function createResettableComponent(): ReactiveComponent
    {
        return new class extends ReactiveComponent {
            public int $count = 0;
            public string $name = 'default';
            public array $items = [];

            public function resetAll(): void
            {
                $this->reset();
            }

            public function resetCount(): void
            {
                $this->reset('count');
            }

            public function render(): string
            {
                return '<div>Resettable</div>';
            }
        };
    }
}
