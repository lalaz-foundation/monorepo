<?php

declare(strict_types=1);

namespace Lalaz\Wire\Tests\Unit;

use Lalaz\Wire\Tests\Common\WireUnitTestCase;
use Lalaz\Wire\HttpClient\HttpRequest;

final class HttpRequestTest extends WireUnitTestCase
{
    public function test_constructor_sets_all_properties(): void
    {
        $request = new HttpRequest(
            method: 'POST',
            url: 'https://example.com/api',
            headers: ['Content-Type' => 'application/json'],
            query: ['page' => 1],
            body: '{"name":"test"}',
            timeout: 30,
            connectTimeout: 10,
            skipSsl: true,
            followRedirects: false,
            maxRedirects: 3,
        );

        $this->assertSame('POST', $request->method);
        $this->assertSame('https://example.com/api', $request->url);
        $this->assertSame(['Content-Type' => 'application/json'], $request->headers);
        $this->assertSame(['page' => 1], $request->query);
        $this->assertSame('{"name":"test"}', $request->body);
        $this->assertSame(30, $request->timeout);
        $this->assertSame(10, $request->connectTimeout);
        $this->assertTrue($request->skipSsl);
        $this->assertFalse($request->followRedirects);
        $this->assertSame(3, $request->maxRedirects);
    }

    public function test_default_values(): void
    {
        $request = new HttpRequest(
            method: 'GET',
            url: 'https://example.com',
        );

        $this->assertSame([], $request->headers);
        $this->assertSame([], $request->query);
        $this->assertNull($request->body);
        $this->assertSame(10, $request->timeout);
        $this->assertSame(5, $request->connectTimeout);
        $this->assertFalse($request->skipSsl);
        $this->assertTrue($request->followRedirects);
        $this->assertSame(5, $request->maxRedirects);
    }

    public function test_method_is_stored_as_provided(): void
    {
        $request = new HttpRequest(method: 'get', url: 'https://example.com');
        $this->assertSame('get', $request->method);

        $request = new HttpRequest(method: 'POST', url: 'https://example.com');
        $this->assertSame('POST', $request->method);
    }

    public function test_url_is_stored_as_provided(): void
    {
        $request = new HttpRequest(
            method: 'GET',
            url: 'https://example.com/path?existing=param',
        );

        $this->assertSame('https://example.com/path?existing=param', $request->url);
    }

    public function test_headers_can_be_empty_array(): void
    {
        $request = new HttpRequest(
            method: 'GET',
            url: 'https://example.com',
            headers: [],
        );

        $this->assertSame([], $request->headers);
    }

    public function test_headers_preserves_multiple_values(): void
    {
        $request = new HttpRequest(
            method: 'GET',
            url: 'https://example.com',
            headers: [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer token',
                'X-Custom' => 'value',
            ],
        );

        $this->assertCount(3, $request->headers);
        $this->assertSame('application/json', $request->headers['Accept']);
        $this->assertSame('Bearer token', $request->headers['Authorization']);
        $this->assertSame('value', $request->headers['X-Custom']);
    }

    public function test_query_can_contain_various_types(): void
    {
        $request = new HttpRequest(
            method: 'GET',
            url: 'https://example.com',
            query: [
                'string' => 'value',
                'int' => 42,
                'bool' => true,
                'array' => ['a', 'b'],
            ],
        );

        $this->assertSame('value', $request->query['string']);
        $this->assertSame(42, $request->query['int']);
        $this->assertTrue($request->query['bool']);
        $this->assertSame(['a', 'b'], $request->query['array']);
    }

    public function test_body_can_be_string(): void
    {
        $request = new HttpRequest(
            method: 'POST',
            url: 'https://example.com',
            body: 'plain text body',
        );

        $this->assertSame('plain text body', $request->body);
    }

    public function test_body_can_be_array(): void
    {
        $body = ['name' => 'John', 'age' => 30];
        $request = new HttpRequest(
            method: 'POST',
            url: 'https://example.com',
            body: $body,
        );

        $this->assertSame($body, $request->body);
    }

    public function test_body_can_be_null(): void
    {
        $request = new HttpRequest(
            method: 'GET',
            url: 'https://example.com',
            body: null,
        );

        $this->assertNull($request->body);
    }

    public function test_timeout_accepts_zero(): void
    {
        $request = new HttpRequest(
            method: 'GET',
            url: 'https://example.com',
            timeout: 0,
        );

        $this->assertSame(0, $request->timeout);
    }

    public function test_connect_timeout_accepts_zero(): void
    {
        $request = new HttpRequest(
            method: 'GET',
            url: 'https://example.com',
            connectTimeout: 0,
        );

        $this->assertSame(0, $request->connectTimeout);
    }

    public function test_max_redirects_accepts_zero(): void
    {
        $request = new HttpRequest(
            method: 'GET',
            url: 'https://example.com',
            maxRedirects: 0,
        );

        $this->assertSame(0, $request->maxRedirects);
    }

    public function test_properties_are_public_readonly(): void
    {
        $request = new HttpRequest(
            method: 'GET',
            url: 'https://example.com',
        );

        // Verify properties can be accessed
        $this->assertSame('GET', $request->method);
        $this->assertSame('https://example.com', $request->url);
    }
}
