<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Integration;

use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Lalaz\Config\Config;
use Lalaz\Container\ServiceProvider;
use Lalaz\Runtime\Http\ExceptionHandler;
use Lalaz\Runtime\Http\HttpApplication;
use Lalaz\Runtime\Http\Providers\ExceptionHandlerProvider;
use Lalaz\Web\Http\Request;
use Lalaz\Web\Http\Response;
use Lalaz\Framework\Tests\Fixtures\Controllers\ConfigRouteController;
use Lalaz\Framework\Tests\Fixtures\Fakes\FakeRequestFactory;
use Lalaz\Framework\Tests\Fixtures\Fakes\FakeResponseEmitter;
use Lalaz\Framework\Tests\Fixtures\Fakes\FakeResponseFactory;
use Lalaz\Framework\Tests\Fixtures\Factories\RouteFileFactory;

class IntegrationException extends \RuntimeException {}

class IntegrationExceptionRenderer implements \Lalaz\Web\Http\Contracts\ExceptionRendererInterface
{
    public function canRender(\Throwable $e, Request $request): bool
    {
        return $e instanceof IntegrationException;
    }

    public function render(\Throwable $e, Request $request): \Lalaz\Exceptions\ExceptionResponse
    {
        return new \Lalaz\Exceptions\ExceptionResponse(
            555,
            [],
            "integration-rendered",
            false,
        );
    }
}

class IntegrationTestClient
{
    public static function dispatch(
        HttpApplication $app,
        string $uri,
        string $method,
        ?array $json = null,
    ): array {
        return json_decode(
            self::dispatchWithResponse($app, $uri, $method, $json)->body(),
            true,
        ) ?? [];
    }

    public static function dispatchWithEmitter(
        HttpApplication $app,
        string $uri,
        string $method,
        ?array $json = null,
        bool $forceException = false,
    ): array {
        $response = self::dispatchWithResponse(
            $app,
            $uri,
            $method,
            $json,
            $forceException,
        );
        $emitter = new FakeResponseEmitter();
        $emitter->emit($response);
        return json_decode($response->body(), true) ?? [];
    }

    private static function dispatchWithResponse(
        HttpApplication $app,
        string $uri,
        string $method,
        ?array $json = null,
        bool $forceException = false,
    ): Response {
        $payload = $json !== null ? json_encode($json, JSON_THROW_ON_ERROR) : null;

        $request = new Request(
            [],
            [],
            [],
            $payload,
            ["Host" => "example.test", "Content-Type" => "application/json"],
            [],
            [],
            strtoupper($method),
            ["REQUEST_URI" => $uri, "REQUEST_METHOD" => strtoupper($method)],
        );

        if ($forceException) {
            try {
                return $app->handle($request, new Response("example.test"));
            } catch (\Throwable $e) {
                $response = new Response("example.test");
                $app->exceptionHandler()->render($e, $request, $response);
                return $response;
            }
        }

        return $app->handle($request, new Response("example.test"));
    }
}

class IntegrationProviderOrder
{
    /** @var array<int, string> */
    public static array $events = [];
}

class PrimaryTrackingProvider extends ServiceProvider
{
    public function register(): void
    {
        IntegrationProviderOrder::$events[] = "primary-register";
        $this->instance("integration.sequence", ["primary"]);
    }

    public function boot(): void
    {
        IntegrationProviderOrder::$events[] = "primary-boot";
    }
}

class SecondaryTrackingProvider extends ServiceProvider
{
    public function register(): void
    {
        IntegrationProviderOrder::$events[] = "secondary-register";
        $sequence = $this->container->resolve("integration.sequence");

        if (!is_array($sequence) || $sequence !== ["primary"]) {
            throw new \RuntimeException(
                "Primary provider must register before secondary.",
            );
        }

        $sequence[] = "secondary";
        $this->instance("integration.sequence", $sequence);
    }

    public function boot(): void
    {
        IntegrationProviderOrder::$events[] = "secondary-boot";
    }
}

class MisconfiguredRendererStub {}

class MisconfiguredReporterStub {}

class IntegrationMiddlewareTracker implements \Lalaz\Web\Http\Contracts\MiddlewareInterface
{
    /** @var array<int, string> */
    public static array $calls = [];

    public function __construct(private string $name) {}

