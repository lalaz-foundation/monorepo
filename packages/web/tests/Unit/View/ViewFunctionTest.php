<?php

declare(strict_types=1);

namespace Lalaz\Web\Tests\Unit\View;

use Lalaz\Web\Tests\Common\WebUnitTestCase;
use Lalaz\Web\View\ViewFunction;

/**
 * Unit tests for ViewFunction.
 *
 * @covers \Lalaz\Web\View\ViewFunction
 */
class ViewFunctionTest extends WebUnitTestCase
{
    public function test_get_name_returns_function_name(): void
    {
        $fn = new ViewFunction('myFunction', fn () => 'result');

        $this->assertSame('myFunction', $fn->getName());
    }

    public function test_get_callable_returns_the_callable(): void
    {
        $callable = fn () => 'result';
        $fn = new ViewFunction('test', $callable);

        $this->assertSame($callable, $fn->getCallable());
    }

    public function test_get_options_returns_empty_array_by_default(): void
    {
        $fn = new ViewFunction('test', fn () => null);

        $this->assertSame([], $fn->getOptions());
    }

    public function test_get_options_returns_provided_options(): void
    {
        $options = ['is_safe' => ['html']];
        $fn = new ViewFunction('test', fn () => null, $options);

        $this->assertSame($options, $fn->getOptions());
    }

    public function test_callable_can_be_invoked(): void
    {
        $fn = new ViewFunction('greet', fn (string $name) => "Hello, {$name}!");

        $callable = $fn->getCallable();
        $result = $callable('World');

        $this->assertSame('Hello, World!', $result);
    }

    public function test_callable_with_multiple_parameters(): void
    {
        $fn = new ViewFunction('add', fn (int $a, int $b) => $a + $b);

        $callable = $fn->getCallable();
        $result = $callable(2, 3);

        $this->assertSame(5, $result);
    }

    public function test_options_can_contain_multiple_values(): void
    {
        $options = [
            'is_safe' => ['html', 'js'],
            'needs_context' => true,
            'needs_environment' => false,
        ];
        $fn = new ViewFunction('test', fn () => null, $options);

        $this->assertSame(['html', 'js'], $fn->getOptions()['is_safe']);
        $this->assertTrue($fn->getOptions()['needs_context']);
        $this->assertFalse($fn->getOptions()['needs_environment']);
    }
}
