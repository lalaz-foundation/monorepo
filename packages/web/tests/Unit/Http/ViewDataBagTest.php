<?php

declare(strict_types=1);

namespace Lalaz\Web\Tests\Unit\Http;

use Lalaz\Web\Tests\TestCase;
use Lalaz\Web\Http\ViewDataBag;
use Lalaz\Web\View\ErrorBag;

class ViewDataBagTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ViewDataBag::reset();

        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        ViewDataBag::reset();
        $_SESSION = [];
        parent::tearDown();
    }

    public function test_get_old_input_returns_null_for_non_existent_key(): void
    {
        $this->assertNull(ViewDataBag::getOldInput('email'));
    }

    public function test_get_old_input_returns_default_value_for_non_existent_key(): void
    {
        $this->assertSame('default@test.com', ViewDataBag::getOldInput('email', 'default@test.com'));
    }

    public function test_get_old_input_returns_all_input_when_key_is_null(): void
    {
        ViewDataBag::flashInput(['email' => 'test@test.com', 'name' => 'John']);
        ViewDataBag::reset();

        $all = ViewDataBag::getOldInput();
        $this->assertSame(['email' => 'test@test.com', 'name' => 'John'], $all);
    }

    public function test_get_errors_returns_empty_error_bag_when_no_errors(): void
    {
        $errors = ViewDataBag::getErrors();

        $this->assertInstanceOf(ErrorBag::class, $errors);
        $this->assertTrue($errors->isEmpty());
    }

    public function test_has_errors_returns_false_when_no_errors(): void
    {
        $this->assertFalse(ViewDataBag::hasErrors());
    }

    public function test_has_errors_with_field_returns_false_when_field_has_no_errors(): void
    {
        $this->assertFalse(ViewDataBag::hasErrors('email'));
    }

    public function test_get_first_error_returns_null_when_no_errors(): void
    {
        $this->assertNull(ViewDataBag::getFirstError('email'));
    }

    public function test_get_all_errors_returns_empty_array_when_no_errors(): void
    {
        $this->assertSame([], ViewDataBag::getAllErrors());
    }

    public function test_has_old_input_returns_false_when_no_input(): void
    {
        $this->assertFalse(ViewDataBag::hasOldInput());
    }

    public function test_has_old_input_with_key_returns_false_when_key_not_present(): void
    {
        $this->assertFalse(ViewDataBag::hasOldInput('email'));
    }

    public function test_old_helper_function_returns_value(): void
    {
        $this->assertTrue(function_exists('old'));

        ViewDataBag::flashInput(['email' => 'test@test.com']);
        ViewDataBag::reset();

        $this->assertSame('test@test.com', old('email'));
    }

    public function test_old_helper_function_returns_default_when_no_value(): void
    {
        $this->assertSame('default@test.com', old('email', 'default@test.com'));
    }

    public function test_errors_helper_function_returns_error_bag(): void
    {
        $this->assertTrue(function_exists('errors'));

        $errors = errors();
        $this->assertInstanceOf(ErrorBag::class, $errors);
    }

    public function test_flash_input_stores_input_in_session(): void
    {
        ViewDataBag::flashInput(['email' => 'test@test.com']);

        $this->assertSame(['email' => 'test@test.com'], $_SESSION['_old_input'] ?? null);
    }

    public function test_flash_errors_stores_errors_in_session(): void
    {
        ViewDataBag::flashErrors(['email' => ['required']]);

        $this->assertSame(['email' => ['required']], $_SESSION['_errors'] ?? null);
    }

    public function test_flash_errors_accepts_error_bag(): void
    {
        $bag = new ErrorBag();
        $bag->add('email', 'required');

        ViewDataBag::flashErrors($bag);

        $this->assertSame(['email' => ['required']], $_SESSION['_errors'] ?? null);
    }

    public function test_reset_clears_cached_values(): void
    {
        ViewDataBag::flashInput(['email' => 'test@test.com']);
        ViewDataBag::reset();

        // Read should get fresh from session
        $input = ViewDataBag::getOldInput();
        $this->assertSame(['email' => 'test@test.com'], $input);

        // Reset again
        ViewDataBag::reset();

        // Session was cleared by first read, so should be empty now
        $input = ViewDataBag::getOldInput();
        $this->assertSame([], $input);
    }
}
