<?php

declare(strict_types=1);

namespace Lalaz\Wire\Tests\Unit;

use Lalaz\Wire\Tests\Common\WireUnitTestCase;
use Lalaz\Wire\HttpClient\HttpClientBuilder;
use Lalaz\Wire\HttpClient\HttpClient;

final class HttpClientBuilderTest extends WireUnitTestCase
{
    public function test_create_returns_builder_instance(): void
    {
        $builder = HttpClientBuilder::create();

        $this->assertInstanceOf(HttpClientBuilder::class, $builder);
    }

    public function test_create_with_base_url(): void
    {
        $transport = $this->createFakeTransport();
        $client = HttpClientBuilder::create('https://api.example.com')
            ->withTransport($transport)
            ->build();

        $client->get('/test');

        $this->assertSame('https://api.example.com/test', $transport->lastRequest()->url);
    }

    public function test_create_strips_trailing_slash_from_base_url(): void
    {
        $transport = $this->createFakeTransport();
        $client = HttpClientBuilder::create('https://api.example.com/')
            ->withTransport($transport)
            ->build();

        $client->get('/test');

        $this->assertSame('https://api.example.com/test', $transport->lastRequest()->url);
    }

    public function test_build_returns_http_client(): void
    {
        $client = HttpClientBuilder::create()->build();

        $this->assertInstanceOf(HttpClient::class, $client);
    }

    public function test_with_transport_sets_custom_transport(): void
    {
        $transport = $this->createFakeTransport(body: ['custom' => true]);
        $client = HttpClientBuilder::create()
            ->withTransport($transport)
            ->build();

        $response = $client->get('https://example.com/test');

        $this->assertSame(['custom' => true], $response->body);
    }

    public function test_base_headers_sets_default_headers(): void
    {
        $transport = $this->createFakeTransport();
        $client = HttpClientBuilder::create()
            ->withTransport($transport)
            ->baseHeaders([
                'Authorization' => 'Bearer token',
                'Accept' => 'application/json',
            ])
            ->build();

        $client->get('https://example.com/test');

        $headers = $transport->lastRequest()->headers;
        $this->assertSame('Bearer token', $headers['Authorization']);
        $this->assertSame('application/json', $headers['Accept']);
    }

    public function test_timeout_sets_request_timeout(): void
    {
        $transport = $this->createFakeTransport();
        $client = HttpClientBuilder::create()
            ->withTransport($transport)
            ->timeout(30)
            ->build();

        $client->get('https://example.com/test');

        $this->assertSame(30, $transport->lastRequest()->timeout);
    }

    public function test_connect_timeout_sets_connection_timeout(): void
    {
        $transport = $this->createFakeTransport();
        $client = HttpClientBuilder::create()
            ->withTransport($transport)
            ->connectTimeout(10)
            ->build();

        $client->get('https://example.com/test');

        $this->assertSame(10, $transport->lastRequest()->connectTimeout);
    }

    public function test_skip_ssl_disables_certificate_verification(): void
    {
        $transport = $this->createFakeTransport();
        $client = HttpClientBuilder::create()
            ->withTransport($transport)
            ->skipSsl()
            ->build();

        $client->get('https://example.com/test');

        $this->assertTrue($transport->lastRequest()->skipSsl);
    }

    public function test_skip_ssl_can_be_disabled(): void
    {
        $transport = $this->createFakeTransport();
        $client = HttpClientBuilder::create()
            ->withTransport($transport)
            ->skipSsl(false)
            ->build();

        $client->get('https://example.com/test');

        $this->assertFalse($transport->lastRequest()->skipSsl);
    }

    public function test_with_defaults_merges_options(): void
    {
        $transport = $this->createFakeTransport();
        $client = HttpClientBuilder::create()
            ->withTransport($transport)
            ->withDefaults([
                'timeout' => 60,
                'headers' => ['X-Custom' => 'value'],
            ])
            ->build();

        $client->get('https://example.com/test');

        $this->assertSame(60, $transport->lastRequest()->timeout);
        $this->assertSame('value', $transport->lastRequest()->headers['X-Custom']);
    }

    public function test_fluent_interface(): void
    {
        $transport = $this->createFakeTransport();

        $client = HttpClientBuilder::create('https://api.example.com')
            ->withTransport($transport)
            ->baseHeaders(['Accept' => 'application/json'])
            ->timeout(30)
            ->connectTimeout(10)
            ->skipSsl(false)
            ->build();

        $client->get('/test');

        $request = $transport->lastRequest();
        $this->assertSame('https://api.example.com/test', $request->url);
        $this->assertSame('application/json', $request->headers['Accept']);
        $this->assertSame(30, $request->timeout);
        $this->assertSame(10, $request->connectTimeout);
        $this->assertFalse($request->skipSsl);
    }

    public function test_default_timeout_values(): void
    {
        $transport = $this->createFakeTransport();
        $client = HttpClientBuilder::create()
            ->withTransport($transport)
            ->build();

        $client->get('https://example.com/test');

        $request = $transport->lastRequest();
        $this->assertSame(10, $request->timeout);
        $this->assertSame(5, $request->connectTimeout);
    }

    public function test_default_ssl_verification_enabled(): void
    {
        $transport = $this->createFakeTransport();
        $client = HttpClientBuilder::create()
            ->withTransport($transport)
            ->build();

        $client->get('https://example.com/test');

        $this->assertFalse($transport->lastRequest()->skipSsl);
    }

    public function test_default_follow_redirects_enabled(): void
    {
        $transport = $this->createFakeTransport();
        $client = HttpClientBuilder::create()
            ->withTransport($transport)
            ->build();

        $client->get('https://example.com/test');

        $this->assertTrue($transport->lastRequest()->followRedirects);
    }

    public function test_default_max_redirects(): void
    {
        $transport = $this->createFakeTransport();
        $client = HttpClientBuilder::create()
            ->withTransport($transport)
            ->build();

        $client->get('https://example.com/test');

        $this->assertSame(5, $transport->lastRequest()->maxRedirects);
    }
}