    public static function reset(): void
    {
        self::$calls = [];
    }

    public static function log(string $label): void
    {
        self::$calls[] = $label;
    }

    public function handle(
        \Lalaz\Web\Http\Contracts\RequestInterface $request,
        \Lalaz\Web\Http\Contracts\ResponseInterface $response,
        callable $next,
    ): mixed {
        self::$calls[] = $this->name . ":before";
        $result = $next($request, $response);
        self::$calls[] = $this->name . ":after";
        return $result;
    }
}

class BootTrackingProvider extends ServiceProvider
{
    /** @var array<int, string> */
    public static array $events = [];

    public static function reset(): void
    {
        self::$events = [];
    }

    public function register(): void
    {
        self::$events[] = "register";
        $this->instance("boot.provider.flag", "booted");
    }

    public function boot(): void
    {
        self::$events[] = "boot";
    }
}

class HttpApplicationIntegrationTest extends FrameworkUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::setConfig("errors", []);

        // Configure logging to use console only (no file writes needed)
        Config::setConfig("logging", [
            "default" => "console",
            "channels" => [
                "console" => [
                    "driver" => "console",
                    "level" => "debug",
                    "formatter" => "text",
                ],
            ],
        ]);
    }

    public function testbootsHandlesRoutesAndEmitsResponsesEndToEnd(): void
    {
        $requestFactory = new FakeRequestFactory();
        $responseFactory = new FakeResponseFactory();
        $emitter = new FakeResponseEmitter();

        $app = new HttpApplication(
            requestFactory: $requestFactory,
            responseFactory: $responseFactory,
            emitter: $emitter,
        );

        $routeFile = RouteFileFactory::simple();
        $app->withRouteFiles([$routeFile]);
        $app->withControllers([ConfigRouteController::class]);

        $requestFactory->next = new Request(
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

        $app->run();

        $this->assertNotNull($emitter->lastResponse);
        $this->assertSame("from-file", trim($emitter->chunks[0] ?? ""));

        @unlink($routeFile);
    }

    public function testwarmsAndReloadsRoutesFromCacheUsingDiscovery(): void
    {
        $cachePath = sys_get_temp_dir() . "/lalaz_routes_" . uniqid() . ".php";

        $app = new HttpApplication();
        $app->withControllerDiscovery([
            [
                "namespace" => "Lalaz\\Framework\\Tests\\Fixtures\\Controllers",
                "path" => __DIR__ . "/../Fixtures/Controllers",
                "pattern" => "*Controller.php",
            ],
        ]);
        $app->enableRouteCache($cachePath, autoWarm: true);

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

        $app->handle($request, new Response("example.test"));
        $this->assertTrue(is_file($cachePath));

        $fresh = new HttpApplication();
        $fresh->enableRouteCache($cachePath);
        $response = $fresh->handle($request, new Response("example.test"));

        $this->assertSame("attr", trim($response->body()));

        @unlink($cachePath);
    }

    public function testrunsGlobalAndRouteMiddlewaresInOrderEndToEnd(): void
    {
        $log = [];
        $app = new HttpApplication();

        $app->middleware(function (
            Request $request,
            Response $response,
            callable $next,
        ) use (&$log): mixed {
            $log[] = "global-before";
            $result = $next($request, $response);
            $log[] = "global-after";
            return $result;
        });

        $routeMiddlewares = [
            function (Request $request, Response $response, callable $next) use (&$log): mixed {
                $log[] = "route-before";
                $result = $next($request, $response);
                $log[] = "route-after";
                return $result;
            },
            function (Request $request, Response $response, callable $next) use (&$log): mixed {
                $log[] = "route-2-before";
                $result = $next($request, $response);
                $log[] = "route-2-after";
                return $result;
            },
        ];

        $app->get(
            "/middleware-stack",
            function (Response $response) use (&$log): void {
                $log[] = "handler";
                $response->json(["log" => $log]);
            },
            $routeMiddlewares,
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
            ["REQUEST_URI" => "/middleware-stack", "REQUEST_METHOD" => "GET"],
        );

        $app->handle($request, new Response("example.test"));

        $this->assertSame([
            "global-before",
            "route-before",
            "route-2-before",
            "handler",
            "route-2-after",
            "route-after",
            "global-after",
        ], $log);
    }

    public function testallowsRouteMiddlewareToShortCircuitHandlers(): void
    {
        $app = new HttpApplication();
        $hitHandler = false;

        $app->get(
            "/guarded",
            function (Response $response) use (&$hitHandler): void {
                $hitHandler = true;
                $response->json(["message" => "should not run"]);
            },
            [
                function (Request $request, Response $response): void {
                    $response->json(["message" => "blocked by middleware"]);
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
            ["REQUEST_URI" => "/guarded", "REQUEST_METHOD" => "GET"],
        );

        $response = $app->handle($request, new Response("example.test"));
        $payload = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertFalse($hitHandler);
        $this->assertSame("blocked by middleware", $payload["message"] ?? null);
    }

    public function testcombinesRouteFilesDiscoveryAndCacheWhilePreservingProviderOrder(): void
    {
        $cachePath = sys_get_temp_dir() . "/lalaz_routes_" . uniqid() . ".php";
        $routeFile = RouteFileFactory::simple();

        IntegrationProviderOrder::$events = [];

        $app = new HttpApplication();
        $app->withRouteFiles([$routeFile]);
        $app->withControllers([ConfigRouteController::class]);
        $app->withControllerDiscovery([
            [
                "namespace" => "Lalaz\\Framework\\Tests\\Fixtures\\Controllers",
                "path" => __DIR__ . "/../Fixtures/Controllers",
                "pattern" => "*Controller.php",
            ],
        ]);
        $app->enableRouteCache($cachePath, autoWarm: true);

        $app->registerProvider(new PrimaryTrackingProvider($app->container()));
        $app->registerProvider(new SecondaryTrackingProvider($app->container()));
        $app->bootProviders();

        $fileRequest = new Request(
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

        $attrRequest = new Request(
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

        $fileResponse = $app->handle($fileRequest, new Response("example.test"));
        $attributeResponse = $app->handle($attrRequest, new Response("example.test"));

        $this->assertSame("from-file", trim($fileResponse->body()));
        $this->assertSame("attr", trim($attributeResponse->body()));
        $this->assertTrue(is_file($cachePath));
        $this->assertSame([
            "primary-register",
            "secondary-register",
            "primary-boot",
            "secondary-boot",
        ], IntegrationProviderOrder::$events);

        $sequence = $app->container()->resolve("integration.sequence");
        $this->assertSame(["primary", "secondary"], $sequence);

        $cached = new HttpApplication();
        $cached->enableRouteCache($cachePath);
        $cachedResponse = $cached->handle($attrRequest, new Response("example.test"));

        $this->assertSame("attr", trim($cachedResponse->body()));

        @unlink($routeFile);
        @unlink($cachePath);
    }

    public function testemitsStatusCodesAndHeadersViaTheConfiguredEmitter(): void
    {
        $requestFactory = new FakeRequestFactory();
        $responseFactory = new FakeResponseFactory();
        $emitter = new FakeResponseEmitter();

        $app = new HttpApplication(
            requestFactory: $requestFactory,
            responseFactory: $responseFactory,
            emitter: $emitter,
        );

        $app->withControllers([ConfigRouteController::class]);

        $requestFactory->next = new Request(
            [],
            [],
            [],
            json_encode(["name" => "Jane"], JSON_THROW_ON_ERROR),
            [
                "Host" => "example.test",
                "Content-Type" => "application/json",
                "Accept" => "application/json",
            ],
            [],
            [],
            "POST",
            ["REQUEST_URI" => "/attr-config", "REQUEST_METHOD" => "POST"],
        );

        $app->run();

        $this->assertCount(1, $emitter->emissions);
        $this->assertSame(200, $emitter->emissions[0]["status"]);
        $this->assertSame("application/json", $emitter->emissions[0]["headers"]["Content-Type"] ?? null);

        $requestFactory->next = new Request(
            [],
            [],
            [],
            null,
            [
                "Host" => "example.test",
                "Accept" => "application/json",
            ],
            [],
            [],
            "POST",
            ["REQUEST_URI" => "/attr-config/10", "REQUEST_METHOD" => "POST"],
        );

        $app->run();

        $this->assertCount(2, $emitter->emissions);
        $this->assertSame(405, $emitter->emissions[1]["status"]);
        $this->assertSame("application/json", $emitter->emissions[1]["headers"]["Content-Type"] ?? null);

        $allowedHeader = $emitter->emissions[1]["headers"]["Allow"] ?? "";
        $allowedMethods = array_values(
            array_filter(array_map("trim", explode(",", $allowedHeader))),
        );
        sort($allowedMethods);

        $this->assertSame(["DELETE", "PATCH", "PUT"], $allowedMethods);

        $errorPayload = json_decode(
            $emitter->emissions[1]["body"],
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->assertArrayHasKey("error", $errorPayload);
        $this->assertTrue($errorPayload["error"]);
        $this->assertSame(405, $errorPayload["statusCode"]);
        $this->assertSame("Method not allowed.", $errorPayload["message"]);
    }

    public function testbootsHttpApplicationUsingDiskConfigurationAndProviders(): void
    {
        BootTrackingProvider::reset();

        $basePath = sys_get_temp_dir() . "/lalaz_boot_" . uniqid("", true);
        $configDir = $basePath . "/config";
        $routesDir = $basePath . "/routes";
        $storageCacheDir = $basePath . "/storage/cache";

        $cleanup = static function () use ($basePath): void {
            if (!is_dir($basePath)) {
                return;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $basePath,
                    \FilesystemIterator::SKIP_DOTS,
                ),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );

            foreach ($iterator as $file) {
                if ($file->isDir()) {
                    @rmdir($file->getPathname());
                    continue;
                }

                @unlink($file->getPathname());
            }

            @rmdir($basePath);
        };

        try {
            @mkdir($configDir, 0777, true);
            @mkdir($routesDir, 0777, true);
            @mkdir($storageCacheDir, 0777, true);

            file_put_contents($basePath . "/.env", "APP_NAME=BootIntegration\n");

            file_put_contents(
                $routesDir . "/web.php",
                <<<'PHP'
                <?php
                use Lalaz\Web\Routing\Router;
                return function (Router $router): void {
                    $router->get('/boot-file', function (\Lalaz\Web\Http\Response $response): void {
                        $response->setBody('booted-from-file');
                    });
                };
                PHP
                ,
            );

            $routerConfig = [
                "files" => ["routes/web.php"],
                "controllers" => [ConfigRouteController::class],
                "discovery" => ["enabled" => false],
                "cache" => [
                    "enabled" => true,
                    "file" => "storage/cache/routes.php",
                    "auto_warm" => true,
                ],
            ];
            file_put_contents(
                $configDir . "/router.php",
                "<?php return " . var_export($routerConfig, true) . ";",
            );

            $providersConfig = [
                "providers" => [BootTrackingProvider::class],
            ];
            file_put_contents(
                $configDir . "/providers.php",
                "<?php return " . var_export($providersConfig, true) . ";",
            );

            $appConfig = ["timezone" => "UTC", "name" => "BootIntegration"];
            file_put_contents(
                $configDir . "/app.php",
                "<?php return " . var_export($appConfig, true) . ";",
            );

            $app = HttpApplication::boot($basePath);

            $fileRequest = new Request(
                [],
                [],
                [],
                null,
                ["Host" => "example.test"],
                [],
                [],
                "GET",
                ["REQUEST_URI" => "/boot-file", "REQUEST_METHOD" => "GET"],
            );
            $fileResponse = $app->handle($fileRequest, new Response("example.test"));

            $controllerRequest = new Request(
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
            $controllerResponse = $app->handle(
                $controllerRequest,
                new Response("example.test"),
            );

            $this->assertSame("booted-from-file", trim($fileResponse->body()));
            $this->assertSame("attr", trim($controllerResponse->body()));

            $cacheFile = $basePath . "/storage/cache/routes.php";
            $this->assertTrue(is_file($cacheFile));

            $this->assertSame(["register", "boot"], BootTrackingProvider::$events);
            $this->assertSame("booted", $app->container()->resolve("boot.provider.flag"));
        } finally {
            Config::setConfig("router", []);
            Config::setConfig("providers", []);
            Config::setConfig("app", []);
            $cleanup();
        }
    }

    public function testfailsFastWhenExceptionRendererConfigurationIsInvalid(): void
    {
        Config::setConfig("errors", [
            "renderers" => [MisconfiguredRendererStub::class],
            "reporters" => [],
        ]);

        $handler = new ExceptionHandler(true);
        $app = new HttpApplication(exceptionHandler: $handler);
        $app->container()->instance(\Lalaz\Web\Http\Contracts\ExceptionHandlerInterface::class, $handler);
        $provider = new ExceptionHandlerProvider($app->container());

        $this->expectException(\Lalaz\Exceptions\ConfigurationException::class);
        $provider->register();
    }

    public function testfallsBackToGetHandlersForHeadRequestsAndHonorsMiddlewareOrder(): void
    {
        IntegrationMiddlewareTracker::reset();

        $requestFactory = new FakeRequestFactory();
        $responseFactory = new FakeResponseFactory();
        $emitter = new FakeResponseEmitter();

        $app = new HttpApplication(
            requestFactory: $requestFactory,
            responseFactory: $responseFactory,
            emitter: $emitter,
        );

        $app->middleware(new IntegrationMiddlewareTracker("global"));

        $app->get(
            "/middleware-head",
            function (Request $request, Response $response): void {
                IntegrationMiddlewareTracker::log("handler");
                $response->setBody("head-fallback:" . $request->method());
            },
            [new IntegrationMiddlewareTracker("route")],
        );

        $requestFactory->next = new Request(
            [],
            [],
            [],
            null,
            ["Host" => "example.test"],
            [],
            [],
            "HEAD",
            ["REQUEST_URI" => "/middleware-head", "REQUEST_METHOD" => "HEAD"],
        );

        $app->run();

        $this->assertCount(1, $emitter->emissions);
        $this->assertSame(200, $emitter->emissions[0]["status"]);
        $this->assertSame("head-fallback:HEAD", trim($emitter->chunks[0] ?? ""));

        $this->assertSame([
            "global:before",
            "route:before",
            "handler",
            "route:after",
            "global:after",
        ], IntegrationMiddlewareTracker::$calls);
    }

    public function teststreamsAndDownloadsResponsesThroughTheEmitterEndToEnd(): void
    {
        $requestFactory = new FakeRequestFactory();
        $responseFactory = new FakeResponseFactory();
        $emitter = new FakeResponseEmitter();

        $app = new HttpApplication(
            requestFactory: $requestFactory,
            responseFactory: $responseFactory,
            emitter: $emitter,
        );

        $app->get("/stream-chunks", function (Response $response): void {
            $response->stream(function (callable $write): void {
                $write("chunk-1");
                $write("chunk-2");
            }, headers: ["Content-Type" => "text/plain"]);
        });

        $tmpFile = tempnam(sys_get_temp_dir(), "lalaz_download_");
        $this->assertNotFalse($tmpFile);
        $tmpFile = (string) $tmpFile;
        file_put_contents($tmpFile, "download-body");

        $app->get("/download-file", function (Response $response) use ($tmpFile): void {
            $response->download($tmpFile, "example.txt");
        });

        $requestFactory->next = new Request(
            [],
            [],
            [],
            null,
            ["Host" => "example.test"],
            [],
            [],
            "GET",
            ["REQUEST_URI" => "/stream-chunks", "REQUEST_METHOD" => "GET"],
        );
        $app->run();

        $this->assertCount(1, $emitter->emissions);
        $this->assertSame("text/plain", $emitter->emissions[0]["headers"]["Content-Type"] ?? null);
        $this->assertSame(["chunk-1", "chunk-2"], $emitter->chunks);

        $requestFactory->next = new Request(
            [],
            [],
            [],
            null,
            ["Host" => "example.test"],
            [],
            [],
            "GET",
            ["REQUEST_URI" => "/download-file", "REQUEST_METHOD" => "GET"],
        );
        $app->run();

        $this->assertCount(2, $emitter->emissions);
        $this->assertSame(
            'attachment; filename="example.txt"',
            $emitter->emissions[1]["headers"]["Content-Disposition"] ?? null
        );
        $this->assertSame(
            strlen("download-body"),
            (int) ($emitter->emissions[1]["headers"]["Content-Length"] ?? -1)
        );
        $this->assertSame("download-body", end($emitter->chunks));

        @unlink($tmpFile);
    }

    public function testfailsFastWhenExceptionReporterConfigurationIsInvalid(): void
    {
        Config::setConfig("errors", [
            "renderers" => [],
            "reporters" => [MisconfiguredReporterStub::class],
        ]);

        $handler = new ExceptionHandler(true);
        $app = new HttpApplication(exceptionHandler: $handler);
        $app->container()->instance(\Lalaz\Web\Http\Contracts\ExceptionHandlerInterface::class, $handler);
        $provider = new ExceptionHandlerProvider($app->container());

        $this->expectException(\Lalaz\Exceptions\ConfigurationException::class);
        $provider->register();
    }

    public function testinvokesCustomExceptionRenderersAndHandlesAllVerbs(): void
    {
        Config::setConfig("errors", [
            "renderers" => [IntegrationExceptionRenderer::class],
            "reporters" => [],
        ]);

        $handler = new ExceptionHandler(true);
        $app = new HttpApplication(exceptionHandler: $handler);
        $app->container()->instance(\Lalaz\Web\Http\Contracts\ExceptionHandlerInterface::class, $handler);
        $app->withControllers([ConfigRouteController::class]);
        $provider = new ExceptionHandlerProvider($app->container());
        $provider->register();

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

        $response = new Response("example.test");
        $app->exceptionHandler()->render(
            new IntegrationException("boom"),
            $request,
            $response,
        );

        $this->assertSame("integration-rendered", $response->body());

        // Exercise POST, PUT, DELETE routes to ensure verbs and payload parsing work.
        $postResponse = IntegrationTestClient::dispatch($app, "/attr-config", "POST", ["name" => "John"]);
        $this->assertSame("POST", $postResponse["method"]);
        $this->assertSame(["name" => "John"], $postResponse["payload"]);

        $putResponse = IntegrationTestClient::dispatch($app, "/attr-config/10", "PUT", ["email" => "a@b"]);
        $this->assertSame("PUT", $putResponse["method"]);
        $this->assertSame("10", $putResponse["id"]);
        $this->assertSame(["email" => "a@b"], $putResponse["payload"]);

        $deleteResponse = IntegrationTestClient::dispatch($app, "/attr-config/10", "DELETE");
        $this->assertSame("DELETE", $deleteResponse["method"]);
        $this->assertSame("10", $deleteResponse["id"]);

        $patchResponse = IntegrationTestClient::dispatch($app, "/attr-config/10", "PATCH", ["phone" => "999"]);
        $this->assertSame("PATCH", $patchResponse["method"]);
        $this->assertSame("10", $patchResponse["id"]);
        $this->assertSame(["phone" => "999"], $patchResponse["payload"]);

        $optionsResponse = IntegrationTestClient::dispatch($app, "/attr-config", "OPTIONS");
        $this->assertSame("OPTIONS", $optionsResponse["method"]);

        $validationResponse = IntegrationTestClient::dispatch($app, "/attr-config", "POST", ["name" => ""]);
        $this->assertArrayHasKey("statusCode", $validationResponse);
        $this->assertSame(422, $validationResponse["statusCode"]);

        $emitter = new FakeResponseEmitter();
        $invalidResponse = new Response("example.test");
        $app->exceptionHandler()->render(
            new IntegrationException("boom"),
            $request,
            $invalidResponse,
        );
        $emitter->emit($invalidResponse);
        $this->assertCount(1, $emitter->emissions);
        $this->assertSame(555, $emitter->emissions[0]["status"]);
        $this->assertContains("integration-rendered", $emitter->chunks);

        $methodNotAllowed = IntegrationTestClient::dispatchWithEmitter($app, "/attr-config/10", "POST", []);
        $this->assertSame(405, $methodNotAllowed["statusCode"] ?? null);

        $frameworkRequest = new Request(
            [],
            [],
            [],
            null,
            ["Host" => "example.test"],
            [],
            [],
            "GET",
            ["REQUEST_URI" => "/framework-error", "REQUEST_METHOD" => "GET"],
        );
        $frameworkResponse = $app->handle($frameworkRequest, new Response("example.test"));
        $frameworkEmitter = new FakeResponseEmitter();
        $frameworkEmitter->emit($frameworkResponse);
        $this->assertSame(500, $frameworkEmitter->emissions[0]["status"] ?? null);

        $missingRoute = IntegrationTestClient::dispatchWithEmitter($app, "/missing-route", "GET", null, true);
        $this->assertSame(404, $missingRoute["statusCode"] ?? null);
    }
}
