<?php

declare(strict_types=1);

namespace Lalaz\Web\Tests\Unit\View;

use Lalaz\Web\Tests\Common\WebUnitTestCase;
use Lalaz\Web\View\ViewContext;

/**
 * Unit tests for ViewContext.
 *
 * @covers \Lalaz\Web\View\ViewContext
 */
class ViewContextTest extends WebUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ViewContext::reset();
    }

    protected function tearDown(): void
    {
        ViewContext::reset();
        parent::tearDown();
    }

    public function test_set_and_get_value(): void
    {
        ViewContext::set('title', 'Test Page');

        $this->assertSame('Test Page', ViewContext::get('title'));
    }

    public function test_get_returns_default_for_missing_key(): void
    {
        $this->assertNull(ViewContext::get('missing'));
        $this->assertSame('default', ViewContext::get('missing', 'default'));
    }

    public function test_set_overwrites_existing_value(): void
    {
        ViewContext::set('key', 'original');
        ViewContext::set('key', 'updated');

        $this->assertSame('updated', ViewContext::get('key'));
    }

    public function test_callable_values_are_resolved_on_get(): void
    {
        $counter = 0;
        ViewContext::set('count', function () use (&$counter) {
            return ++$counter;
        });

        $value1 = ViewContext::get('count');
        $value2 = ViewContext::get('count');

        // Each get() call invokes the callable
        $this->assertSame(1, $value1);
        $this->assertSame(2, $value2);
    }

    public function test_resolved_returns_all_values(): void
    {
        ViewContext::set('name', 'John');
        ViewContext::set('age', 30);

        $resolved = ViewContext::resolved();

        $this->assertArrayHasKey('name', $resolved);
        $this->assertArrayHasKey('age', $resolved);
    }

    public function test_resolved_wraps_callables_in_lazy_objects(): void
    {
        if (!class_exists('\Lalaz\Support\LazyObject')) {
            $this->markTestSkipped('LazyObject class not available in this package');
        }

        ViewContext::set('lazy', fn () => 'computed value');

        $resolved = ViewContext::resolved();

        // Should contain the callable wrapped, not immediately resolved
        $this->assertArrayHasKey('lazy', $resolved);
    }

    public function test_reset_clears_all_data(): void
    {
        ViewContext::set('key1', 'value1');
        ViewContext::set('key2', 'value2');

        ViewContext::reset();

        $this->assertNull(ViewContext::get('key1'));
        $this->assertNull(ViewContext::get('key2'));
    }

    public function test_resolved_returns_empty_array_after_reset(): void
    {
        ViewContext::set('key', 'value');
        ViewContext::reset();

        $resolved = ViewContext::resolved();

        $this->assertEmpty($resolved);
    }

    public function test_can_store_various_types(): void
    {
        ViewContext::set('string', 'text');
        ViewContext::set('int', 42);
        ViewContext::set('float', 3.14);
        ViewContext::set('bool', true);
        ViewContext::set('array', ['a', 'b']);
        ViewContext::set('null', null);
        ViewContext::set('object', new \stdClass());

        $this->assertSame('text', ViewContext::get('string'));
        $this->assertSame(42, ViewContext::get('int'));
        $this->assertSame(3.14, ViewContext::get('float'));
        $this->assertTrue(ViewContext::get('bool'));
        $this->assertSame(['a', 'b'], ViewContext::get('array'));
        $this->assertNull(ViewContext::get('null'));
        $this->assertInstanceOf(\stdClass::class, ViewContext::get('object'));
    }
}
