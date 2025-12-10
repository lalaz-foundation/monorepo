<?php

declare(strict_types=1);

namespace Lalaz\Runtime\Http;

use Lalaz\Config\Config;
use Lalaz\Container\Container;
use Lalaz\Container\Contracts\ContainerInterface;
use Lalaz\Container\Middlewares\ScopedContainerMiddleware;
use Lalaz\Container\ProviderRegistry;
use Lalaz\Logging\Log;
use Lalaz\Logging\LogManager;
use Lalaz\Runtime\Http\Emitter\SapiResponseEmitter;
use Lalaz\Runtime\Http\Request\SapiRequestFactory;
use Lalaz\Runtime\Http\Response\SimpleResponseFactory;
use Lalaz\Web\Http\Contracts\ExceptionHandlerInterface;
use Lalaz\Web\Http\Contracts\RequestFactoryInterface;
use Lalaz\Web\Http\Contracts\ResponseEmitterInterface;
use Lalaz\Web\Http\Contracts\ResponseFactoryInterface;
use Lalaz\Web\Routing\Contracts\RouterInterface;
use Lalaz\Web\Routing\Router;
use Psr\Log\LoggerInterface;

/**
 * Bootstraps core services in the container.
 *
 * This class is an internal implementation detail of HttpApplication.
 * It encapsulates the setup of container bindings and core services
 * including the DI container, router, exception handler, and logger.
 *
 * @internal
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
final class ServiceBootstrapper
{
    /**
     * The dependency injection container.
     *
     * @var ContainerInterface
     */
    private ContainerInterface $container;

    /**
     * The router instance.
     *
     * @var RouterInterface
     */
    private RouterInterface $router;

    /**
     * The exception handler.
     *
     * @var ExceptionHandlerInterface
     */
    private ExceptionHandlerInterface $exceptionHandler;

    /**
     * Service provider registry.
     *
     * @var ProviderRegistry
     */
    private ProviderRegistry $providers;

    /**
     * Response emitter for sending responses.
     *
     * @var ResponseEmitterInterface
     */
    private ResponseEmitterInterface $emitter;

    /**
     * Factory for creating request objects.
     *
     * @var RequestFactoryInterface
     */
    private RequestFactoryInterface $requestFactory;

    /**
     * Factory for creating response objects.
     *
     * @var ResponseFactoryInterface
     */
    private ResponseFactoryInterface $responseFactory;

    /**
     * Create a new service bootstrapper instance.
     *
     * @param ContainerInterface|null $container Optional DI container.
     * @param RouterInterface|null $router Optional router instance.
     * @param ExceptionHandlerInterface|null $exceptionHandler Optional exception handler.
     * @param bool $debug Whether debug mode is enabled.
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
        $this->container = $container ?? new Container();
        $this->router = $router ?? new Router();
        $this->exceptionHandler = $exceptionHandler ?? new ExceptionHandler($debug);
        $this->providers = new ProviderRegistry($this->container);
        $this->emitter = $emitter ?? new SapiResponseEmitter();
        $this->requestFactory = $requestFactory ?? new SapiRequestFactory();
        $this->responseFactory = $responseFactory ?? new SimpleResponseFactory();
    }

    /**
     * Bootstrap all core services into the container.
     *
     * @param HttpApplication $app The application instance.
     * @return void
     */
    public function bootstrap(HttpApplication $app): void
    {
        $this->registerContainerBindings();
        $this->registerRouterBindings();
        $this->registerExceptionHandlerBindings();
        $this->registerFactoryBindings();
        $this->registerApplicationBinding($app);
        $this->registerOptionalProviders();
        $this->registerLogger($app->basePath());
    }

    /**
     * Add the scoped container middleware to the given middlewares array.
     *
     * @param array<int, mixed> $middlewares The middleware stack.
     * @return array<int, mixed> The middleware stack with scoped container prepended.
     */
    public function addScopedMiddleware(array $middlewares): array
    {
        array_unshift($middlewares, new ScopedContainerMiddleware($this->container));
        return $middlewares;
    }

    /**
     * Get the DI container.
     *
     * @return ContainerInterface
     */
    public function container(): ContainerInterface
    {
        return $this->container;
    }

    /**
     * Get the router.
     *
     * @return RouterInterface
     */
    public function router(): RouterInterface
    {
        return $this->router;
    }

    /**
     * Get the exception handler.
     *
     * @return ExceptionHandlerInterface
     */
    public function exceptionHandler(): ExceptionHandlerInterface
    {
        return $this->exceptionHandler;
    }

    /**
     * Get the provider registry.
     *
     * @return ProviderRegistry
     */
    public function providers(): ProviderRegistry
    {
        return $this->providers;
    }

    /**
     * Get the response emitter.
     *
     * @return ResponseEmitterInterface
     */
    public function emitter(): ResponseEmitterInterface
    {
        return $this->emitter;
    }

    /**
     * Get the request factory.
     *
     * @return RequestFactoryInterface
     */
    public function requestFactory(): RequestFactoryInterface
    {
        return $this->requestFactory;
    }

    /**
     * Get the response factory.
     *
     * @return ResponseFactoryInterface
     */
    public function responseFactory(): ResponseFactoryInterface
    {
        return $this->responseFactory;
    }

    /**
     * Register container interface bindings.
     *
     * @return void
     */
    private function registerContainerBindings(): void
    {
        $this->container->instance(ContainerInterface::class, $this->container);

        if ($this->container instanceof Container) {
            $this->container->instance(Container::class, $this->container);
        }
    }

    /**
     * Register router interface bindings.
     *
     * @return void
     */
    private function registerRouterBindings(): void
    {
        $this->container->instance(RouterInterface::class, $this->router);

        if ($this->router instanceof Router) {
            $this->container->instance(Router::class, $this->router);
        }
    }

    /**
     * Register exception handler bindings.
     *
     * @return void
     */
    private function registerExceptionHandlerBindings(): void
    {
        $this->container->instance(
            ExceptionHandlerInterface::class,
            $this->exceptionHandler,
        );

        if ($this->exceptionHandler instanceof ExceptionHandler) {
            $this->container->instance(
                ExceptionHandler::class,
                $this->exceptionHandler,
            );
        }
    }

    /**
     * Register factory bindings.
     *
     * @return void
     */
    private function registerFactoryBindings(): void
    {
        $this->container->instance(
            RequestFactoryInterface::class,
            $this->requestFactory,
        );
        $this->container->instance(
            ResponseFactoryInterface::class,
            $this->responseFactory,
        );
    }

    /**
     * Register the application instance binding.
     *
     * @param HttpApplication $app The application instance.
     * @return void
     */
    private function registerApplicationBinding(HttpApplication $app): void
    {
        $this->container->instance(HttpApplication::class, $app);
    }

    /**
     * Register optional framework providers.
     *
     * @return void
     */
    private function registerOptionalProviders(): void
    {
        if (class_exists(\Lalaz\Validation\Providers\ValidationServiceProvider::class)) {
            $this->providers->register(
                \Lalaz\Validation\Providers\ValidationServiceProvider::class,
            );
        }
    }

    /**
     * Register and configure the logging system.
     *
     * @param string|null $basePath Application base path for resolving relative log paths.
     * @return void
     */
    private function registerLogger(?string $basePath = null): void
    {
        // Get logging configuration
        $config = Config::get('logging', []);

        if (!is_array($config)) {
            $config = [];
        }

        // Create LogManager with configuration and basePath
        $manager = new LogManager($config, $basePath);

        // Register in container
        $this->container->instance(LogManager::class, $manager);
        $this->container->instance(LoggerInterface::class, $manager);

        // Also register as 'log' alias
        $this->container->alias(LogManager::class, 'log');

        // Set up the Log facade
        Log::setManager($manager);

        Log::debug('Logging system initialized', [
            'default_channel' => $manager->getDefaultChannel(),
        ]);
    }
}
