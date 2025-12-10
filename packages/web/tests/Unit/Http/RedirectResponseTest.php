<?php

declare(strict_types=1);

namespace Lalaz\Web\Tests\Unit\Http;

use Lalaz\Web\Tests\TestCase;
use Lalaz\Web\Http\RedirectResponse;
use Lalaz\Web\View\ErrorBag;

class RedirectResponseTest extends TestCase
{
    public function test_creates_redirect_response_with_url(): void
    {
        $response = new RedirectResponse('/dashboard');

        $this->assertSame('/dashboard', $response->getUrl());
        $this->assertSame(302, $response->getStatus());
    }

    public function test_creates_redirect_response_with_custom_status(): void
    {
        $response = new RedirectResponse('/new-location', 301);

        $this->assertSame(301, $response->getStatus());
    }

    public function test_status_method_changes_status_code_fluently(): void
    {
        $response = new RedirectResponse('/dashboard');
        $result = $response->status(301);

        $this->assertSame($response, $result);
        $this->assertSame(301, $response->getStatus());
    }

    public function test_with_adds_flash_message_fluently(): void
    {
        $response = new RedirectResponse('/dashboard');
        $result = $response->with('success', 'Profile updated!');

        $this->assertSame($response, $result);
        $this->assertSame(['success' => 'Profile updated!'], $response->getMessages());
    }

    public function test_with_can_add_multiple_messages(): void
    {
        $response = new RedirectResponse('/dashboard');
        $response->with('success', 'Done!')
            ->with('info', 'Check your email');

        $this->assertSame([
            'success' => 'Done!',
            'info' => 'Check your email',
        ], $response->getMessages());
    }

    public function test_with_errors_adds_validation_errors_from_array(): void
    {
        $response = new RedirectResponse('/users/create');
        $result = $response->withErrors([
            'email' => 'Email is required',
            'password' => ['Too short', 'Must contain number'],
        ]);

        $this->assertSame($response, $result);
        $this->assertSame([
            'email' => ['Email is required'],
            'password' => ['Too short', 'Must contain number'],
        ], $response->getErrors());
    }

    public function test_with_errors_adds_validation_errors_from_error_bag(): void
    {
        $bag = new ErrorBag();
        $bag->add('email', 'required');
        $bag->add('email', 'invalid');

        $response = new RedirectResponse('/users/create');
        $response->withErrors($bag);

        $this->assertSame([
            'email' => ['required', 'invalid'],
        ], $response->getErrors());
    }

    public function test_with_input_marks_all_input_to_be_flashed(): void
    {
        $response = new RedirectResponse('/users/create');
        $result = $response->withInput();

        $this->assertSame($response, $result);
        $this->assertTrue($response->hasFlashData());
    }

    public function test_with_input_with_specific_keys_marks_only_those_keys(): void
    {
        $response = new RedirectResponse('/users/create');
        $result = $response->withInput(['email', 'name']);

        $this->assertSame($response, $result);
        $this->assertTrue($response->hasFlashData());
    }

    public function test_with_input_except_marks_all_input_except_specified_keys(): void
    {
        $response = new RedirectResponse('/users/create');
        $result = $response->withInputExcept(['password']);

        $this->assertSame($response, $result);
        $this->assertTrue($response->hasFlashData());
    }

    public function test_has_flash_data_returns_true_when_errors_present(): void
    {
        $response = new RedirectResponse('/dashboard');
        $response->withErrors(['email' => 'required']);

        $this->assertTrue($response->hasFlashData());
    }

    public function test_has_flash_data_returns_true_when_messages_present(): void
    {
        $response = new RedirectResponse('/dashboard');
        $response->with('success', 'Done!');

        $this->assertTrue($response->hasFlashData());
    }

    public function test_has_flash_data_returns_false_when_no_flash_data(): void
    {
        $response = new RedirectResponse('/dashboard');

        $this->assertFalse($response->hasFlashData());
    }

    public function test_redirect_helper_creates_redirect_response(): void
    {
        $this->assertTrue(function_exists('redirect'));

        $response = redirect('/dashboard');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/dashboard', $response->getUrl());
    }

    public function test_back_helper_creates_redirect_response_with_referer(): void
    {
        $this->assertTrue(function_exists('back'));

        // Without referer, uses fallback
        $response = back('/fallback');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/fallback', $response->getUrl());
    }

    public function test_chaining_multiple_methods_works_correctly(): void
    {
        $response = redirect('/users/create')
            ->withInput(['email', 'name'])
            ->withErrors(['email' => 'Invalid email'])
            ->with('warning', 'Please check your input')
            ->status(302);

        $this->assertSame('/users/create', $response->getUrl());
        $this->assertSame(302, $response->getStatus());
        $this->assertSame(['email' => ['Invalid email']], $response->getErrors());
        $this->assertSame(['warning' => 'Please check your input'], $response->getMessages());
        $this->assertTrue($response->hasFlashData());
    }
}
