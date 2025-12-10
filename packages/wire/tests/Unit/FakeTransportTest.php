<?php

declare(strict_types=1);

namespace Lalaz\Wire\Tests\Unit;

use Lalaz\Wire\Tests\Common\WireUnitTestCase;
use Lalaz\Wire\Tests\Common\FakeTransport;
use Lalaz\Wire\HttpClient\HttpClientBuilder;
use Lalaz\Wire\HttpClient\HttpException;

final class FakeTransportTest extends WireUnitTestCase
{
    public function test_returns_default_response(): void
    {
        $transport = new FakeTransport();
        $request = $this->createRequest();

        $response = $transport->send($request);

        $this->assertSame(200, $response['status']);
        $this->assertSame([], $response['headers']);
    }

    public function test_returns_custom_status(): void
    {
        $transport = new FakeTransport(status: 404);
        $request = $this->createRequest();

        $response = $transport->send($request);

        $this->assertSame(404, $response['status']);
    }

    public function test_returns_custom_headers(): void
    {
        $transport = new FakeTransport(headers: ['Content-Type' => 'text/plain']);
        $request = $this->createRequest();

        $response = $transport->send($request);

        $this->assertSame(['Content-Type' => 'text/plain'], $response['headers']);
    }

    public function test_returns_custom_body(): void
    {
        $transport = new FakeTransport(body: ['data' => 'test']);
        $request = $this->createRequest();

        $response = $transport->send($request);

        $this->assertSame('{"data":"test"}', $response['body']);
    }

    public function test_records_requests(): void
    {
        $transport = new FakeTransport();

        $transport->send($this->createRequest(method: 'GET', url: 'https://example.com/a'));
        $transport->send($this->createRequest(method: 'POST', url: 'https://example.com/b'));

        $this->assertCount(2, $transport->requests);
        $this->assertSame('GET', $transport->requests[0]->method);
        $this->assertSame('POST', $transport->requests[1]->method);
    }

    public function test_last_request_returns_most_recent(): void
    {
        $transport = new FakeTransport();

        $transport->send($this->createRequest(url: 'https://example.com/first'));
        $transport->send($this->createRequest(url: 'https://example.com/second'));

        $this->assertSame('https://example.com/second', $transport->lastRequest()->url);
    }

    public function test_last_request_returns_null_when_no_requests(): void
    {
        $transport = new FakeTransport();

        $this->assertNull($transport->lastRequest());
    }

    public function test_request_count(): void
    {
        $transport = new FakeTransport();

        $this->assertSame(0, $transport->requestCount());

        $transport->send($this->createRequest());
        $this->assertSame(1, $transport->requestCount());

        $transport->send($this->createRequest());
        $this->assertSame(2, $transport->requestCount());
    }

    public function test_clear_requests(): void
    {
        $transport = new FakeTransport();
        $transport->send($this->createRequest());
        $transport->send($this->createRequest());

        $transport->clearRequests();

        $this->assertSame(0, $transport->requestCount());
        $this->assertNull($transport->lastRequest());
    }

    public function test_will_throw_throws_on_send(): void
    {
        $transport = new FakeTransport();
        $transport->willThrow(new HttpException('Network error'));

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Network error');

        $transport->send($this->createRequest());
    }

    public function test_queue_response_returns_responses_in_order(): void
    {
        $transport = new FakeTransport(status: 200);
        $transport->queueResponse(status: 201);
        $transport->queueResponse(status: 204);

        $response1 = $transport->send($this->createRequest());
        $response2 = $transport->send($this->createRequest());
        $response3 = $transport->send($this->createRequest());

        $this->assertSame(200, $response1['status']);
        $this->assertSame(201, $response2['status']);
        $this->assertSame(204, $response3['status']);
    }

    public function test_queue_response_repeats_last_when_exhausted(): void
    {
        $transport = new FakeTransport(status: 200);
        $transport->queueResponse(status: 201);

        $transport->send($this->createRequest()); // 200
        $transport->send($this->createRequest()); // 201
        $response = $transport->send($this->createRequest()); // 201 (repeated)

        $this->assertSame(201, $response['status']);
    }

    public function test_fluent_interface(): void
    {
        $transport = (new FakeTransport(status: 200, body: ['first' => true]))
            ->queueResponse(201, [], ['second' => true]);

        $response1 = $transport->send($this->createRequest());
        $response2 = $transport->send($this->createRequest());

        $this->assertSame(200, $response1['status']);
        $this->assertSame(201, $response2['status']);
    }
}
