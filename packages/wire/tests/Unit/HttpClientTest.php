<?php

declare(strict_types=1);

namespace Lalaz\Wire\Tests\Unit;

use Lalaz\Wire\Tests\Common\WireUnitTestCase;
use Lalaz\Wire\HttpClient\HttpClient;
use Lalaz\Wire\HttpClient\HttpClientBuilder;
use Lalaz\Wire\HttpClient\HttpResponse;

final class HttpClientTest extends WireUnitTestCase
{
    public function test_get_request_with_base_url(): void
    {
        [$client, $transport] = $this->createClientWithFakeTransport(
            baseUrl: 'https://api.example.com',
            body: ['success' => true]
        );

        $response = $client->get('/users');

        $this->assertInstanceOf(HttpResponse::class, $response);
        $this->assertSame(200, $response->statusCode);
        $this->assertSame('https://api.example.com/users', $transport->lastRequest()->url);
        $this->assertSame('GET', $transport->lastRequest()->method);
    }

    public function test_get_request_without_base_url(): void
    {
        [$client, $transport] = $this->createClientWithFakeTransport();

        $client->get('https://api.example.com/users');

        $this->assertSame('https://api.example.com/users', $transport->lastRequest()->url);
    }

    public function test_post_request(): void
    {
        [$client, $transport] = $this->createClientWithFakeTransport(
            baseUrl: 'https://api.example.com',
            status: 201,
            body: ['id' => 1]
        );

        $response = $client->post('/users', [
            'body' => json_encode(['name' => 'John']),
        ]);

        $this->assertSame(201, $response->statusCode);
        $this->assertSame('POST', $transport->lastRequest()->method);
        $this->assertSame(json_encode(['name' => 'John']), $transport->lastRequest()->body);
    }

    public function test_put_request(): void
    {
        [$client, $transport] = $this->createClientWithFakeTransport(
            baseUrl: 'https://api.example.com'
        );

        $client->put('/users/1', [
            'body' => json_encode(['name' => 'Jane']),
        ]);

        $this->assertSame('PUT', $transport->lastRequest()->method);
        $this->assertSame('https://api.example.com/users/1', $transport->lastRequest()->url);
    }

    public function test_patch_request(): void
    {
        [$client, $transport] = $this->createClientWithFakeTransport(
            baseUrl: 'https://api.example.com'
        );

        $client->patch('/users/1', [
            'body' => json_encode(['name' => 'Jane']),
        ]);

        $this->assertSame('PATCH', $transport->lastRequest()->method);
    }

    public function test_delete_request(): void
    {
        [$client, $transport] = $this->createClientWithFakeTransport(
            baseUrl: 'https://api.example.com',
            status: 204
        );

        $response = $client->delete('/users/1');

        $this->assertSame(204, $response->statusCode);
        $this->assertSame('DELETE', $transport->lastRequest()->method);
    }

    public function test_request_with_query_parameters(): void
    {
        [$client, $transport] = $this->createClientWithFakeTransport(
            baseUrl: 'https://api.example.com'
        );

        $client->get('/search', [
            'query' => ['q' => 'test', 'page' => 1],
        ]);

        $this->assertSame(['q' => 'test', 'page' => 1], $transport->lastRequest()->query);
    }

    public function test_request_with_custom_headers(): void
    {
        [$client, $transport] = $this->createClientWithFakeTransport();

        $client->get('https://api.example.com/data', [
            'headers' => [
                'Authorization' => 'Bearer token123',
                'Accept' => 'application/json',
            ],
        ]);

        $this->assertRequestHasHeaders($transport, [
            'Authorization' => 'Bearer token123',
            'Accept' => 'application/json',
        ]);
    }

    public function test_merges_base_headers_with_request_headers(): void
    {
        $transport = $this->createFakeTransport();
        $client = HttpClientBuilder::create('https://api.example.com')
            ->withTransport($transport)
            ->baseHeaders(['X-Api-Key' => 'secret'])
            ->build();

        $client->get('/data', [
            'headers' => ['X-Request-Id' => '123'],
        ]);

        $headers = $transport->lastRequest()->headers;
        $this->assertArrayHasKey('X-Api-Key', $headers);
        $this->assertArrayHasKey('X-Request-Id', $headers);
    }

