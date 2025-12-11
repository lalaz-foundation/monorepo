<?php

declare(strict_types=1);

namespace Lalaz\Runtime\Http;

use Lalaz\Config\Config;
use Lalaz\Container\Contracts\ContainerInterface;
use Lalaz\Container\Middlewares\ScopedContainerMiddleware;
use Lalaz\Container\ProviderRegistry;
use Lalaz\Container\ServiceProvider;
use Lalaz\Runtime\Application;
use Lalaz\Runtime\ApplicationBootstrap;
use Lalaz\Web\Http\Contracts\ExceptionHandlerInterface;
use Lalaz\Web\Http\Contracts\MiddlewareInterface;
use Lalaz\Web\Http\Contracts\RequestFactoryInterface;
use Lalaz\Web\Http\Contracts\ResponseEmitterInterface;
use Lalaz\Web\Http\Contracts\ResponseFactoryInterface;
use Lalaz\Web\Http\Request;
use Lalaz\Web\Http\Response;
use Lalaz\Web\Routing\Contracts\RouteRegistrarInterface;
use Lalaz\Web\Routing\Contracts\RouterInterface;

/**
 * High-level façade that wires the HTTP kernel, container, router and routing configuration.
 *
 * This class provides a unified API for configuring and running HTTP applications.
 * Internally, it delegates to specialized classes for better separation of concerns:
 * - ServiceBootstrapper: Container and service registration
 * - RoutingConfigurator: Route files, controllers, and cache management
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
class HttpApplication
{
    /**
     * Service bootstrapper for container and service management.
     *
     * @var ServiceBootstrapper
     */
    private ServiceBootstrapper $services;

    /**
     * Routing configuration manager.
     *
     * @var RoutingConfigurator
     */
    private RoutingConfigurator $routing;

    /**
     * HTTP kernel for request handling.
     *
     * @var HttpKernel
     */
    private HttpKernel $kernel;

    /**
     * Whether service providers have been booted.
     *
     * @var bool
     */
    private bool $providersBooted = false;

    /**
     * Application base path.
     *
     * @var string|null
     */
    private ?string $basePath = null;

    /**
     * Debug mode flag.
     *
     * @var bool
     */
    private bool $debug = false;

    /**
     * Global middleware stack.
     *
     * @var array<int, callable|string|MiddlewareInterface>
     */
    private array $globalMiddlewares = [];

    /**
     * Create a new HTTP application instance.
     *
     * @param ContainerInterface|null $container Optional DI container.
     * @param RouterInterface|null $router Optional router instance.
     * @param ExceptionHandlerInterface|null $exceptionHandler Optional exception handler.
     * @param bool $debug Whether to enable debug mode.
     * @param ResponseEmitterInterface|null $emitter Optional response emitter.
     * @param RequestFactoryInterface|null $requestFactory Optional request factory.
     * @param ResponseFactoryInterface|null $responseFactory Optional response factory.
     */
    public function __construct(
        ?ContainerInterface $container = null,
        ?RouterInterface $router = null,
        ?ExceptionHandlerInterface $exceptionHandler = null,
        bool $debug = false,
        ?ResponseEmitterInterface $emitter = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?ResponseFactoryInterface $responseFactory = null,
    ) {
        $this->debug = $debug;

        // Initialize service bootstrapper (but don't bootstrap yet - wait for basePath)
        $this->services = new ServiceBootstrapper(
            $container,
            $router,
            $exceptionHandler,
            $debug,
            $emitter,
            $requestFactory,
            $responseFactory,
        );

        // Initialize routing configurator
        $this->routing = new RoutingConfigurator($this->basePath);

        // Create kernel
        $this->kernel = new HttpKernel(
            $this->services->container(),
            $this->services->router(),
            $this->services->exceptionHandler(),
        );

        // Add scoped container middleware as the first middleware
        $this->middleware(new ScopedContainerMiddleware($this->services->container()));
    }

    // =========================================================================
    // Static Factory Methods
    // =========================================================================

    /**
     * Create a new HTTP application instance.
     *
     * @param bool $debug Whether to enable debug mode.
     * @return self
     */
    public static function create(bool $debug = false): self
    {
        $app = new self(null, null, null, $debug);

        // Bootstrap core services (logger, etc.)
        $app->services->bootstrap($app);

        // Register global application context
        $app->registerGlobalContext();

        return $app;
    }

    /**
     * Boot an HTTP application with the given base path.
     *
     * This is the main entry point for bootstrapping a full application.
     * It loads environment variables, router configuration, and service providers.
     *
     * @param string $basePath Application base directory path.
     * @param array<string, mixed>|null $routerConfig Optional router configuration.
     * @param bool $debug Whether to enable debug mode.
     * @return self
     */
    public static function boot(
        string $basePath,
        ?array $routerConfig = null,
        bool $debug = false,
    ): self {
        $app = new self(null, null, null, $debug);
        $app->basePath = rtrim($basePath, DIRECTORY_SEPARATOR);

        // Load environment and config FIRST
        ApplicationBootstrap::bootstrapEnvironment($app->basePath);

        // Bootstrap services now that basePath and config are set
        $app->services->bootstrap($app);

        // Re-create routing with basePath
        $app->routing = new RoutingConfigurator($app->basePath);

        $config = $routerConfig ?? $app->loadRouterConfig();

        if ($config !== null) {
            $app->applyRouterConfig($config);
        }

        ApplicationBootstrap::registerConfiguredProviders($app->services->providers());
        $app->providersBooted = true;

        // Register global application context
        $app->registerGlobalContext();

        return $app;
    }

    /**
     * Register this application as the global context.
     *
     * This makes the container and other services available via the
     * Application singleton for use by helper functions.
     *
     * @return void
     */
    private function registerGlobalContext(): void
    {
        $context = new Application(
            $this->services->container(),
            $this->basePath,
            $this->debug,
        );

        // Try to get EventDispatcher from container if available
        try {
            $events = $this->services->container()->resolve(
                \Lalaz\Events\Contracts\EventDispatcherInterface::class,
            );
            $context->setEvents($events);
        } catch (\Throwable) {
            // Events not registered, that's fine
        }

        Application::setInstance($context);
    }

    // =========================================================================
    // Accessors (Public API - unchanged)
    // =========================================================================

    /**
     * Get the dependency injection container.
     *
     * @return ContainerInterface
     */
    public function container(): ContainerInterface
    {
        return $this->services->container();
    }

    /**
     * Get the router instance.
     *
     * @return RouterInterface
     */
    public function router(): RouterInterface
    {
        return $this->services->router();
    }

    /**
     * Get the exception handler.
     *
     * @return ExceptionHandlerInterface
     */
    public function exceptionHandler(): ExceptionHandlerInterface
    {
        return $this->services->exceptionHandler();
    }

    /**
     * Get the request factory.
     *
     * @return RequestFactoryInterface
     */
    public function requestFactory(): RequestFactoryInterface
    {
        return $this->services->requestFactory();
    }

    /**
     * Get the response factory.
     *
     * @return ResponseFactoryInterface
     */
    public function responseFactory(): ResponseFactoryInterface
    {
        return $this->services->responseFactory();
    }

    /**
     * Get the HTTP kernel.
     *
     * @return HttpKernel
     */
    public function kernel(): HttpKernel
    {
        return $this->kernel;
    }

    /**
     * Get the application base path.
     *
     * @return string|null
     */
    public function basePath(): ?string
    {
        return $this->basePath;
    }

    /**
     * Set the application base path.
     *
     * @param string $basePath The application base directory.
     * @return self
     */
    public function setBasePath(string $basePath): self
    {
        $this->basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
        $this->routing = new RoutingConfigurator($this->basePath);
        return $this;
    }

    /**
     * Get the provider registry.
     *
     * @return ProviderRegistry The provider registry instance.
     */
    public function providers(): ProviderRegistry
    {
        return $this->services->providers();
    }

    // =========================================================================
    // Provider Management (Public API - unchanged)
    // =========================================================================

    /**
     * Register a service provider.
     *
     * @param string|ServiceProvider $provider Provider class name or instance.
     * @return self
     */
    public function registerProvider(string|ServiceProvider $provider): self
    {
        $this->services->providers()->register($provider);
        return $this;
    }

    /**
     * Boot all registered service providers.
     *
     * @return void
     */
    public function bootProviders(): void
    {
        $this->services->providers()->boot();
    }

    // =========================================================================
    // Middleware Management (Public API - unchanged)
    // =========================================================================

    /**
     * Add a global middleware.
     *
     * @param callable|string|MiddlewareInterface $middleware The middleware to add.
     * @return self
     */
    public function middleware($middleware): self
    {
        $this->globalMiddlewares[] = $middleware;
        return $this;
    }

    /**
     * Add multiple global middlewares.
     *
     * @param array<int, callable|string|MiddlewareInterface> $middlewares Middlewares to add.
     * @return self
     */
    public function withGlobalMiddlewares(array $middlewares): self
    {
        foreach ($middlewares as $middleware) {
            $this->middleware($middleware);
        }
        return $this;
    }

    // =========================================================================
    // Express-style Route Helpers (Public API - unchanged)
    // =========================================================================

    /**
     * Register a GET route.
     *
     * @param string $path The route path pattern.
     * @param callable|array|string $handler The route handler.
     * @param array<int, callable|string> $middlewares Route-specific middlewares.
     * @return void
     */
    public function get(string $path, $handler, array $middlewares = []): void
    {
        $this->services->router()->get($path, $handler, $middlewares);
    }

    /**
     * Register a POST route.
     *
     * @param string $path The route path pattern.
     * @param callable|array|string $handler The route handler.
     * @param array<int, callable|string> $middlewares Route-specific middlewares.
     * @return void
     */
    public function post(string $path, $handler, array $middlewares = []): void
    {
        $this->services->router()->post($path, $handler, $middlewares);
    }

    /**
     * Register a PUT route.
     *
     * @param string $path The route path pattern.
     * @param callable|array|string $handler The route handler.
     * @param array<int, callable|string> $middlewares Route-specific middlewares.
     * @return void
     */
    public function put(string $path, $handler, array $middlewares = []): void
    {
        $this->services->router()->put($path, $handler, $middlewares);
    }

    /**
     * Register a DELETE route.
     *
     * @param string $path The route path pattern.
     * @param callable|array|string $handler The route handler.
     * @param array<int, callable|string> $middlewares Route-specific middlewares.
     * @return void
     */
    public function delete(string $path, $handler, array $middlewares = []): void
    {
        $this->services->router()->delete($path, $handler, $middlewares);
    }

    /**
     * Register a route that matches any HTTP method.
     *
     * @param string $path The route path pattern.
     * @param callable|array|string $handler The route handler.
     * @param array<int, callable|string> $middlewares Route-specific middlewares.
     * @return void
     */
    public function any(string $path, $handler, array $middlewares = []): void
    {
        $this->services->router()->any($path, $handler, $middlewares);
    }

    // =========================================================================
    // Routing Configuration (Public API - unchanged, delegates to RoutingConfigurator)
    // =========================================================================

    /**
     * Add route files to load.
     *
     * @param array<int, string> $files Route file paths.
     * @return self
     */
    public function withRouteFiles(array $files): self
    {
        $this->routing->addRouteFiles($files);
        return $this;
    }

    /**
     * Add controller classes to register.
     *
     * @param array<int, class-string> $controllers Controller class names.
     * @return self
     */
    public function withControllers(array $controllers): self
    {
        $this->routing->addControllers($controllers);
        return $this;
    }

    /**
     * Add controller discovery paths.
     *
     * @param array<int, array<string, mixed>> $paths Discovery configuration.
     * @return self
     */
    public function withControllerDiscovery(array $paths): self
    {
        $this->routing->addControllerDiscovery($paths);
        return $this;
    }

    /**
     * Enable route caching.
     *
     * @param string $file Cache file path.
     * @param bool $autoWarm Whether to auto-warm cache.
     * @return self
     */
    public function enableRouteCache(string $file, bool $autoWarm = false): self
    {
        $this->routing->enableCache($file, $autoWarm);
        return $this;
    }

    /**
     * Register a custom route registrar.
     *
     * @param RouteRegistrarInterface $registrar The registrar to add.
     * @return self
     */
    public function registerRouteRegistrar(RouteRegistrarInterface $registrar): self
    {
        $this->routing->addRegistrar($registrar);
        return $this;
    }

    /**
     * Warm the routing configuration.
     *
     * Forces route registration without handling a request.
     *
     * @return void
     */
    public function warmRouting(): void
    {
        $this->routing->configure($this->services->router());
    }

    // =========================================================================
    // Request Handling (Public API - unchanged)
    // =========================================================================

    /**
     * Handle an HTTP request and return a response.
     *
     * @param Request|null $request Optional request instance.
     * @param Response|null $response Optional response instance.
     * @return Response The HTTP response.
     */
    public function handle(
        ?Request $request = null,
        ?Response $response = null,
    ): Response {
        $request ??= $this->services->requestFactory()->fromGlobals();
        $response ??= $this->services->responseFactory()->create($request);

        $this->routing->configure($this->services->router());

        return $this->kernel->handle(
            $request,
            $response,
            $this->globalMiddlewares,
        );
    }

    /**
     * Run the application.
     *
     * Handles the request from globals and emits the response.
     *
     * @return void
     */
    public function run(): void
    {
        // Short-circuit for static files on PHP built-in server
        if ($this->shouldBypassForStaticFile()) {
            http_response_code(404);
            return;
        }

        try {
            $request = $this->services->requestFactory()->fromGlobals();
            $response = $this->services->responseFactory()->create($request);
            $handled = $this->handle($request, $response);
            $this->services->emitter()->emit($handled);
        } catch (\Throwable $e) {
            $this->emergencyResponse($e);
        }
    }

    /**
     * Check if request should bypass framework for non-existent static files.
     *
     * When using PHP's built-in development server, requests that look like
     * static files (have a file extension) but don't exist on disk will
     * return 404 directly without going through the router.
     *
     * This prevents unnecessary framework bootstrapping for missing assets
     * like favicon.ico, missing images, etc.
     *
     * @return bool True if request should be bypassed with 404.
     */
    private function shouldBypassForStaticFile(): bool
    {
        if (PHP_SAPI !== 'cli-server') {
            return false;
        }

        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (!$uri || $uri === '/') {
            return false;
        }

        // If URI has a file extension, it's likely a static file request
        $extension = pathinfo($uri, PATHINFO_EXTENSION);
        if (!$extension) {
            return false;
        }

        // Static file with extension doesn't exist - bypass framework
        $file = ($this->basePath ?? getcwd()) . '/public' . $uri;
        return !is_file($file);
    }

    // =========================================================================
    // Configuration (Public API - unchanged)
    // =========================================================================

    /**
     * Apply router configuration from array.
     *
     * @param array<string, mixed> $config Router configuration array.
     * @return void
     */
    public function applyRouterConfig(array $config): void
    {
        if (isset($config['files']) && is_array($config['files'])) {
            $this->withRouteFiles($config['files']);
        }

        if (isset($config['controllers']) && is_array($config['controllers'])) {
            $this->withControllers($config['controllers']);
        }

        if (isset($config['discovery']) && is_array($config['discovery'])) {
            $enabled = (bool) ($config['discovery']['enabled'] ?? false);
            if ($enabled && isset($config['discovery']['paths'])) {
                $this->withControllerDiscovery($config['discovery']['paths']);
            }
        }

        if (isset($config['middlewares']) && is_array($config['middlewares'])) {
            $this->withGlobalMiddlewares($config['middlewares']);
        }

        if (isset($config['cache']) && is_array($config['cache'])) {
            $cacheConfig = $config['cache'];
            $enabled = !empty($cacheConfig['enabled']);
            if ($enabled && isset($cacheConfig['file'])) {
                $autoWarm = !empty($cacheConfig['auto_warm']);
                $this->enableRouteCache((string) $cacheConfig['file'], $autoWarm);
            }
        }
    }

    // =========================================================================
    // Private Methods
    // =========================================================================

    /**
     * Handle emergency/fatal error response.
     *
     * @param \Throwable $e The exception that caused the emergency.
     * @return void
     */
    private function emergencyResponse(\Throwable $e): void
    {
        error_log(sprintf(
            "[Lalaz Fatal] %s: %s in %s:%d\nStack trace:\n%s",
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString(),
        ));

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
        }

        if ($this->debug) {
            echo sprintf(
                "Fatal Error: %s\n\nFile: %s:%d\n\nTrace:\n%s",
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $e->getTraceAsString(),
            );
        } else {
            echo 'Internal Server Error';
        }
    }

    /**
     * Load router configuration from config files.
     *
     * The router configuration is loaded via Config::getArray('router').
     * This can come from either:
     * - A dedicated config/router.php file
     * - A 'router' key in config/app.php (auto-promoted by ConfigRepository)
     *
     * @return array<string, mixed>|null Router configuration or null if not found.
     */
    private function loadRouterConfig(): ?array
    {
        return Config::getArray('router', null);
    }
}
