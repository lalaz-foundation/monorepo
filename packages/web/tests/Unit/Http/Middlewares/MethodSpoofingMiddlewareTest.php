<?php

declare(strict_types=1);

namespace Lalaz\Web\Tests\Unit\Http\Middlewares;

use Lalaz\Web\Tests\TestCase;
use Lalaz\Web\Http\Middlewares\MethodSpoofingMiddleware;
use Lalaz\Web\Http\Request;
use Lalaz\Web\Http\Response;

class MethodSpoofingMiddlewareTest extends TestCase
{
    private MethodSpoofingMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new MethodSpoofingMiddleware();
    }

    public function test_does_not_modify_get_requests(): void
    {
        $request = $this->createMockRequest('GET', []);
        $response = new Response('');
        $nextCalled = false;

        $this->middleware->handle($request, $response, function ($req, $res) use (&$nextCalled) {
            $nextCalled = true;
            return $res;
        });

        $this->assertTrue($nextCalled);
        $this->assertSame('GET', $request->method());
    }

    public function test_does_not_modify_post_without_method_field(): void
    {
        $request = $this->createMockRequest('POST', ['name' => 'John']);
        $response = new Response('');

        $this->middleware->handle($request, $response, function ($req, $res) {
            return $res;
        });

        $this->assertSame('POST', $request->method());
    }

    public function test_converts_post_to_delete_when_method_is_delete(): void
    {
        $request = $this->createMockRequest('POST', ['_method' => 'DELETE']);
        $response = new Response('');

        $this->middleware->handle($request, $response, function ($req, $res) {
            return $res;
        });

        $this->assertSame('DELETE', $request->method());
    }

    public function test_converts_post_to_put_when_method_is_put(): void
    {
        $request = $this->createMockRequest('POST', ['_method' => 'PUT']);
        $response = new Response('');

        $this->middleware->handle($request, $response, function ($req, $res) {
            return $res;
        });

        $this->assertSame('PUT', $request->method());
    }

    public function test_converts_post_to_patch_when_method_is_patch(): void
    {
        $request = $this->createMockRequest('POST', ['_method' => 'PATCH']);
        $response = new Response('');

        $this->middleware->handle($request, $response, function ($req, $res) {
            return $res;
        });

        $this->assertSame('PATCH', $request->method());
    }

    public function test_uppercases_method_value(): void
    {
        $request = $this->createMockRequest('POST', ['_method' => 'delete']);
        $response = new Response('');

        $this->middleware->handle($request, $response, function ($req, $res) {
            return $res;
        });

        $this->assertSame('DELETE', $request->method());
    }

    public function test_ignores_invalid_method_values(): void
    {
        $request = $this->createMockRequest('POST', ['_method' => 'INVALID']);
        $response = new Response('');

        $this->middleware->handle($request, $response, function ($req, $res) {
            return $res;
        });

        $this->assertSame('POST', $request->method());
    }

    public function test_does_not_allow_get_as_spoofed_method(): void
    {
        $request = $this->createMockRequest('POST', ['_method' => 'GET']);
        $response = new Response('');

        $this->middleware->handle($request, $response, function ($req, $res) {
            return $res;
        });

        $this->assertSame('POST', $request->method());
    }

    public function test_does_not_allow_post_as_spoofed_method(): void
    {
        $request = $this->createMockRequest('POST', ['_method' => 'POST']);
        $response = new Response('');

        $this->middleware->handle($request, $response, function ($req, $res) {
            return $res;
        });

        $this->assertSame('POST', $request->method());
    }

    public function test_trims_whitespace_from_method_value(): void
    {
        $request = $this->createMockRequest('POST', ['_method' => '  DELETE  ']);
        $response = new Response('');

        $this->middleware->handle($request, $response, function ($req, $res) {
            return $res;
        });

        $this->assertSame('DELETE', $request->method());
    }

    /**
     * Helper to create a mock request.
     */
    private function createMockRequest(string $method, array $formBody): Request
    {
        return new Request(
            routeParams: [],
            queryParams: [],
            formBody: $formBody,
            rawJsonBody: null,
            headers: [],
            cookies: [],
            files: [],
            method: $method,
            server: ['REQUEST_METHOD' => $method, 'REQUEST_URI' => '/test'],
        );
    }
}
