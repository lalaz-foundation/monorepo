<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Common;

use Lalaz\Runtime\Http\HttpApplication;
use Lalaz\Web\Http\Request;
use Lalaz\Web\Http\Response;
use Lalaz\Framework\Tests\Fixtures\Fakes\FakeRequestFactory;
use Lalaz\Framework\Tests\Fixtures\Fakes\FakeResponseEmitter;
use Lalaz\Framework\Tests\Fixtures\Fakes\FakeResponseFactory;
use Lalaz\Framework\Tests\Fixtures\Factories\RequestFactory;
use PHPUnit\Framework\Assert;

/**
 * Trait for tests that need to make HTTP requests to the application.
 *
 * @package lalaz/framework
 */
trait MakesHttpRequests
{
    protected ?HttpApplication $app = null;
    protected ?FakeRequestFactory $requestFactory = null;
    protected ?FakeResponseFactory $responseFactory = null;
    protected ?FakeResponseEmitter $emitter = null;

    protected function setUpHttp(): void
    {
        $this->requestFactory = new FakeRequestFactory();
        $this->responseFactory = new FakeResponseFactory();
        $this->emitter = new FakeResponseEmitter();

        $this->app = new HttpApplication(
            requestFactory: $this->requestFactory,
            responseFactory: $this->responseFactory,
            emitter: $this->emitter,
        );
    }

    protected function tearDownHttp(): void
    {
        $this->app = null;
        $this->requestFactory = null;
        $this->responseFactory = null;
        $this->emitter = null;
    }

    protected function app(): HttpApplication
    {
        if ($this->app === null) {
            $this->setUpHttp();
        }

        return $this->app;
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, string> $headers
     */
    protected function get(string $uri, array $query = [], array $headers = []): Response
    {
        return $this->request(RequestFactory::get($uri, $query, $headers));
    }

    /**
     * @param array<string, mixed>|null $json
     * @param array<string, string> $headers
     */
    protected function postJson(string $uri, ?array $json = null, array $headers = []): Response
    {
        return $this->request(RequestFactory::postJson($uri, $json, $headers));
    }

    /**
     * @param array<string, mixed>|null $json
     * @param array<string, string> $headers
     */
    protected function putJson(string $uri, ?array $json = null, array $headers = []): Response
    {
        return $this->request(RequestFactory::putJson($uri, $json, $headers));
    }

    /**
     * @param array<string, mixed>|null $json
     * @param array<string, string> $headers
     */
    protected function patchJson(string $uri, ?array $json = null, array $headers = []): Response
    {
        return $this->request(RequestFactory::patchJson($uri, $json, $headers));
    }

    /**
     * @param array<string, string> $headers
     */
    protected function delete(string $uri, array $headers = []): Response
    {
        return $this->request(RequestFactory::delete($uri, $headers));
    }

    protected function request(Request $request): Response
    {
        return $this->app()->handle($request, new Response('localhost'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function responseJson(?Response $response = null): array
    {
        $body = ($response ?? $this->emitter?->lastResponse)?->body() ?? '';

        return json_decode($body, true) ?? [];
    }

    protected function assertStatus(int $expected, ?Response $response = null): void
    {
        $actual = ($response ?? $this->emitter?->lastResponse)?->getStatusCode();
        Assert::assertSame($expected, $actual);
    }

    protected function assertJson(array $expected, ?Response $response = null): void
    {
        $actual = $this->responseJson($response);
        foreach ($expected as $key => $value) {
            Assert::assertArrayHasKey($key, $actual);
            Assert::assertSame($value, $actual[$key]);
        }
    }
}
