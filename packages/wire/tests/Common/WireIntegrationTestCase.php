<?php

declare(strict_types=1);

namespace Lalaz\Wire\Tests\Common;

use PHPUnit\Framework\TestCase;
use Lalaz\Wire\HttpClient\HttpClient;
use Lalaz\Wire\HttpClient\HttpClientBuilder;
use Lalaz\Wire\HttpClient\Transport\CurlTransport;

/**
 * Base test case for Wire integration tests.
 *
 * Integration tests make real HTTP requests and require network access.
 * These tests are skipped by default unless explicitly enabled.
 */
abstract class WireIntegrationTestCase extends TestCase
{
    protected ?HttpClient $client = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (!$this->shouldRunIntegrationTests()) {
            $this->markTestSkipped(
                'Integration tests are skipped. Set WIRE_INTEGRATION_TESTS=1 to run.'
            );
        }

        $this->client = $this->createClient();
    }

    protected function tearDown(): void
    {
        $this->client = null;
        parent::tearDown();
    }

    /**
     * Check if integration tests should run.
     */
    protected function shouldRunIntegrationTests(): bool
    {
        return getenv('WIRE_INTEGRATION_TESTS') === '1';
    }

    /**
     * Skip test if cURL extension is not available.
     */
    protected function skipIfCurlNotAvailable(): void
    {
        if (!extension_loaded('curl')) {
            $this->markTestSkipped('cURL extension is not available');
        }
    }

    /**
     * Create an HTTP client for integration tests.
     */
    protected function createClient(string $baseUrl = ''): HttpClient
    {
        return HttpClientBuilder::create($baseUrl)
            ->withTransport(new CurlTransport())
            ->timeout(10)
            ->connectTimeout(5)
            ->build();
    }

    /**
     * Get a test URL that responds with JSON.
     */
    protected function getJsonTestUrl(): string
    {
        return 'https://httpbin.org/json';
    }

    /**
     * Get a test URL that echoes request data.
     */
    protected function getEchoTestUrl(): string
    {
        return 'https://httpbin.org/anything';
    }

    /**
     * Get a test URL for specific status codes.
     */
    protected function getStatusTestUrl(int $status): string
    {
        return "https://httpbin.org/status/{$status}";
    }
}
