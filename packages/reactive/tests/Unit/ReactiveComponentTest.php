<?php

declare(strict_types=1);

namespace Lalaz\Reactive\Tests\Unit;

use Lalaz\Reactive\ReactiveComponent;
use Lalaz\Reactive\Tests\TestCase;

class ReactiveComponentTest extends TestCase
{
    public function test_can_set_and_get_id(): void
    {
        $component = new class extends ReactiveComponent {
            public function render(): string
            {
                return '';
            }
        };

        $component->setId('test-123');

        $this->assertSame('test-123', $component->getId());
    }

    public function test_can_set_and_get_name(): void
    {
        $component = new class extends ReactiveComponent {
            public function render(): string
            {
                return '';
            }
        };

        $component->setName('counter');

        $this->assertSame('counter', $component->getName());
    }

    public function test_can_set_and_get_mount_params(): void
    {
        $component = new class extends ReactiveComponent {
            public function render(): string
            {
                return '';
            }
        };

        $component->setMountParams(['userId' => 123]);

        $this->assertSame(['userId' => 123], $component->getMountParams());
    }

    public function test_get_public_properties_returns_public_properties(): void
    {
        $component = new class extends ReactiveComponent {
            public int $count = 0;
            public string $name = 'test';

            public function render(): string
            {
                return '';
            }
        };

        $properties = $component->getPublicProperties();

        $this->assertArrayHasKey('count', $properties);
        $this->assertArrayHasKey('name', $properties);
        $this->assertSame(0, $properties['count']);
        $this->assertSame('test', $properties['name']);
    }

    public function test_set_property_updates_public_property(): void
    {
        $component = new class extends ReactiveComponent {
            public int $count = 0;

            public function render(): string
            {
                return '';
            }
        };

        $component->setProperty('count', 42);

        $this->assertSame(42, $component->count);
    }

    public function test_dehydrate_returns_component_state(): void
    {
        $component = new class extends ReactiveComponent {
            public int $count = 5;

            public function render(): string
            {
                return '';
            }
        };

        $component->setId('counter-123');
        $component->setName('counter');
        $component->setMountParams(['initial' => 5]);

        $state = $component->dehydrate();

        $this->assertSame('counter-123', $state['id']);
        $this->assertSame('counter', $state['name']);
        $this->assertArrayHasKey('count', $state['properties']);
        $this->assertSame(5, $state['properties']['count']);
        $this->assertSame(['initial' => 5], $state['mount']);
    }

    public function test_hydrate_restores_component_state(): void
    {
        $component = new class extends ReactiveComponent {
            public int $count = 0;

            public function render(): string
            {
                return '';
            }
        };

        $component->hydrate([
            'id' => 'counter-456',
            'name' => 'counter',
            'mount' => ['initial' => 10],
            'properties' => ['count' => 42],
        ]);

        $this->assertSame('counter-456', $component->getId());
        $this->assertSame('counter', $component->getName());
        $this->assertSame(42, $component->count);
        $this->assertSame(['initial' => 10], $component->getMountParams());
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
    }

    public function test_notify_adds_notification(): void
    {
        $component = new class extends ReactiveComponent {
            public function showNotification(): void
            {
                $this->notify('Success!', 'success');
                $this->notify('Error!', 'error');
            }

            public function render(): string
            {
                return '';
            }
        };

        $component->showNotification();

        $notifications = $component->getNotifications();
        $this->assertCount(2, $notifications);
        $this->assertSame('Success!', $notifications[0]['message']);
        $this->assertSame('success', $notifications[0]['type']);
        $this->assertSame('Error!', $notifications[1]['message']);
        $this->assertSame('error', $notifications[1]['type']);
    }

    public function test_redirect_sets_redirect_url(): void
    {
        $component = new class extends ReactiveComponent {
            public function redirectToDashboard(): void
            {
                $this->redirect('/dashboard');
            }

            public function render(): string
            {
                return '';
            }
        };

        $component->redirectToDashboard();

        $this->assertSame('/dashboard', $component->getRedirect());
    }

    public function test_listen_registers_event_listener(): void
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
    }

    public function test_listens_to_checks_if_listening_to_event(): void
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
        $this->assertFalse($component->listensTo('user-deleted'));
    }
}
