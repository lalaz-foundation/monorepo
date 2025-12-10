<?php

declare(strict_types=1);

namespace Lalaz\Wire\HttpClient;

use Lalaz\Wire\HttpClient\Contracts\TransportInterface;
use Lalaz\Wire\HttpClient\Transport\CurlTransport;

final class HttpClientBuilder
{
    private string $baseUrl = '';
    private ?TransportInterface $transport = null;
    private array $defaults = [];

    public static function create(string $baseUrl = ''): self
    {
        $instance = new self();
        $instance->baseUrl = rtrim($baseUrl, '/');
        return $instance;
    }

    public function withTransport(TransportInterface $transport): self
    {
        $this->transport = $transport;
        return $this;
    }

    public function withDefaults(array $defaults): self
    {
        $this->defaults = array_replace_recursive($this->defaults, $defaults);
        return $this;
    }

    public function baseHeaders(array $headers): self
    {
        $this->defaults['headers'] = $headers;
        return $this;
    }

    public function timeout(int $seconds): self
    {
        $this->defaults['timeout'] = $seconds;
        return $this;
    }

    public function connectTimeout(int $seconds): self
    {
        $this->defaults['connect_timeout'] = $seconds;
        return $this;
    }

    public function skipSsl(bool $enabled = true): self
    {
        $this->defaults['skip_ssl'] = $enabled;
        return $this;
    }

    public function build(): HttpClient
    {
        return new HttpClient(
            baseUrl: $this->baseUrl,
            transport: $this->transport ?? new CurlTransport(),
            defaults: $this->defaults,
        );
    }
}
