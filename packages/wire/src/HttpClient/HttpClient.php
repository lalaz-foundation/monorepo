<?php

declare(strict_types=1);

namespace Lalaz\Wire\HttpClient;

use Lalaz\Wire\HttpClient\Contracts\TransportInterface;
use Lalaz\Wire\HttpClient\Transport\CurlTransport;

final class HttpClient
{
    public function __construct(
        private string $baseUrl = '',
        private ?TransportInterface $transport = null,
        private array $defaults = [],
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->transport = $transport ?? new CurlTransport();
        $this->defaults = array_replace_recursive(
            [
                'headers' => [],
                'timeout' => 10,
                'connect_timeout' => 5,
                'skip_ssl' => false,
                'follow_redirects' => true,
                'max_redirects' => 5,
            ],
            $defaults,
        );
    }

    public function withBaseUrl(string $baseUrl): self
    {
        $clone = clone $this;
        $clone->baseUrl = rtrim($baseUrl, '/');
        return $clone;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function request(
        string $method,
        string $endpoint,
        array $options = [],
    ): HttpResponse {
        $request = $this->buildRequest($method, $endpoint, $options);
        return $this->send($request);
    }

    public function get(string $endpoint, array $options = []): HttpResponse
    {
        return $this->request('GET', $endpoint, $options);
    }

    public function post(string $endpoint, array $options = []): HttpResponse
    {
        return $this->request('POST', $endpoint, $options);
    }

    public function put(string $endpoint, array $options = []): HttpResponse
    {
        return $this->request('PUT', $endpoint, $options);
    }

    public function patch(string $endpoint, array $options = []): HttpResponse
    {
        return $this->request('PATCH', $endpoint, $options);
    }

    public function delete(string $endpoint, array $options = []): HttpResponse
    {
        return $this->request('DELETE', $endpoint, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function buildRequest(
        string $method,
        string $endpoint,
        array $options,
    ): HttpRequest {
        $url =
            $this->baseUrl !== ''
                ? $this->baseUrl . '/' . ltrim($endpoint, '/')
                : $endpoint;

        $headers = array_merge(
            $this->defaults['headers'] ?? [],
            $options['headers'] ?? [],
        );

        return new HttpRequest(
            method: strtoupper($method),
            url: $url,
            headers: $headers,
            query: $options['query'] ?? [],
            body: $options['body'] ?? null,
            timeout: (int) ($options['timeout'] ?? $this->defaults['timeout']),
            connectTimeout: (int) ($options['connect_timeout'] ??
                $this->defaults['connect_timeout']),
            skipSsl: (bool) ($options['skip_ssl'] ??
                $this->defaults['skip_ssl']),
            followRedirects: (bool) ($options['follow_redirects'] ??
                $this->defaults['follow_redirects']),
            maxRedirects: (int) ($options['max_redirects'] ??
                $this->defaults['max_redirects']),
        );
    }

    private function send(HttpRequest $request): HttpResponse
    {
        $start = microtime(true);
        $result = $this->transport->send($request);
        $duration = (microtime(true) - $start) * 1000;

        $status = (int) ($result['status'] ?? 0);
        $headers = $result['headers'] ?? [];
        $body = $result['body'] ?? '';

        $decoded = json_decode((string) $body, true);
        $payload = json_last_error() === JSON_ERROR_NONE ? $decoded : $body;

        return new HttpResponse($status, $headers, $payload, $duration);
    }
}
