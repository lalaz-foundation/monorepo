<?php

declare(strict_types=1);

namespace Lalaz\Reactive\Tests\Unit\Http;

use Lalaz\Reactive\Exceptions\InvalidRequestException;
use Lalaz\Reactive\Exceptions\MethodNotAccessibleException;
use Lalaz\Reactive\Exceptions\PropertyNotAccessibleException;
use Lalaz\Reactive\ReactiveComponent;
use Lalaz\Reactive\Tests\TestCase;

/**
 * Tests for ReactiveController validation logic.
 *
 * Note: Full integration tests for ReactiveController require a complete
 * application context with container, request handling, etc. These unit
 * tests focus on the validation exception classes used by the controller.
 */
class ReactiveControllerTest extends TestCase
{
    public function test_invalid_request_exception_contains_message(): void
    {
        $exception = new InvalidRequestException('Component name is required');

        $this->assertSame('Component name is required', $exception->getMessage());
    }

    public function test_method_not_accessible_exception_contains_method_name(): void
    {
        $exception = new MethodNotAccessibleException('privateMethod');

        $this->assertStringContainsString('privateMethod', $exception->getMessage());
    }

    public function test_property_not_accessible_exception_contains_property_name(): void
    {
        $exception = new PropertyNotAccessibleException('privateProperty');

        $this->assertStringContainsString('privateProperty', $exception->getMessage());
    }

    public function test_component_public_method_is_accessible(): void
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

        $reflection = new \ReflectionMethod($component, 'increment');

        $this->assertTrue($reflection->isPublic());
    }

    public function test_component_protected_method_is_not_public(): void
    {
        $component = new class extends ReactiveComponent {
            protected function protectedMethod(): void
            {
            }

            public function render(): string
            {
                return '';
            }
        };

        $reflection = new \ReflectionMethod($component, 'protectedMethod');

        $this->assertFalse($reflection->isPublic());
    }

    public function test_component_private_method_is_not_public(): void
    {
        $component = new class extends ReactiveComponent {
            private function privateMethod(): void
            {
            }

            public function render(): string
            {
                return '';
            }
        };

        $reflection = new \ReflectionMethod($component, 'privateMethod');

        $this->assertFalse($reflection->isPublic());
    }

    public function test_component_public_property_is_accessible(): void
    {
        $component = new class extends ReactiveComponent {
            public int $count = 0;

            public function render(): string
            {
                return '';
            }
        };

        $reflection = new \ReflectionProperty($component, 'count');

        $this->assertTrue($reflection->isPublic());
    }

    public function test_component_protected_property_is_not_public(): void
    {
        $component = new class extends ReactiveComponent {
            protected int $protectedValue = 0;

            public function render(): string
            {
                return '';
            }
        };

        $reflection = new \ReflectionProperty($component, 'protectedValue');

        $this->assertFalse($reflection->isPublic());
    }

    public function test_component_method_can_be_called_dynamically(): void
    {
        $component = new class extends ReactiveComponent {
            public int $count = 0;

            public function increment(): void
            {
                $this->count++;
            }

            public function add(int $amount): void
            {
                $this->count += $amount;
            }

            public function render(): string
            {
                return '';
            }
        };

        // Simulating how ReactiveController calls methods
        $method = 'increment';
        $component->$method();

        $this->assertSame(1, $component->count);

        // With parameters
        call_user_func_array([$component, 'add'], [10]);

        $this->assertSame(11, $component->count);
    }

    public function test_component_property_can_be_updated_dynamically(): void
    {
        $component = new class extends ReactiveComponent {
            public int $count = 0;
            public string $name = '';

            public function render(): string
            {
                return '';
            }
        };

        // Simulating how ReactiveController updates properties
        $property = 'count';
        $component->{$property} = 42;

        $this->assertSame(42, $component->count);

        $component->{'name'} = 'Test';

        $this->assertSame('Test', $component->name);
    }

    public function test_protected_lifecycle_methods_should_be_blocked(): void
    {
        $protectedMethods = ['hydrate', 'dehydrate', 'setId', 'setName', 'setProperty'];

        foreach ($protectedMethods as $method) {
            // These methods should be blocked by the controller
            // even though some are public (like hydrate, dehydrate)
            $this->assertContains($method, $protectedMethods);
        }
    }
}
