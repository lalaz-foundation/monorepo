<?php

declare(strict_types=1);

namespace Lalaz\Reactive\Tests\Unit;

use Lalaz\Reactive\ComponentRegistry;
use Lalaz\Reactive\ReactiveComponent;
use Lalaz\Reactive\Tests\TestCase;

class ComponentRegistryTest extends TestCase
{
    private ComponentRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new ComponentRegistry();
    }

    public function test_can_register_component(): void
    {
        $component = $this->createMock(ReactiveComponent::class);

        $this->registry->register('test-123', $component);

        $this->assertTrue($this->registry->has('test-123'));
    }

    public function test_can_get_registered_component(): void
    {
        $component = $this->createMock(ReactiveComponent::class);
        $this->registry->register('test-123', $component);

        $result = $this->registry->get('test-123');

        $this->assertSame($component, $result);
    }

    public function test_returns_null_for_unregistered_component(): void
    {
        $result = $this->registry->get('nonexistent');

        $this->assertNull($result);
    }

    public function test_can_check_if_component_exists(): void
    {
        $component = $this->createMock(ReactiveComponent::class);
        $this->registry->register('test-123', $component);

        $this->assertTrue($this->registry->has('test-123'));
        $this->assertFalse($this->registry->has('nonexistent'));
    }

    public function test_can_remove_component(): void
    {
        $component = $this->createMock(ReactiveComponent::class);
        $this->registry->register('test-123', $component);

        $this->registry->remove('test-123');

        $this->assertFalse($this->registry->has('test-123'));
    }

    public function test_can_get_all_components(): void
    {
        $component1 = $this->createMock(ReactiveComponent::class);
        $component2 = $this->createMock(ReactiveComponent::class);

        $this->registry->register('test-1', $component1);
        $this->registry->register('test-2', $component2);

        $all = $this->registry->all();

        $this->assertCount(2, $all);
        $this->assertArrayHasKey('test-1', $all);
        $this->assertArrayHasKey('test-2', $all);
    }

    public function test_can_clear_all_components(): void
    {
        $component1 = $this->createMock(ReactiveComponent::class);
        $component2 = $this->createMock(ReactiveComponent::class);

        $this->registry->register('test-1', $component1);
        $this->registry->register('test-2', $component2);

        $this->registry->clear();

        $this->assertEmpty($this->registry->all());
    }
}
