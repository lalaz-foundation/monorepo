<?php

declare(strict_types=1);

namespace Lalaz\Wire;

use Lalaz\Container\ServiceProvider;
use Lalaz\Wire\HttpClient\HttpClient;
use Lalaz\Wire\HttpClient\HttpClientBuilder;
use Lalaz\Wire\HttpClient\Transport\CurlTransport;

/**
 * Service provider for the Wire package.
 */
final class WireServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->singleton(HttpClient::class, function () {
            return HttpClientBuilder::create()
                ->withTransport(new CurlTransport())
                ->build();
        });
    }
}
