<?php

declare(strict_types=1);

namespace Lalaz\Runtime\Http;

use Lalaz\Container\Contracts\ContainerInterface;
use Lalaz\Exceptions\ConfigurationException;
use Lalaz\Exceptions\HttpException;
use Lalaz\Logging\Log;
use Lalaz\Web\Http\Contracts\ExceptionHandlerInterface;
use Lalaz\Web\Http\Contracts\MiddlewareInterface as HttpMiddlewareInterface;
use Lalaz\Web\Http\Contracts\RenderableInterface;
use Lalaz\Web\Http\Request;
use Lalaz\Web\Http\Response;
use Lalaz\Web\Routing\Contracts\RouterInterface;
use Lalaz\Web\Routing\MatchedRoute;
use Throwable;

/**
 * Core HTTP kernel responsible for routing and middleware dispatch.
 *
 * The kernel is the heart of the HTTP request handling pipeline. It matches
 * incoming requests to routes, executes middleware stacks, invokes route
 * handlers, and handles exceptions.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
class HttpKernel
{
    /**
     * Create a new HTTP kernel instance.
     *
     * @param ContainerInterface $container The DI container for resolving dependencies.
     * @param RouterInterface $router The router for matching requests to routes.
     * @param ExceptionHandlerInterface $exceptionHandler Handler for rendering exceptions.
     */
    public function __construct(
        private ContainerInterface $container,
        private RouterInterface $router,
        private ExceptionHandlerInterface $exceptionHandler,
    ) {
    }

    /**
     * Handle an HTTP request and return a response.
     *
     * This is the main entry point for processing HTTP requests. It:
     * 1. Matches the request to a route
     * 2. Builds and executes the middleware pipeline
     * 3. Invokes the route handler
     * 4. Handles any exceptions that occur
     *
     * @param Request $request The incoming HTTP request.
     * @param Response $response The response object to populate.
     * @param array<int, callable|string|HttpMiddlewareInterface> $globalMiddlewares Global middleware stack.
     * @return Response The completed HTTP response.
     */
    public function handle(
        Request $request,
        Response $response,
        array $globalMiddlewares = [],
    ): Response {
        $startTime = microtime(true);
        $this->container->instance(Request::class, $request);
        $this->container->instance(Response::class, $response);

        try {
            $matched = $this->router->match(
                $request->method(),
                $request->path(),
            );

            Log::debug('Route matched', [
                'method' => $request->method(),
                'path' => $request->path(),
                'handler' => $this->getHandlerName($matched->handler()),
                'params' => $matched->params(),
            ]);

            $request->setRouteParams($matched->params());

            $middlewares = array_merge(
                $globalMiddlewares,
                $matched->middlewares(),
            );

            $this->runPipeline($middlewares, $matched, $request, $response);

            $this->logRequest($request, $response, $startTime);
        } catch (HttpException $e) {
            $this->logRequest($request, $response, $startTime, $e);
            $this->safeRender($e, $request, $response);
        } catch (Throwable $e) {
            $this->logRequest($request, $response, $startTime, $e);
            $this->safeRender($e, $request, $response);
        }

        return $response;
    }

    /**
     * Log the HTTP request with timing information.
     *
     * @param Request $request The HTTP request.
     * @param Response $response The HTTP response.
     * @param float $startTime Request start timestamp.
     * @param Throwable|null $exception Optional exception that occurred.
     * @return void
     */
    private function logRequest(
        Request $request,
        Response $response,
        float $startTime,
        ?Throwable $exception = null,
    ): void {
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        $statusCode = $response->getStatusCode();

        $context = [
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => $statusCode,
            'duration_ms' => $duration,
            'ip' => $request->ip(),
        ];

        if ($exception !== null) {
            $context['exception'] = get_class($exception);
            $context['exception_message'] = $exception->getMessage();
        }

        // Determine log level based on status code
        $level = match (true) {
            $statusCode >= 500 => 'error',
            $statusCode >= 400 => 'warning',
            default => 'info',
        };

        Log::log($level, sprintf(
            '%s %s %d (%.2fms)',
            $request->method(),
            $request->path(),
            $statusCode,
            $duration,
        ), $context);
    }

    /**
     * Get a readable name for the route handler.
     *
     * @param callable|array|string $handler The route handler.
     * @return string Human-readable handler name.
     */
    private function getHandlerName(callable|array|string $handler): string
    {
        if (is_string($handler)) {
            return $handler;
        }

        if (is_array($handler) && count($handler) === 2) {
            $class = is_object($handler[0]) ? get_class($handler[0]) : $handler[0];
            return $class . '@' . $handler[1];
        }

        if ($handler instanceof \Closure) {
            return 'Closure';
        }

        return 'callable';
    }

    /**
     * Safely render an exception, logging to error_log if the handler itself fails.
     *
     * @param Throwable $e The exception to render.
     * @param Request $request The HTTP request.
     * @param Response $response The HTTP response.
     * @return void
     */
    private function safeRender(
        Throwable $e,
        Request $request,
        Response $response,
    ): void {
        try {
            $this->exceptionHandler->render($e, $request, $response);
        } catch (Throwable $handlerError) {
            // Log both the original exception and the handler failure
            $this->emergencyLog($e, 'Original exception');
            $this->emergencyLog($handlerError, 'Exception handler failed');

            // Return a minimal 500 response
            $response->status(500);
            $response->setBody('Internal Server Error');
        }
    }

    /**
     * Emergency logging when normal exception handling fails.
     *
     * Always logs to PHP error_log as a last resort.
     *
     * @param Throwable $e The exception to log.
     * @param string $prefix Log message prefix.
     * @return void
     */
    private function emergencyLog(Throwable $e, string $prefix = 'Exception'): void
    {
        error_log(sprintf(
            "[Lalaz Emergency] %s: %s in %s:%d\nStack trace:\n%s",
            $prefix,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString(),
        ));
    }

    /**
     * Run the middleware pipeline and invoke the route handler.
     *
     * @param array<int, callable|string|HttpMiddlewareInterface> $middlewares Middleware stack.
     * @param MatchedRoute $route The matched route.
     * @param Request $request The HTTP request.
     * @param Response $response The HTTP response.
     * @return void
     */
    private function runPipeline(
        array $middlewares,
        MatchedRoute $route,
        Request $request,
        Response $response,
    ): void {
        $destination = function (Request $req, Response $res) use (
            $route,
        ): mixed {
            $handler = $this->normalizeHandler($route->handler());
            return $this->container->call($handler, $route->params());
        };

        $pipeline = array_reduce(
            array_reverse($middlewares),
            fn (callable $next, $middleware) => $this->wrapMiddleware(
                $middleware,
                $next,
            ),
            $destination,
        );

        $result = $pipeline($request, $response);
        $this->processReturnValue($result, $response);
    }

    /**
     * Process the return value from a controller/handler.
     *
     * This enables a clean return-based pattern where controllers can return
     * typed values instead of directly manipulating the response object.
     *
     * Supported return types:
     * - RenderableInterface: Calls toResponse() to render to the response.
     * - array: Automatically converted to JSON response.
     * - string: Set as the response body.
     * - null/void: No action (handler used $response directly).
     *
     * @param mixed $result The return value from the handler.
     * @param Response $response The HTTP response to populate.
     * @return void
     */
    private function processReturnValue(mixed $result, Response $response): void
    {
        if ($result === null) {
            // Handler used $response directly (void return)
            return;
        }

        if ($result instanceof RenderableInterface) {
            $result->toResponse($response);
            return;
        }

        if (is_array($result) || is_object($result) && !($result instanceof \Stringable)) {
            // Treat arrays and non-stringable objects as JSON
            $response->json($result);
            return;
        }

        if (is_string($result) || $result instanceof \Stringable) {
            $response->setBody((string) $result);
            return;
        }

        // Scalar values (int, float, bool)
        if (is_scalar($result)) {
            $response->setBody((string) $result);
            return;
        }
    }

    /**
     * Wrap a middleware in a callable for the pipeline.
     *
     * @param callable|string|HttpMiddlewareInterface $middleware The middleware.
     * @param callable $next The next handler in the pipeline.
     * @return callable The wrapped middleware.
     */
    private function wrapMiddleware(
        callable|string|HttpMiddlewareInterface $middleware,
        callable $next,
    ): callable {
        $callable = $this->prepareMiddleware($middleware);

        return static function (Request $request, Response $response) use (
            $callable,
            $next,
        ): mixed {
            return $callable($request, $response, $next);
        };
    }

    /**
     * Prepare a middleware for execution.
     *
     * Normalizes various middleware formats (class name, instance, callable)
     * into a consistent callable format.
     *
     * @param callable|string|HttpMiddlewareInterface $middleware The middleware.
     * @return callable The prepared middleware callable.
     * @throws ConfigurationException When middleware format is invalid.
     */
    private function prepareMiddleware(
        callable|string|HttpMiddlewareInterface $middleware,
    ): callable {
        if ($middleware instanceof HttpMiddlewareInterface) {
            return static function (
                Request $request,
                Response $response,
                callable $next,
            ) use ($middleware): mixed {
                return $middleware->handle($request, $response, $next);
            };
        }

        if (is_string($middleware)) {
            $instance = $this->container->resolve($middleware);
            return $this->prepareMiddleware($instance);
        }

        if (is_callable($middleware)) {
            return static function (
                Request $request,
                Response $response,
                callable $next,
            ) use ($middleware): mixed {
                return $middleware($request, $response, $next);
            };
        }

        throw ConfigurationException::invalidValue(
            'middleware',
            $middleware,
            'string, MiddlewareInterface, or callable',
        );
    }

    /**
     * Normalize a route handler into a callable format.
     *
     * @param callable|array|string $handler The route handler.
     * @return callable|array The normalized handler.
     * @throws ConfigurationException When handler format is invalid.
     */
    private function normalizeHandler(
        callable|array|string $handler,
    ): callable|array {
        if (is_callable($handler)) {
            return $handler;
        }

        if (is_string($handler)) {
            if (str_contains($handler, '@')) {
                [$class, $method] = explode('@', $handler, 2);
                $instance = $this->container->resolve($class);
                return [$instance, $method];
            }

            $instance = $this->container->resolve($handler);

            if (is_callable($instance)) {
                return $instance;
            }

            if (method_exists($instance, '__invoke')) {
                return $instance;
            }
        }

        if (is_array($handler) && count($handler) === 2) {
            [$classOrInstance, $method] = $handler;

            if (is_string($classOrInstance)) {
                $instance = $this->container->resolve($classOrInstance);
            } else {
                $instance = $classOrInstance;
            }

            return [$instance, $method];
        }

        throw ConfigurationException::invalidValue(
            'handler',
            $handler,
            'callable, string, or array',
        );
    }
}
