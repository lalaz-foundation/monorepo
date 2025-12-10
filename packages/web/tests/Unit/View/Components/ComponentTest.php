<?php

declare(strict_types=1);

namespace Lalaz\Web\Tests\Unit\View\Components;

use Lalaz\Web\Tests\Common\WebUnitTestCase;
use Lalaz\Web\View\Components\Component;

/**
 * Test component for unit tests.
 */
class TestComponent extends Component
{
    public string $message = 'default';
    public int $count = 0;

    public function __construct(string $message = 'default', int $count = 0)
    {
        $this->message = $message;
        $this->count = $count;
    }

    public function data(): array
    {
        return [
            'uppercase' => strtoupper($this->message),
            'doubled' => $this->count * 2,
        ];
    }
}

/**
 * Test component with custom template.
 */
class CustomTemplateComponent extends Component
{
    public function template(): ?string
    {
        return 'custom/path/to/template';
    }
}

/**
 * Test component that conditionally renders.
 */
class ConditionalComponent extends Component
{
    public bool $shouldShow = true;

    public function shouldRender(): bool
    {
        return $this->shouldShow;
    }
}

/**
 * Unit tests for Component base class.
 *
 * @covers \Lalaz\Web\View\Components\Component
 */
class ComponentTest extends WebUnitTestCase
{
    public function test_template_returns_null_by_default(): void
    {
        $component = new TestComponent();

        $this->assertNull($component->template());
    }

    public function test_template_can_be_overridden(): void
    {
        $component = new CustomTemplateComponent();

        $this->assertSame('custom/path/to/template', $component->template());
    }

    public function test_data_returns_empty_array_by_default(): void
    {
        $component = new class extends Component {};

        $this->assertSame([], $component->data());
    }

    public function test_data_can_return_computed_values(): void
    {
        $component = new TestComponent('hello', 5);

        $data = $component->data();

        $this->assertSame('HELLO', $data['uppercase']);
        $this->assertSame(10, $data['doubled']);
    }

    public function test_should_render_returns_true_by_default(): void
    {
        $component = new TestComponent();

        $this->assertTrue($component->shouldRender());
    }

    public function test_should_render_can_be_overridden(): void
    {
        $showComponent = new ConditionalComponent();
        $showComponent->shouldShow = true;

        $hideComponent = new ConditionalComponent();
        $hideComponent->shouldShow = false;

        $this->assertTrue($showComponent->shouldRender());
        $this->assertFalse($hideComponent->shouldRender());
    }

    public function test_get_public_properties_returns_public_properties(): void
    {
        $component = new TestComponent('test', 42);

        $properties = $component->getPublicProperties();

        $this->assertArrayHasKey('message', $properties);
        $this->assertArrayHasKey('count', $properties);
        $this->assertSame('test', $properties['message']);
        $this->assertSame(42, $properties['count']);
    }

    public function test_get_public_properties_excludes_static_properties(): void
    {
        $component = new class extends Component {
            public string $instance = 'value';
            public static string $staticProp = 'static';
        };

        $properties = $component->getPublicProperties();

        $this->assertArrayHasKey('instance', $properties);
        $this->assertArrayNotHasKey('staticProp', $properties);
    }

    public function test_resolve_data_merges_properties_and_computed_data(): void
    {
        $component = new TestComponent('hello', 5);

        $data = $component->resolveData();

        // From properties
        $this->assertSame('hello', $data['message']);
        $this->assertSame(5, $data['count']);

        // From data() method
        $this->assertSame('HELLO', $data['uppercase']);
        $this->assertSame(10, $data['doubled']);
    }

    public function test_resolve_data_computed_overrides_properties(): void
    {
        $component = new class extends Component {
            public string $value = 'original';

            public function data(): array
            {
                return ['value' => 'overridden'];
            }
        };

        $data = $component->resolveData();

        $this->assertSame('overridden', $data['value']);
    }
}
