<?php

declare(strict_types=1);

namespace Lalaz\Wire\Tests\Common;

use PHPUnit\Framework\TestCase;
use Lalaz\Wire\HttpClient\Contracts\TransportInterface;
use Lalaz\Wire\HttpClient\HttpClient;
use Lalaz\Wire\HttpClient\HttpClientBuilder;
use Lalaz\Wire\HttpClient\HttpRequest;
use Lalaz\Wire\HttpClient\HttpResponse;

/**
 * Base test case for Wire unit tests.
 *
 * Provides helper methods for creating HTTP clients, fake transports,
 * and common test fixtures.
 */
abstract class WireUnitTestCase extends TestCase
{
    /**
     * Create a fake transport for testing.
     */
    protected function createFakeTransport(
        int $status = 200,
        array $headers = [],
        mixed $body = null
    ): FakeTransport {
        return new FakeTransport($status, $headers, $body);
    }

    /**
     * Create an HTTP client with a fake transport.
     */
    protected function createClientWithFakeTransport(
        string $baseUrl = '',
        int $status = 200,
        array $headers = [],
        mixed $body = null
    ): array {
        $transport = $this->createFakeTransport($status, $headers, $body);
        $client = HttpClientBuilder::create($baseUrl)
            ->withTransport($transport)
            ->build();

        return [$client, $transport];
    }

    /**
     * Create a basic HTTP request for testing.
     */
    protected function createRequest(
        string $method = 'GET',
        string $url = 'https://example.com/test',
        array $headers = [],
        array $query = [],
        mixed $body = null
    ): HttpRequest {
        return new HttpRequest(
            method: $method,
            url: $url,
            headers: $headers,
            query: $query,
            body: $body,
        );
    }

    /**
     * Create a basic HTTP response for testing.
     */
    protected function createResponse(
        int $statusCode = 200,
        array $headers = [],
        mixed $body = null,
        float $durationMs = 0.0
    ): HttpResponse {
        return new HttpResponse($statusCode, $headers, $body, $durationMs);
    }

    /**
     * Assert that a request was made to a specific URL.
     */
    protected function assertRequestMade(
        FakeTransport $transport,
        string $expectedUrl,
        string $expectedMethod = 'GET'
    ): void {
        $this->assertNotEmpty($transport->requests, 'No requests were made');

        $found = false;
        foreach ($transport->requests as $request) {
            if ($request->url === $expectedUrl && $request->method === $expectedMethod) {
                $found = true;
                break;
            }
        }

        $this->assertTrue(
            $found,
            "Expected {$expectedMethod} request to {$expectedUrl} was not made"
        );
    }

    /**
     * Assert that a request contains specific headers.
     */
    protected function assertRequestHasHeaders(
        FakeTransport $transport,
        array $expectedHeaders
    ): void {
        $this->assertNotEmpty($transport->requests, 'No requests were made');

        $lastRequest = end($transport->requests);
        foreach ($expectedHeaders as $name => $value) {
            $this->assertArrayHasKey($name, $lastRequest->headers);
            $this->assertSame($value, $lastRequest->headers[$name]);
        }
    }
}

/**
 * Fake transport implementation for testing.
 */
final class FakeTransport implements TransportInterface
{
    /** @var array<int, HttpRequest> */
    public array $requests = [];

    /** @var array<int, array> */
    private array $responses = [];

    private int $responseIndex = 0;

    public function __construct(
        int $status = 200,
        array $headers = [],
        mixed $body = null
    ) {
        $this->responses[] = [
            'status' => $status,
            'headers' => $headers,
            'body' => is_array($body) ? json_encode($body) : $body,
        ];
    }

    /**
     * Queue a response to be returned.
     */
    public function queueResponse(
        int $status = 200,
        array $headers = [],
        mixed $body = null
    ): self {
        $this->responses[] = [
            'status' => $status,
            'headers' => $headers,
            'body' => is_array($body) ? json_encode($body) : $body,
        ];
        return $this;
    }

    /**
     * Set the next response to throw an exception.
     */
    public function willThrow(\Throwable $exception): self
    {
        $this->responses = [['exception' => $exception]];
        $this->responseIndex = 0;
        return $this;
    }

    /**
     * Get the last request made.
     */
    public function lastRequest(): ?HttpRequest
    {
        return $this->requests[array_key_last($this->requests)] ?? null;
    }

    /**
     * Get the number of requests made.
     */
    public function requestCount(): int
    {
        return count($this->requests);
    }

    /**
     * Clear all recorded requests.
     */
    public function clearRequests(): void
    {
        $this->requests = [];
    }

    public function send(HttpRequest $request): array
    {
        $this->requests[] = $request;

        $response = $this->responses[$this->responseIndex] ?? $this->responses[count($this->responses) - 1];

        if ($this->responseIndex < count($this->responses) - 1) {
            $this->responseIndex++;
        }

        if (isset($response['exception'])) {
            throw $response['exception'];
        }

        return [
            'status' => $response['status'],
            'headers' => $response['headers'],
            'body' => $response['body'],
        ];
    }
}
