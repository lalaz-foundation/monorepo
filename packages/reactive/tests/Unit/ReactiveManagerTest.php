<?php

declare(strict_types=1);

namespace Lalaz\Reactive\Tests\Unit;

use Lalaz\Reactive\ComponentRegistry;
use Lalaz\Reactive\Exceptions\ComponentNotFoundException;
use Lalaz\Reactive\ReactiveComponent;
use Lalaz\Reactive\ReactiveManager;
use Lalaz\Reactive\Tests\Fixtures\Mocks\MockContainer;
use Lalaz\Reactive\Tests\TestCase;

/**
 * Tests for ReactiveManager.
 *
 * Note: Some tests require a full container implementation. Tests that can
 * work without the container focus on the render, call, and updateProperty
 * methods which work with already-registered components.
 */
class ReactiveManagerTest extends TestCase
{
    private ComponentRegistry $registry;
    private ReactiveManager $manager;
    private MockContainer $container;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new ComponentRegistry();
        $this->container = new MockContainer();
        $this->manager = new ReactiveManager($this->registry, $this->container);
    }

    public function test_render_wraps_html_in_container(): void
    {
        $component = new class extends ReactiveComponent {
            public function render(): string
            {
                return '<span>Hello</span>';
            }
        };

        $component->setId('test-123');
        $component->setName('test-component');

        $html = $this->manager->render($component);

        $this->assertStringContainsString('reactive:id="test-123"', $html);
        $this->assertStringContainsString('reactive:name="test-component"', $html);
        $this->assertStringContainsString('<span>Hello</span>', $html);
        $this->assertStringStartsWith('<div', $html);
        $this->assertStringEndsWith('</div>', $html);
    }

    public function test_render_includes_state_in_container(): void
    {
        $component = new class extends ReactiveComponent {
            public int $count = 42;

            public function render(): string
            {
                return '<span>Count: ' . $this->count . '</span>';
            }
        };

        $component->setId('counter-123');
        $component->setName('counter');

        $html = $this->manager->render($component);

        $this->assertStringContainsString('reactive:state=', $html);
        $this->assertStringContainsString('42', $html);
    }

    public function test_render_includes_listeners_in_container(): void
    {
        $component = new class extends ReactiveComponent {
            public function mount(...$params): void
            {
                $this->listen('user-created', 'handleUserCreated');
            }

            public function handleUserCreated(array $data): void
            {
            }

            public function render(): string
            {
                return '<span>Component</span>';
            }
        };

        $component->setId('test-123');
        $component->setName('test');
        $component->mount();

        $html = $this->manager->render($component);

        $this->assertStringContainsString('reactive:listeners=', $html);
        $this->assertStringContainsString('user-created', $html);
    }

    public function test_render_includes_mount_params(): void
    {
        $component = new class extends ReactiveComponent {
            public function render(): string
            {
                return '<span>Component</span>';
            }
        };

        $component->setId('test-123');
        $component->setName('test');
        $component->setMountParams(['userId' => 42]);

        $html = $this->manager->render($component);

        $this->assertStringContainsString('reactive:params=', $html);
        $this->assertStringContainsString('userId', $html);
    }

    public function test_call_executes_method_on_registered_component(): void
    {
        $component = new class extends ReactiveComponent {
            public int $count = 0;

            public function increment(): void
            {
                $this->count++;
            }

            public function render(): string
            {
                return '';
            }
        };

        $component->setId('counter-123');
        $component->setName('counter');
        $this->registry->register('counter-123', $component);

        $result = $this->manager->call('counter-123', 'increment');

        $this->assertSame(1, $component->count);
    }

    public function test_call_passes_parameters_to_method(): void
    {
        $component = new class extends ReactiveComponent {
            public int $count = 0;

            public function add(int $amount): void
            {
                $this->count += $amount;
            }

            public function render(): string
            {
                return '';
            }
        };

        $component->setId('counter-123');
        $component->setName('counter');
        $this->registry->register('counter-123', $component);

        $result = $this->manager->call('counter-123', 'add', [10]);

        $this->assertSame(10, $component->count);
    }

    public function test_call_throws_exception_for_unregistered_component(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Component not found');

        $this->manager->call('nonexistent-id', 'method');
    }

    public function test_call_throws_exception_for_nonexistent_method(): void
    {
        $component = new class extends ReactiveComponent {
            public function render(): string
            {
                return '';
            }
        };

        $component->setId('test-123');
        $this->registry->register('test-123', $component);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Method not found');

        $this->manager->call('test-123', 'nonexistentMethod');
    }

    public function test_update_property_updates_component_property(): void
    {
        $component = new class extends ReactiveComponent {
            public int $count = 0;

            public function render(): string
            {
                return '';
            }
        };

        $component->setId('counter-123');
        $this->registry->register('counter-123', $component);

        $result = $this->manager->updateProperty('counter-123', 'count', 99);

        $this->assertSame(99, $component->count);
    }

    public function test_update_property_throws_exception_for_unregistered_component(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Component not found');

        $this->manager->updateProperty('nonexistent-id', 'property', 'value');
    }

    public function test_set_namespace_correctly_sets_namespace(): void
    {
        $this->manager->setNamespace('Custom\\Components');

        // Access private property via reflection to verify
        $reflection = new \ReflectionClass($this->manager);
        $property = $reflection->getProperty('namespace');

        $this->assertSame('Custom\\Components\\', $property->getValue($this->manager));
    }

    public function test_set_namespace_adds_trailing_backslash(): void
    {
        $this->manager->setNamespace('Custom\\Components\\');

        $reflection = new \ReflectionClass($this->manager);
        $property = $reflection->getProperty('namespace');

        $this->assertSame('Custom\\Components\\', $property->getValue($this->manager));
    }

    public function test_call_can_execute_multiple_methods_sequentially(): void
    {
        $component = new class extends ReactiveComponent {
            public int $count = 0;

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
                return '';
            }
        };

        $component->setId('counter-123');
        $this->registry->register('counter-123', $component);

        $this->manager->call('counter-123', 'increment');
        $this->manager->call('counter-123', 'increment');
        $result = $this->manager->call('counter-123', 'decrement');

        $this->assertSame(1, $component->count);
    }

    public function test_mount_throws_exception_for_nonexistent_component(): void
    {
        $this->expectException(ComponentNotFoundException::class);

        $this->manager->mount('nonexistent-component');
    }
}
