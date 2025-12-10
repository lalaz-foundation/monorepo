<?php

declare(strict_types=1);

namespace Lalaz\Wire\Tests\Integration;

use Lalaz\Wire\Tests\Common\WireIntegrationTestCase;

/**
 * Integration tests for CurlTransport.
 *
 * These tests make real HTTP requests and require network access.
 * Run with: WIRE_INTEGRATION_TESTS=1 ./vendor/bin/phpunit --testsuite=Integration
 */
final class CurlTransportTest extends WireIntegrationTestCase
{
    public function test_get_request_to_real_endpoint(): void
    {
        $response = $this->client->get($this->getJsonTestUrl());

        $this->assertSame(200, $response->statusCode);
        $this->assertIsArray($response->body);
    }

    public function test_post_request_with_json_body(): void
    {
        $response = $this->client->post($this->getEchoTestUrl(), [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode(['name' => 'Test', 'value' => 123]),
        ]);

        $this->assertSame(200, $response->statusCode);
        $this->assertIsArray($response->body);
    }

    public function test_request_with_query_parameters(): void
    {
        $response = $this->client->get($this->getEchoTestUrl(), [
            'query' => ['foo' => 'bar', 'baz' => 'qux'],
        ]);

        $this->assertSame(200, $response->statusCode);
        $this->assertIsArray($response->body);
        $this->assertArrayHasKey('args', $response->body);
        $this->assertSame('bar', $response->body['args']['foo']);
    }

    public function test_request_with_custom_headers(): void
    {
        $response = $this->client->get($this->getEchoTestUrl(), [
            'headers' => [
                'X-Custom-Header' => 'CustomValue',
                'Accept' => 'application/json',
            ],
        ]);

        $this->assertSame(200, $response->statusCode);
        $this->assertArrayHasKey('headers', $response->body);
    }

    public function test_handles_404_status(): void
    {
        $response = $this->client->get($this->getStatusTestUrl(404));

        $this->assertSame(404, $response->statusCode);
    }

    public function test_handles_500_status(): void
    {
        $response = $this->client->get($this->getStatusTestUrl(500));

        $this->assertSame(500, $response->statusCode);
    }

    public function test_response_includes_duration(): void
    {
        $response = $this->client->get($this->getJsonTestUrl());

        $this->assertGreaterThan(0, $response->durationMs);
    }

    public function test_put_request(): void
    {
        $response = $this->client->put($this->getEchoTestUrl(), [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode(['updated' => true]),
        ]);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('PUT', $response->body['method']);
    }

    public function test_delete_request(): void
    {
        $response = $this->client->delete($this->getEchoTestUrl());

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('DELETE', $response->body['method']);
    }

    public function test_patch_request(): void
    {
        $response = $this->client->patch($this->getEchoTestUrl(), [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode(['patched' => true]),
        ]);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('PATCH', $response->body['method']);
    }
}
