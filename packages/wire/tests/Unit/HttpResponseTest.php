<?php

declare(strict_types=1);

namespace Lalaz\Wire\Tests\Unit;

use Lalaz\Wire\Tests\Common\WireUnitTestCase;
use Lalaz\Wire\HttpClient\HttpResponse;

final class HttpResponseTest extends WireUnitTestCase
{
    public function test_constructor_sets_all_properties(): void
    {
        $response = new HttpResponse(
            statusCode: 200,
            headers: ['Content-Type' => 'application/json'],
            body: ['success' => true],
            durationMs: 123.45,
        );

        $this->assertSame(200, $response->statusCode);
        $this->assertSame(['Content-Type' => 'application/json'], $response->headers);
        $this->assertSame(['success' => true], $response->body);
        $this->assertSame(123.45, $response->durationMs);
    }

    public function test_status_code_can_be_any_http_status(): void
    {
        $statuses = [200, 201, 204, 301, 400, 401, 403, 404, 500, 502, 503];

        foreach ($statuses as $status) {
            $response = new HttpResponse($status, [], null, 0.0);
            $this->assertSame($status, $response->statusCode);
        }
    }

    public function test_headers_can_be_empty(): void
    {
        $response = new HttpResponse(200, [], null, 0.0);
        $this->assertSame([], $response->headers);
    }

    public function test_headers_preserves_all_values(): void
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Content-Length' => '1234',
            'X-Request-Id' => 'abc-123',
            'Cache-Control' => 'no-cache',
        ];

        $response = new HttpResponse(200, $headers, null, 0.0);

        $this->assertCount(4, $response->headers);
        $this->assertSame('application/json', $response->headers['Content-Type']);
        $this->assertSame('1234', $response->headers['Content-Length']);
        $this->assertSame('abc-123', $response->headers['X-Request-Id']);
        $this->assertSame('no-cache', $response->headers['Cache-Control']);
    }

    public function test_body_can_be_null(): void
    {
        $response = new HttpResponse(204, [], null, 0.0);
        $this->assertNull($response->body);
    }

    public function test_body_can_be_string(): void
    {
        $response = new HttpResponse(200, [], 'Hello World', 0.0);
        $this->assertSame('Hello World', $response->body);
    }

    public function test_body_can_be_array(): void
    {
        $body = ['name' => 'John', 'items' => [1, 2, 3]];
        $response = new HttpResponse(200, [], $body, 0.0);
        $this->assertSame($body, $response->body);
    }

    public function test_body_can_be_empty_string(): void
    {
        $response = new HttpResponse(200, [], '', 0.0);
        $this->assertSame('', $response->body);
    }

    public function test_body_can_be_empty_array(): void
    {
        $response = new HttpResponse(200, [], [], 0.0);
        $this->assertSame([], $response->body);
    }

    public function test_duration_is_float(): void
    {
        $response = new HttpResponse(200, [], null, 50.123);
        $this->assertSame(50.123, $response->durationMs);
    }

    public function test_duration_can_be_zero(): void
    {
        $response = new HttpResponse(200, [], null, 0.0);
        $this->assertSame(0.0, $response->durationMs);
    }

    public function test_duration_can_be_very_small(): void
    {
        $response = new HttpResponse(200, [], null, 0.001);
        $this->assertSame(0.001, $response->durationMs);
    }

    public function test_duration_can_be_large(): void
    {
        $response = new HttpResponse(200, [], null, 30000.0);
        $this->assertSame(30000.0, $response->durationMs);
    }

    public function test_properties_are_public(): void
    {
        $response = new HttpResponse(200, ['X-Test' => 'value'], 'body', 100.0);

        // Verify all properties are accessible
        $this->assertSame(200, $response->statusCode);
        $this->assertSame(['X-Test' => 'value'], $response->headers);
        $this->assertSame('body', $response->body);
        $this->assertSame(100.0, $response->durationMs);
    }

    public function test_successful_status_codes(): void
    {
        $successCodes = [200, 201, 202, 204];

        foreach ($successCodes as $code) {
            $response = new HttpResponse($code, [], null, 0.0);
            $this->assertTrue($response->statusCode >= 200 && $response->statusCode < 300);
        }
    }

    public function test_error_status_codes(): void
    {
        $errorCodes = [400, 401, 403, 404, 500, 502, 503];

        foreach ($errorCodes as $code) {
            $response = new HttpResponse($code, [], null, 0.0);
            $this->assertTrue($response->statusCode >= 400);
        }
    }

    public function test_body_with_nested_structure(): void
    {
        $body = [
            'data' => [
                'user' => [
                    'id' => 1,
                    'name' => 'John',
                    'roles' => ['admin', 'user'],
                ],
            ],
            'meta' => [
                'page' => 1,
                'total' => 100,
            ],
        ];

        $response = new HttpResponse(200, [], $body, 0.0);

        $this->assertSame(1, $response->body['data']['user']['id']);
        $this->assertSame('John', $response->body['data']['user']['name']);
        $this->assertContains('admin', $response->body['data']['user']['roles']);
    }
}
