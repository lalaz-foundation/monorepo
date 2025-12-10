<?php

declare(strict_types=1);

namespace Lalaz\Web\Tests\Unit\View;

use Lalaz\Web\Tests\TestCase;
use Lalaz\Web\View\ViewResponse;

class ViewResponseTest extends TestCase
{
    public function test_creates_view_response_with_template(): void
    {
        $viewResponse = new ViewResponse('pages/home');

        $this->assertSame('pages/home', $viewResponse->template());
        $this->assertSame([], $viewResponse->data());
        $this->assertSame(200, $viewResponse->statusCode());
    }

    public function test_creates_view_response_with_data(): void
    {
        $data = ['title' => 'Welcome', 'user' => 'John'];
        $viewResponse = new ViewResponse('pages/home', $data);

        $this->assertSame($data, $viewResponse->data());
    }

    public function test_creates_view_response_with_custom_status_code(): void
    {
        $viewResponse = new ViewResponse('errors/not-found', [], 404);

        $this->assertSame(404, $viewResponse->statusCode());
    }

    public function test_static_create_factory_method_works(): void
    {
        $viewResponse = ViewResponse::create('pages/about', ['key' => 'value'], 201);

        $this->assertSame('pages/about', $viewResponse->template());
        $this->assertSame(['key' => 'value'], $viewResponse->data());
        $this->assertSame(201, $viewResponse->statusCode());
    }

    public function test_can_set_status_fluently(): void
    {
        $viewResponse = new ViewResponse('pages/home');
        $result = $viewResponse->status(404);

        $this->assertSame($viewResponse, $result);
        $this->assertSame(404, $viewResponse->statusCode());
    }

    public function test_can_add_header_fluently(): void
    {
        $viewResponse = new ViewResponse('pages/home');
        $result = $viewResponse->header('X-Custom', 'value');

        $this->assertSame($viewResponse, $result);
    }

    public function test_can_add_multiple_headers_fluently(): void
    {
        $viewResponse = new ViewResponse('pages/home');
        $result = $viewResponse->withHeaders([
            'X-First' => 'one',
            'X-Second' => 'two',
        ]);

        $this->assertSame($viewResponse, $result);
    }

    public function test_can_add_data_with_key_value_fluently(): void
    {
        $viewResponse = new ViewResponse('pages/home', ['name' => 'John']);
        $result = $viewResponse->with('age', 30);

        $this->assertSame($viewResponse, $result);
        $this->assertSame(['name' => 'John', 'age' => 30], $viewResponse->data());
    }

    public function test_can_add_data_with_array_fluently(): void
    {
        $viewResponse = new ViewResponse('pages/home', ['name' => 'John']);
        $result = $viewResponse->with(['age' => 30, 'city' => 'NYC']);

        $this->assertSame($viewResponse, $result);
        $this->assertSame(['name' => 'John', 'age' => 30, 'city' => 'NYC'], $viewResponse->data());
    }

    public function test_can_set_layout_fluently(): void
    {
        $viewResponse = new ViewResponse('pages/home');
        $result = $viewResponse->layout('layouts/main');

        $this->assertSame($viewResponse, $result);
    }

    public function test_can_disable_layout_fluently(): void
    {
        $viewResponse = new ViewResponse('pages/home');
        $result = $viewResponse->withoutLayout();

        $this->assertSame($viewResponse, $result);
    }

    public function test_view_helper_function_creates_view_response(): void
    {
        $this->assertTrue(function_exists('view'));

        $viewResponse = view('pages/home', ['title' => 'Test'], 201);

        $this->assertInstanceOf(ViewResponse::class, $viewResponse);
        $this->assertSame('pages/home', $viewResponse->template());
        $this->assertSame(['title' => 'Test'], $viewResponse->data());
        $this->assertSame(201, $viewResponse->statusCode());
    }

    public function test_partial_helper_function_creates_view_response(): void
    {
        $this->assertTrue(function_exists('partial'));

        $viewResponse = partial('components/card', ['item' => 'Test']);

        $this->assertInstanceOf(ViewResponse::class, $viewResponse);
        $this->assertSame('components/card', $viewResponse->template());
    }
}