    public function test_request_headers_override_base_headers(): void
    {
        $transport = $this->createFakeTransport();
        $client = HttpClientBuilder::create()
            ->withTransport($transport)
            ->baseHeaders(['Accept' => 'text/plain'])
            ->build();

        $client->get('https://api.example.com/data', [
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->assertSame('application/json', $transport->lastRequest()->headers['Accept']);
    }

    public function test_request_with_timeout(): void
    {
        [$client, $transport] = $this->createClientWithFakeTransport();

        $client->get('https://api.example.com/slow', [
            'timeout' => 30,
        ]);

        $this->assertSame(30, $transport->lastRequest()->timeout);
    }

    public function test_request_with_connect_timeout(): void
    {
        [$client, $transport] = $this->createClientWithFakeTransport();

        $client->get('https://api.example.com/test', [
            'connect_timeout' => 3,
        ]);

        $this->assertSame(3, $transport->lastRequest()->connectTimeout);
    }

    public function test_request_with_skip_ssl(): void
    {
        [$client, $transport] = $this->createClientWithFakeTransport();

        $client->get('https://self-signed.example.com/test', [
            'skip_ssl' => true,
        ]);

        $this->assertTrue($transport->lastRequest()->skipSsl);
    }

    public function test_response_contains_duration(): void
    {
        [$client, $transport] = $this->createClientWithFakeTransport();

        $response = $client->get('https://api.example.com/test');

        $this->assertIsFloat($response->durationMs);
        $this->assertGreaterThanOrEqual(0, $response->durationMs);
    }

    public function test_response_json_body_is_decoded(): void
    {
        [$client, $transport] = $this->createClientWithFakeTransport(
            body: ['name' => 'John', 'age' => 30]
        );

        $response = $client->get('https://api.example.com/user');

        $this->assertIsArray($response->body);
        $this->assertSame('John', $response->body['name']);
        $this->assertSame(30, $response->body['age']);
    }

    public function test_response_non_json_body_remains_string(): void
    {
        $transport = $this->createFakeTransport(body: 'plain text response');
        $client = HttpClientBuilder::create()
            ->withTransport($transport)
            ->build();

        $response = $client->get('https://example.com/text');

        $this->assertSame('plain text response', $response->body);
    }

    public function test_response_headers_are_captured(): void
    {
        [$client, $transport] = $this->createClientWithFakeTransport(
            headers: ['Content-Type' => 'application/json', 'X-Request-Id' => 'abc123']
        );

        $response = $client->get('https://api.example.com/test');

        $this->assertArrayHasKey('Content-Type', $response->headers);
        $this->assertSame('abc123', $response->headers['X-Request-Id']);
    }

    public function test_with_base_url_creates_new_instance(): void
    {
        [$client, $transport] = $this->createClientWithFakeTransport(
            baseUrl: 'https://api.example.com'
        );

        $newClient = $client->withBaseUrl('https://api.other.com');

        $this->assertNotSame($client, $newClient);
    }

    public function test_with_base_url_changes_url(): void
    {
        $transport = $this->createFakeTransport();
        $client = HttpClientBuilder::create('https://api.example.com')
            ->withTransport($transport)
            ->build();

        $newClient = $client->withBaseUrl('https://api.other.com');
        $newClient->get('/test');

        $this->assertSame('https://api.other.com/test', $transport->lastRequest()->url);
    }

    public function test_handles_empty_response_body(): void
    {
        [$client, $transport] = $this->createClientWithFakeTransport(
            status: 204,
            body: ''
        );

        $response = $client->delete('https://api.example.com/resource/1');

        $this->assertSame(204, $response->statusCode);
        $this->assertSame('', $response->body);
    }

    public function test_handles_null_response_body(): void
    {
        [$client, $transport] = $this->createClientWithFakeTransport(
            body: null
        );

        $response = $client->get('https://api.example.com/empty');

        // null body becomes empty string after transport processing
        $this->assertSame('', $response->body);
    }
}
