<?php

declare(strict_types=1);

namespace Lalaz\Web\Tests\Unit\View;

use Lalaz\Web\Tests\TestCase;
use Lalaz\Web\View\ErrorBag;

class ErrorBagTest extends TestCase
{
    public function test_creates_empty_error_bag(): void
    {
        $bag = new ErrorBag();

        $this->assertTrue($bag->isEmpty());
        $this->assertFalse($bag->isNotEmpty());
        $this->assertSame(0, $bag->count());
    }

    public function test_creates_error_bag_with_initial_errors(): void
    {
        $errors = ['email' => ['required', 'invalid format']];
        $bag = new ErrorBag($errors);

        $this->assertFalse($bag->isEmpty());
        $this->assertTrue($bag->has('email'));
    }

    public function test_adds_error_to_field(): void
    {
        $bag = new ErrorBag();
        $result = $bag->add('email', 'Email is required');

        $this->assertSame($bag, $result);
        $this->assertTrue($bag->has('email'));
        $this->assertSame(['Email is required'], $bag->get('email'));
    }

    public function test_adds_multiple_errors_to_same_field(): void
    {
        $bag = new ErrorBag();
        $bag->add('email', 'Email is required');
        $bag->add('email', 'Email must be valid');

        $this->assertSame(['Email is required', 'Email must be valid'], $bag->get('email'));
        $this->assertSame(2, $bag->count());
    }

    public function test_has_checks_for_any_errors_when_field_is_null(): void
    {
        $bag = new ErrorBag();

        $this->assertFalse($bag->has());

        $bag->add('email', 'required');
        $this->assertTrue($bag->has());
    }

    public function test_has_checks_for_specific_field(): void
    {
        $bag = new ErrorBag();
        $bag->add('email', 'required');

        $this->assertTrue($bag->has('email'));
        $this->assertFalse($bag->has('password'));
    }

    public function test_get_returns_empty_array_for_non_existent_field(): void
    {
        $bag = new ErrorBag();

        $this->assertSame([], $bag->get('email'));
    }

    public function test_first_returns_first_error_for_field(): void
    {
        $bag = new ErrorBag();
        $bag->add('email', 'first error');
        $bag->add('email', 'second error');

        $this->assertSame('first error', $bag->first('email'));
    }

    public function test_first_returns_null_for_non_existent_field(): void
    {
        $bag = new ErrorBag();

        $this->assertNull($bag->first('email'));
    }

    public function test_first_returns_first_error_of_any_field_when_no_field_specified(): void
    {
        $bag = new ErrorBag();
        $bag->add('email', 'email error');
        $bag->add('password', 'password error');

        $this->assertSame('email error', $bag->first());
    }

    public function test_first_returns_null_when_bag_is_empty(): void
    {
        $bag = new ErrorBag();

        $this->assertNull($bag->first());
    }

    public function test_all_returns_flat_array_of_all_messages(): void
    {
        $bag = new ErrorBag();
        $bag->add('email', 'email error 1');
        $bag->add('email', 'email error 2');
        $bag->add('password', 'password error');

        $this->assertSame(['email error 1', 'email error 2', 'password error'], $bag->all());
    }

    public function test_to_array_returns_errors_indexed_by_field(): void
    {
        $bag = new ErrorBag();
        $bag->add('email', 'error 1');
        $bag->add('password', 'error 2');

        $this->assertSame([
            'email' => ['error 1'],
            'password' => ['error 2'],
        ], $bag->toArray());
    }

    public function test_count_returns_total_number_of_messages(): void
    {
        $bag = new ErrorBag();
        $bag->add('email', 'error 1');
        $bag->add('email', 'error 2');
        $bag->add('password', 'error 3');

        $this->assertSame(3, $bag->count());
    }

    public function test_keys_returns_all_field_names_with_errors(): void
    {
        $bag = new ErrorBag();
        $bag->add('email', 'error');
        $bag->add('password', 'error');
        $bag->add('name', 'error');

        $this->assertSame(['email', 'password', 'name'], $bag->keys());
    }

    public function test_merge_combines_two_error_bags(): void
    {
        $bag1 = new ErrorBag();
        $bag1->add('email', 'error 1');

        $bag2 = new ErrorBag();
        $bag2->add('password', 'error 2');

        $bag1->merge($bag2);

        $this->assertTrue($bag1->has('email'));
        $this->assertTrue($bag1->has('password'));
        $this->assertSame(2, $bag1->count());
    }

    public function test_from_array_creates_bag_from_flat_array(): void
    {
        $errors = [
            'email' => 'Email is required',
            'password' => 'Password is required',
        ];

        $bag = ErrorBag::fromArray($errors);

        $this->assertSame('Email is required', $bag->first('email'));
        $this->assertSame('Password is required', $bag->first('password'));
    }

    public function test_from_array_creates_bag_from_nested_array(): void
    {
        $errors = [
            'email' => ['required', 'invalid format'],
            'password' => ['too short'],
        ];

        $bag = ErrorBag::fromArray($errors);

        $this->assertSame(['required', 'invalid format'], $bag->get('email'));
        $this->assertSame(['too short'], $bag->get('password'));
    }

    public function test_empty_factory_creates_empty_bag(): void
    {
        $bag = ErrorBag::empty();

        $this->assertInstanceOf(ErrorBag::class, $bag);
        $this->assertTrue($bag->isEmpty());
    }
}
