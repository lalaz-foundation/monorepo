<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Runtime\Http;

use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Lalaz\Web\Http\Contracts\MiddlewareInterface;
use Lalaz\Web\Http\Contracts\RequestFactoryInterface;
use Lalaz\Web\Http\Contracts\RequestInterface;
use Lalaz\Web\Http\Contracts\ResponseFactoryInterface;
use Lalaz\Web\Http\Contracts\ResponseInterface;
use Lalaz\Runtime\Http\HttpApplication;
use Psr\Log\LoggerInterface;
use Lalaz\Web\Http\Request;
use Lalaz\Web\Http\Response;
use Lalaz\Framework\Tests\Fixtures\Controllers\ConfigRouteController;
use Lalaz\Framework\Tests\Fixtures\Fakes\FakeResponseBodyEmitter;

class HttpApplicationTest extends FrameworkUnitTestCase
{
    public function testrunsASimpleGetRoute(): void
    {
        $app = new HttpApplication();
        $app->get("/", function (Response $response) {
            $response->json(["hello" => "world"], 200);
        });

        $request = new Request(
            [],
            [],
            [],
            null,
            ["Host" => "example.test"],
            [],
            [],
            "GET",
            ["REQUEST_URI" => "/", "REQUEST_METHOD" => "GET"],
        );
        $response = $app->handle($request, new Response("example.test"));

        $this->assertSame(["hello" => "world"], json_decode($response->body(), true));
    }

    public function testbindsAPsrLoggerByDefault(): void
    {
        // Logger is only registered when bootstrap() is called via boot() or create()
        $app = HttpApplication::create();
        $logger = $app->container()->resolve(LoggerInterface::class);

        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function testinjectsRouteParamsIntoHandlersAndRequest(): void
    {
        $app = new HttpApplication();
        $app->get("/users/{id}", function (
            string $id,
            Request $request,
            Response $response,
        ) {
            $response->setBody($id . ":" . $request->routeParam("id"));
        });

        $request = new Request(
            [],
            [],
            [],
            null,
            ["Host" => "example.test"],
            [],
            [],
            "GET",
            ["REQUEST_URI" => "/users/55", "REQUEST_METHOD" => "GET"],
        );

        $response = $app->handle($request, new Response("example.test"));

        $this->assertSame("55:55", trim($response->body()));
    }

    public function testrunsMiddlewaresInOrder(): void
    {
        $app = new HttpApplication();
        $order = [];

        $app->middleware(function (
            RequestInterface $request,
            ResponseInterface $response,
            callable $next,
        ) use (&$order) {
            $order[] = "global-before";
            $result = $next($request, $response);
            $order[] = "global-after";
            return $result;
        });

        $app->get(
            "/ping",
            function (Response $response) use (&$order) {
                $order[] = "handler";
                $response->setBody("pong");
            },
            [
                new class ($order) implements MiddlewareInterface {
                    private $log;

                    public function __construct(array &$log)
                    {
                        $this->log = &$log;
                    }

                    public function handle(
                        RequestInterface $request,
                        ResponseInterface $response,
                        callable $next,
                    ): mixed {
                        $this->log[] = "route-before";
                        $result = $next($request, $response);
                        $this->log[] = "route-after";
                        return $result;
                    }
                },
            ],
        );

        $request = new Request(
            [],
            [],
            [],
            null,
            ["Host" => "example.test"],
            [],
            [],
            "GET",
            ["REQUEST_URI" => "/ping", "REQUEST_METHOD" => "GET"],
        );

        $app->handle($request, new Response("example.test"));

        $this->assertSame([
            "global-before",
            "route-before",
            "handler",
            "route-after",
            "global-after",
        ], $order);
    }

    public function testloadsRoutesFromConfigFiles(): void
    {
        $tmp = sys_get_temp_dir() . "/lalaz_app_" . uniqid();
        mkdir($tmp . "/routes", 0777, true);

        $routeFile = $tmp . "/routes/web.php";
        file_put_contents(
            $routeFile,
            <<<'PHP'
            <?php
            use Lalaz\Web\Routing\Router;
            return function (Router $router): void {
                $router->get('/from-file', function (\Lalaz\Web\Http\Response $response) {
                    $response->setBody('loaded-from-file');
                });
            };
            PHP
            ,
        );

        $config = ["files" => [$routeFile]];
        $app = HttpApplication::boot($tmp, $config);

        $request = new Request(
            [],
            [],
            [],
            null,
            ["Host" => "example.test"],
            [],
            [],
            "GET",
            ["REQUEST_URI" => "/from-file", "REQUEST_METHOD" => "GET"],
        );

        $response = $app->handle($request, new Response("example.test"));

        $this->assertSame("loaded-from-file", trim($response->body()));

        // Cleanup
        @unlink($routeFile);
        @rmdir($tmp . "/routes");
        @rmdir($tmp);
    }

    public function testregistersAttributeControllersThroughConfig(): void
    {
        $this->assertSame(
            "Lalaz\\Framework\\Tests\\Fixtures\\Controllers\\ConfigRouteController",
            ConfigRouteController::class
        );

        $app = HttpApplication::create();
        $app->withControllers([ConfigRouteController::class]);

        $request = new Request(
            [],
            [],
            [],
            null,
            ["Host" => "example.test"],
            [],
            [],
            "GET",
            ["REQUEST_URI" => "/attr-config", "REQUEST_METHOD" => "GET"],
        );

        $response = $app->handle($request, new Response("example.test"));

        $this->assertNotEmpty($app->router()->all());
        $this->assertSame("attr", trim($response->body()));
    }

    public function testsupportsStreamingResponsesEndToEnd(): void
    {
        $app = new HttpApplication();
        $app->get("/stream", function (Response $response): void {
            $response->stream(function (callable $write): void {
                $write("chunk-a");
                $write("chunk-b");
            });
        });

        $request = new Request(
            [],
            [],
            [],
            null,
            ["Host" => "example.test"],
            [],
            [],
            "GET",
            ["REQUEST_URI" => "/stream", "REQUEST_METHOD" => "GET"],
        );

        $response = $app->handle($request, new Response("example.test"));

        $emitter = new FakeResponseBodyEmitter();
        $response->sendBody($emitter);

        $this->assertSame(["chunk-a", "chunk-b"], $emitter->chunks);
    }

    public function testusesInjectedRequestAndResponseFactoriesWhenOmitted(): void
    {
        $customRequest = new Request(
            [],
            [],
            [],
            null,
            ["Host" => "factory.test"],
            [],
            [],
            "GET",
            ["REQUEST_URI" => "/", "REQUEST_METHOD" => "GET"],
        );

        $requestFactory = new class ($customRequest) implements RequestFactoryInterface {
            public function __construct(private Request $request) {}

            public function fromGlobals(): RequestInterface
            {
                return $this->request;
            }
        };

        $responseFactory = new class implements ResponseFactoryInterface {
            public ?RequestInterface $captured = null;

            public function create(RequestInterface $request): ResponseInterface
            {
                $this->captured = $request;
                return new Response("factory.host");
            }
        };

        $app = new HttpApplication(
            null,
            null,
            null,
            false,
            null,
            $requestFactory,
            $responseFactory,
        );

        $app->get("/", function (Response $response): void {
            $response->setBody("factory-ok");
        });

        $response = $app->handle();

        $this->assertSame("factory-ok", $response->body());
        $this->assertSame($customRequest, $responseFactory->captured);
    }
}
