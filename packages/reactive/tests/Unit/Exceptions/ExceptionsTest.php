<?php

declare(strict_types=1);

namespace Lalaz\Reactive\Tests\Unit\Exceptions;

use Lalaz\Reactive\Exceptions\ComponentNotFoundException;
use Lalaz\Reactive\Exceptions\InvalidRequestException;
use Lalaz\Reactive\Exceptions\MethodNotAccessibleException;
use Lalaz\Reactive\Exceptions\PropertyNotAccessibleException;
use Lalaz\Reactive\Exceptions\ReactiveException;
use Lalaz\Reactive\Tests\TestCase;

class ExceptionsTest extends TestCase
{
    public function test_reactive_exception_is_base_exception(): void
    {
        $exception = new ReactiveException('Test error');

        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertSame('Test error', $exception->getMessage());
    }

    public function test_component_not_found_exception_includes_component_name(): void
    {
        $exception = new ComponentNotFoundException('Counter');

        $this->assertInstanceOf(ReactiveException::class, $exception);
        $this->assertStringContainsString('Counter', $exception->getMessage());
    }

    public function test_invalid_request_exception(): void
    {
        $exception = new InvalidRequestException('Invalid data');

        $this->assertInstanceOf(ReactiveException::class, $exception);
        $this->assertSame('Invalid data', $exception->getMessage());
    }

    public function test_method_not_accessible_exception_includes_method_name(): void
    {
        $exception = new MethodNotAccessibleException('privateMethod');

        $this->assertInstanceOf(ReactiveException::class, $exception);
        $this->assertStringContainsString('privateMethod', $exception->getMessage());
    }

    public function test_property_not_accessible_exception_includes_property_name(): void
    {
        $exception = new PropertyNotAccessibleException('privateProperty');

        $this->assertInstanceOf(ReactiveException::class, $exception);
        $this->assertStringContainsString('privateProperty', $exception->getMessage());
    }
}
