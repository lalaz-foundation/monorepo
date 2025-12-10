<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Runtime\Http;

use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Lalaz\Runtime\Http\HttpApplication;
use Lalaz\Web\Http\Request;
use Lalaz\Web\Http\Response;
use Lalaz\Web\Http\JsonResponse;
use Lalaz\Web\Http\Contracts\RenderableInterface;
use Lalaz\Web\Http\Contracts\ResponseInterface;
use Stringable;

/**
 * Tests for HttpKernel return value processing.
 */
class HttpKernelReturnValueTest extends FrameworkUnitTestCase
{
    public function testprocessesRenderableInterfaceReturn(): void
    {
        $app = new HttpApplication();

        // Create a simple Renderable implementation inline
        $app->get("/renderable", function (): RenderableInterface {
            return new class implements RenderableInterface {
                public function toResponse(ResponseInterface $response): void
                {
                    $response->status(201);
                    $response->header('X-Custom', 'renderable');
                    $response->setBody('Rendered content');
                }
            };
        });

        $request = $this->createRequest("GET", "/renderable");
        $response = $app->handle($request, new Response("example.test"));

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('renderable', $response->headers()['X-Custom']);
        $this->assertSame('Rendered content', $response->body());
    }

    public function testprocessesJsonResponseReturn(): void
    {
        $app = new HttpApplication();

        $app->get("/json-response", function (): JsonResponse {
            return json(['status' => 'ok', 'data' => [1, 2, 3]], 201);
        });

        $request = $this->createRequest("GET", "/json-response");
        $response = $app->handle($request, new Response("example.test"));

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('application/json; charset=utf-8', $response->headers()['Content-Type']);
        $this->assertJsonStringEqualsJsonString(
            '{"status":"ok","data":[1,2,3]}',
            $response->body()
        );
    }

    public function testprocessesArrayReturnAsJson(): void
    {
        $app = new HttpApplication();

        $app->get("/array", function (): array {
            return ['name' => 'John', 'age' => 30];
        });

        $request = $this->createRequest("GET", "/array");
        $response = $app->handle($request, new Response("example.test"));

        $this->assertSame('application/json', $response->headers()['Content-Type']);
        $this->assertJsonStringEqualsJsonString(
            '{"name":"John","age":30}',
            $response->body()
        );
    }

    public function testprocessesStringReturnAsBody(): void
    {
        $app = new HttpApplication();

        $app->get("/string", function (): string {
            return '<h1>Hello World</h1>';
        });

        $request = $this->createRequest("GET", "/string");
        $response = $app->handle($request, new Response("example.test"));

        $this->assertSame('<h1>Hello World</h1>', $response->body());
    }

    public function testprocessesStringableObjectReturn(): void
    {
        $app = new HttpApplication();

        $app->get("/stringable", function (): Stringable {
            return new class implements Stringable {
                public function __toString(): string
                {
                    return 'Stringable content';
                }
            };
        });

        $request = $this->createRequest("GET", "/stringable");
        $response = $app->handle($request, new Response("example.test"));

        $this->assertSame('Stringable content', $response->body());
    }

    public function testprocessesVoidReturnCorrectly(): void
    {
        $app = new HttpApplication();

        $app->get("/void", function (Response $response): void {
            $response->status(204);
            $response->setBody('Set directly');
        });

        $request = $this->createRequest("GET", "/void");
        $response = $app->handle($request, new Response("example.test"));

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('Set directly', $response->body());
    }

    public function testprocessesNullReturnCorrectly(): void
    {
        $app = new HttpApplication();

        $app->get("/null", function (Response $response) {
            $response->setBody('Set before null return');
            return null;
        });

        $request = $this->createRequest("GET", "/null");
        $response = $app->handle($request, new Response("example.test"));

        $this->assertSame('Set before null return', $response->body());
    }

    public function testprocessesIntegerReturnAsString(): void
    {
        $app = new HttpApplication();

        $app->get("/int", function (): int {
            return 42;
        });

        $request = $this->createRequest("GET", "/int");
        $response = $app->handle($request, new Response("example.test"));

        $this->assertSame('42', $response->body());
    }

    public function testprocessesFloatReturnAsString(): void
    {
        $app = new HttpApplication();

        $app->get("/float", function (): float {
            return 3.14159;
        });

        $request = $this->createRequest("GET", "/float");
        $response = $app->handle($request, new Response("example.test"));

        $this->assertSame('3.14159', $response->body());
    }

    public function testprocessesBooleanReturnAsString(): void
    {
        $app = new HttpApplication();

        $app->get("/bool", function (): bool {
            return true;
        });

        $request = $this->createRequest("GET", "/bool");
        $response = $app->handle($request, new Response("example.test"));

        $this->assertSame('1', $response->body());
    }

    public function testjsonResponseWithMethodChaining(): void
    {
        $app = new HttpApplication();

        $app->get("/json-chained", function (): JsonResponse {
            return json(['initial' => true])
                ->with('extra', 'data')
                ->status(202)
                ->header('X-Chained', 'yes');
        });

        $request = $this->createRequest("GET", "/json-chained");
        $response = $app->handle($request, new Response("example.test"));

        $this->assertSame(202, $response->getStatusCode());
        $this->assertSame('yes', $response->headers()['X-Chained']);
        $this->assertJsonStringEqualsJsonString(
            '{"initial":true,"extra":"data"}',
            $response->body()
        );
    }

    public function testbackwardsCompatibilityWithResponseJsonMethod(): void
    {
        $app = new HttpApplication();

        // Old style - using $response->json() directly
        $app->get("/old-style", function (Response $response): void {
            $response->json(['old' => 'style'], 200);
        });

        $request = $this->createRequest("GET", "/old-style");
        $response = $app->handle($request, new Response("example.test"));

        $this->assertSame('application/json', $response->headers()['Content-Type']);
        $this->assertJsonStringEqualsJsonString(
            '{"old":"style"}',
            $response->body()
        );
    }

    public function testcanMixReturnStylesInDifferentRoutes(): void
    {
        $app = new HttpApplication();

        // Return style
        $app->get("/return-style", function (): array {
            return ['style' => 'return'];
        });

        // Direct manipulation style
        $app->get("/direct-style", function (Response $response): void {
            $response->json(['style' => 'direct']);
        });

        $returnRequest = $this->createRequest("GET", "/return-style");
        $returnResponse = $app->handle($returnRequest, new Response("example.test"));

        $directRequest = $this->createRequest("GET", "/direct-style");
        $directResponse = $app->handle($directRequest, new Response("example.test"));

        $this->assertJsonStringEqualsJsonString(
            '{"style":"return"}',
            $returnResponse->body()
        );
        $this->assertJsonStringEqualsJsonString(
            '{"style":"direct"}',
            $directResponse->body()
        );
    }

    /**
     * Create a test request.
     */
    private function createRequest(string $method, string $uri): Request
    {
        return new Request(
            [],
            [],
            [],
            null,
            ["Host" => "example.test"],
            [],
            [],
            $method,
            ["REQUEST_URI" => $uri, "REQUEST_METHOD" => $method],
        );
    }
}
