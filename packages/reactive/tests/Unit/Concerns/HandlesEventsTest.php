<?php

declare(strict_types=1);

namespace Lalaz\Reactive\Tests\Unit\Concerns;

use Lalaz\Reactive\ReactiveComponent;
use Lalaz\Reactive\Tests\TestCase;

class HandlesEventsTest extends TestCase
{
    public function test_listen_registers_event_with_method_handler(): void
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
                return '';
            }
        };

        $component->mount();

        $listeners = $component->getListeners();
        $this->assertArrayHasKey('user-created', $listeners);
        $this->assertSame('handleUserCreated', $listeners['user-created']);
    }

    public function test_on_is_alias_for_listen(): void
    {
        $component = new class extends ReactiveComponent {
            public function mount(...$params): void
            {
                $this->on('item-added', 'handleItemAdded');
            }

            public function handleItemAdded(array $data): void
            {
            }

            public function render(): string
            {
                return '';
            }
        };

        $component->mount();

        $listeners = $component->getListeners();
        $this->assertArrayHasKey('item-added', $listeners);
        $this->assertSame('handleItemAdded', $listeners['item-added']);
    }

    public function test_listens_to_returns_true_for_registered_event(): void
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
                return '';
            }
        };

        $component->mount();

        $this->assertTrue($component->listensTo('user-created'));
    }

    public function test_listens_to_returns_false_for_unregistered_event(): void
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
                return '';
            }
        };

        $component->mount();

        $this->assertFalse($component->listensTo('user-deleted'));
    }

    public function test_can_register_multiple_listeners(): void
    {
        $component = new class extends ReactiveComponent {
            public function mount(...$params): void
            {
                $this->listen('event-one', 'handleOne');
                $this->listen('event-two', 'handleTwo');
                $this->listen('event-three', 'handleThree');
            }

            public function handleOne(array $data): void
            {
            }

            public function handleTwo(array $data): void
            {
            }

            public function handleThree(array $data): void
            {
            }

            public function render(): string
            {
                return '';
            }
        };

        $component->mount();

        $listeners = $component->getListeners();
        $this->assertCount(3, $listeners);
        $this->assertArrayHasKey('event-one', $listeners);
        $this->assertArrayHasKey('event-two', $listeners);
        $this->assertArrayHasKey('event-three', $listeners);
    }

    public function test_dispatch_queues_event(): void
    {
        $component = new class extends ReactiveComponent {
            public function triggerEvent(): void
            {
                $this->dispatch('user-created', ['id' => 123]);
            }

            public function render(): string
            {
                return '';
            }
        };

        $component->triggerEvent();

        $queue = $component->getDispatchQueue();
        $this->assertCount(1, $queue);
        $this->assertSame('user-created', $queue[0]['event']);
        $this->assertSame(['id' => 123], $queue[0]['data']);
    }

    public function test_dispatch_without_data(): void
    {
        $component = new class extends ReactiveComponent {
            public function triggerEvent(): void
            {
                $this->dispatch('simple-event');
            }

            public function render(): string
            {
                return '';
            }
        };

        $component->triggerEvent();

        $queue = $component->getDispatchQueue();
        $this->assertCount(1, $queue);
        $this->assertSame('simple-event', $queue[0]['event']);
        $this->assertSame([], $queue[0]['data']);
    }

    public function test_emit_is_alias_for_dispatch(): void
    {
        $component = new class extends ReactiveComponent {
            public function triggerEvent(): void
            {
                $this->emit('item-added', ['name' => 'test']);
            }

            public function render(): string
            {
                return '';
            }
        };

        $component->triggerEvent();

        $queue = $component->getDispatchQueue();
        $this->assertCount(1, $queue);
        $this->assertSame('item-added', $queue[0]['event']);
        $this->assertSame(['name' => 'test'], $queue[0]['data']);
    }

    public function test_can_dispatch_multiple_events(): void
    {
        $component = new class extends ReactiveComponent {
            public function triggerEvents(): void
            {
                $this->dispatch('event-one', ['id' => 1]);
                $this->dispatch('event-two', ['id' => 2]);
                $this->dispatch('event-three', ['id' => 3]);
            }

            public function render(): string
            {
                return '';
            }
        };

        $component->triggerEvents();

        $queue = $component->getDispatchQueue();
        $this->assertCount(3, $queue);
    }

    public function test_dehydrate_includes_listeners(): void
    {
        $component = new class extends ReactiveComponent {
            public function mount(...$params): void
            {
                $this->listen('user-created', 'handleUserCreated');
                $this->listen('user-deleted', 'handleUserDeleted');
            }

            public function handleUserCreated(array $data): void
            {
            }

            public function handleUserDeleted(array $data): void
            {
            }

            public function render(): string
            {
                return '';
            }
        };

        $component->setId('test-123');
        $component->setName('test');
        $component->mount();

        $state = $component->dehydrate();

        $this->assertArrayHasKey('listeners', $state);
        $this->assertContains('user-created', $state['listeners']);
        $this->assertContains('user-deleted', $state['listeners']);
    }

    public function test_dehydrate_includes_dispatch_queue(): void
    {
        $component = new class extends ReactiveComponent {
            public function triggerEvent(): void
            {
                $this->dispatch('event-fired', ['value' => 42]);
            }

            public function render(): string
            {
                return '';
            }
        };

        $component->setId('test-123');
        $component->setName('test');
        $component->triggerEvent();

        $state = $component->dehydrate();

        $this->assertArrayHasKey('dispatches', $state);
        $this->assertCount(1, $state['dispatches']);
        $this->assertSame('event-fired', $state['dispatches'][0]['event']);
    }
}
