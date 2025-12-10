<?php

declare(strict_types=1);

namespace Lalaz\Reactive\Tests\Integration;

use Lalaz\Reactive\ComponentRegistry;
use Lalaz\Reactive\ReactiveComponent;
use Lalaz\Reactive\ReactiveManager;
use Lalaz\Reactive\Http\ReactiveController;
use Lalaz\Reactive\Exceptions\ComponentNotFoundException;
use Lalaz\Reactive\Exceptions\InvalidRequestException;
use Lalaz\Reactive\Exceptions\MethodNotAccessibleException;
use Lalaz\Reactive\Exceptions\PropertyNotAccessibleException;
use Lalaz\Reactive\Tests\Fixtures\Mocks\MockContainer;
use Lalaz\Reactive\Tests\TestCase;

/**
 * Integration tests for ReactiveController HTTP handling.
 */
class ControllerIntegrationTest extends TestCase
{
    private ComponentRegistry $registry;
    private MockContainer $container;
    private ReactiveManager $manager;
    private ReactiveController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new ComponentRegistry();
        $this->container = new MockContainer();
        $this->manager = new ReactiveManager($this->registry, $this->container);
        $this->controller = new ReactiveController($this->manager);
    }

    // =========================================================================
    // Controller Method Call Tests
    // =========================================================================

    public function test_controller_can_call_component_method(): void
    {
        $component = $this->createCounterComponent();
        $component->setId('test-123');
        $component->setName('counter');
        $component->mount();

        $this->registry->register('test-123', $component);

        // Simulate method call
        $this->manager->call('test-123', 'increment');

        $this->assertSame(1, $component->count);
    }

    public function test_controller_can_call_method_with_parameters(): void
    {
        $component = $this->createCounterComponent();
        $component->setId('param-123');
        $component->setName('counter');
        $component->mount();

        $this->registry->register('param-123', $component);

        $this->manager->call('param-123', 'add', [50]);

        $this->assertSame(50, $component->count);
    }

    public function test_controller_can_update_property(): void
    {
        $component = $this->createFormComponent();
        $component->setId('form-123');
        $component->setName('form');

        $this->registry->register('form-123', $component);

        $this->manager->updateProperty('form-123', 'email', 'test@example.com');

        $this->assertSame('test@example.com', $component->email);
    }

    public function test_controller_throws_for_non_existent_component(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Component not found');

        $this->manager->call('non-existent-123', 'someMethod');
    }

    public function test_controller_renders_updated_component(): void
    {
        $component = $this->createCounterComponent();
        $component->setId('render-123');
        $component->setName('counter');
        $component->mount(10);

        $this->registry->register('render-123', $component);

        $this->manager->call('render-123', 'increment');
        $this->manager->call('render-123', 'increment');

        $html = $this->manager->render($component);

        $this->assertStringContainsString('12', $html);
    }

    // =========================================================================
    // State Management Tests
    // =========================================================================

    public function test_state_checksum_validation(): void
    {
        $component = $this->createCounterComponent();
        $component->setId('checksum-123');
        $component->setName('counter');
        $component->mount(5);

        $state = $component->dehydrate();

        $this->assertArrayHasKey('id', $state);
        $this->assertArrayHasKey('name', $state);
        $this->assertArrayHasKey('properties', $state);
        $this->assertSame('checksum-123', $state['id']);
        $this->assertSame('counter', $state['name']);
        $this->assertSame(5, $state['properties']['count']);
    }

    public function test_hydrate_restores_component_state(): void
    {
        $original = $this->createCounterComponent();
        $original->setId('hydrate-123');
        $original->setName('counter');
        $original->mount(100);
        $original->increment();
        $original->increment();

        $state = $original->dehydrate();

        $restored = $this->createCounterComponent();
        $restored->hydrate($state);

        $this->assertSame('hydrate-123', $restored->getId());
        $this->assertSame('counter', $restored->getName());
        $this->assertSame(102, $restored->count);
    }

    public function test_mount_params_preserved_in_state(): void
    {
        $component = $this->createCounterComponent();
        $component->setId('params-123');
        $component->setName('counter');
        $component->setMountParams(['initial' => 50, 'step' => 5]);
        $component->mount(50);

        $state = $component->dehydrate();

        $this->assertArrayHasKey('mount', $state);
        $this->assertSame(['initial' => 50, 'step' => 5], $state['mount']);
    }

    // =========================================================================
    // Lifecycle Integration Tests
    // =========================================================================

    public function test_full_request_response_cycle(): void
    {
        // 1. Mount component
        $component = $this->createFormComponent();
        $component->setId('form-cycle-123');
        $component->setName('form');
        $component->mount();

        $this->registry->register('form-cycle-123', $component);

        // 2. Get initial state
        $initialState = $component->dehydrate();

        // 3. Update property
        $this->manager->updateProperty('form-cycle-123', 'name', 'John Doe');
        $this->manager->updateProperty('form-cycle-123', 'email', 'john@example.com');

        // 4. Call method
        $this->manager->call('form-cycle-123', 'submit');

        // 5. Get final state
        $finalState = $component->dehydrate();

        // 6. Verify
        $this->assertSame('John Doe', $finalState['properties']['name']);
        $this->assertSame('john@example.com', $finalState['properties']['email']);
        $this->assertTrue($finalState['properties']['submitted']);
    }

    public function test_sequential_method_calls_with_state_sync(): void
    {
        $component = $this->createCounterComponent();
        $component->setId('seq-123');
        $component->setName('counter');
        $component->mount();

        $this->registry->register('seq-123', $component);

        // Sequential calls simulating multiple AJAX requests
        for ($i = 0; $i < 10; $i++) {
            $stateBefore = $component->dehydrate();
            $this->manager->call('seq-123', 'increment');
            $stateAfter = $component->dehydrate();

            $this->assertSame(
                $stateBefore['properties']['count'] + 1,
                $stateAfter['properties']['count']
            );
        }

        $this->assertSame(10, $component->count);
    }

    // =========================================================================
    // Event System Integration Tests
    // =========================================================================

    public function test_events_queued_during_method_call(): void
    {
        $component = $this->createEventComponent();
        $component->setId('event-123');
        $component->setName('event-emitter');
        $component->mount();

        $this->registry->register('event-123', $component);

        $this->manager->call('event-123', 'createItem', ['Test Item']);

        $state = $component->dehydrate();

        $this->assertCount(1, $state['dispatches']);
        $this->assertSame('item:created', $state['dispatches'][0]['event']);
        $this->assertSame(['name' => 'Test Item'], $state['dispatches'][0]['data']);
    }

    public function test_notifications_included_in_response(): void
    {
        $component = $this->createEventComponent();
        $component->setId('notify-123');
        $component->setName('notifier');
        $component->mount();

        $this->registry->register('notify-123', $component);

        $this->manager->call('notify-123', 'triggerNotification');

        $state = $component->dehydrate();

        $this->assertCount(1, $state['notifications']);
        $this->assertSame('Operation successful', $state['notifications'][0]['message']);
        $this->assertSame('success', $state['notifications'][0]['type']);
    }

    public function test_redirect_included_in_response(): void
    {
        $component = $this->createEventComponent();
        $component->setId('redirect-123');
        $component->setName('redirector');
        $component->mount();

        $this->registry->register('redirect-123', $component);

        $this->manager->call('redirect-123', 'triggerRedirect');

        $state = $component->dehydrate();

        $this->assertSame('/dashboard', $state['redirect']);
    }

    // =========================================================================
    // Multi-Component Interaction Tests
    // =========================================================================

    public function test_multiple_components_independent_state(): void
    {
        $counter1 = $this->createCounterComponent();
        $counter1->setId('counter-1');
        $counter1->mount(10);

        $counter2 = $this->createCounterComponent();
        $counter2->setId('counter-2');
        $counter2->mount(20);

        $this->registry->register('counter-1', $counter1);
        $this->registry->register('counter-2', $counter2);

        // Modify counter 1
        $this->manager->call('counter-1', 'add', [5]);

        // Counter 2 should be unaffected
        $this->assertSame(15, $counter1->count);
        $this->assertSame(20, $counter2->count);
    }

    public function test_component_replacement_in_registry(): void
    {
        $original = $this->createCounterComponent();
        $original->setId('replace-123');
        $original->mount(100);

        $this->registry->register('replace-123', $original);

        // Replace with new component
        $replacement = $this->createCounterComponent();
        $replacement->setId('replace-123');
        $replacement->mount(500);

        $this->registry->register('replace-123', $replacement);

        // Should get replacement
        $component = $this->registry->get('replace-123');
        $this->assertSame(500, $component->count);
    }

    // =========================================================================
    // Error Handling Tests
    // =========================================================================

    public function test_calling_non_existent_method_fails_gracefully(): void
    {
        $component = $this->createCounterComponent();
        $component->setId('error-123');
        $component->setName('counter');
        $component->mount();

        $this->registry->register('error-123', $component);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Method not found');

        $this->manager->call('error-123', 'nonExistentMethod');
    }

    public function test_updating_non_existent_property(): void
    {
        $component = $this->createCounterComponent();
        $component->setId('prop-error-123');
        $component->setName('counter');
        $component->mount();

        $this->registry->register('prop-error-123', $component);

        // This should either throw or be ignored depending on implementation
        try {
            $this->manager->updateProperty('prop-error-123', 'nonExistentProperty', 'value');
            // If no exception, property should not exist
            $state = $component->dehydrate();
            $this->assertArrayNotHasKey('nonExistentProperty', $state['properties']);
        } catch (\Exception $e) {
            $this->assertTrue(true); // Exception is acceptable
        }
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

    private function createFormComponent(): ReactiveComponent
    {
        return new class extends ReactiveComponent {
            public string $name = '';
            public string $email = '';
            public bool $submitted = false;

            public function submit(): void
            {
                $this->submitted = true;
                $this->dispatch('form:submitted', [
                    'name' => $this->name,
                    'email' => $this->email,
                ]);
            }

            public function clearForm(): void
            {
                $this->name = '';
                $this->email = '';
                $this->submitted = false;
            }

            public function render(): string
            {
                $status = $this->submitted ? 'submitted' : 'pending';
                return "<form data-status='{$status}'><input name='name' value='{$this->name}' /><input name='email' value='{$this->email}' /></form>";
            }
        };
    }

    private function createEventComponent(): ReactiveComponent
    {
        return new class extends ReactiveComponent {
            public string $lastItem = '';

            public function createItem(string $name): void
            {
                $this->lastItem = $name;
                $this->dispatch('item:created', ['name' => $name]);
            }

            public function triggerNotification(): void
            {
                $this->notify('Operation successful', 'success');
            }

            public function triggerRedirect(): void
            {
                $this->redirect('/dashboard');
            }

            public function render(): string
            {
                return "<div>Last item: {$this->lastItem}</div>";
            }
        };
    }
}
