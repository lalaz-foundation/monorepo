<?php

declare(strict_types=1);

namespace Lalaz\Container;

use Closure;
use Lalaz\Container\Contracts\ContainerInterface;
use Lalaz\Container\Exceptions\ContainerException;
use Lalaz\Container\Exceptions\NotFoundException;
use Lalaz\Support\Cache\LruCache;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Dependency Injection Container with Auto-Wiring
 *
 * Features:
 * - PSR-11 compliant
 * - Auto-wiring via reflection
 * - Singleton and transient bindings
 * - Contextual binding
 * - Method injection
 * - Circular dependency detection
 *
 *  * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
class Container implements ContainerInterface
{
    /**
     * The container's bindings.
     *
     * @var array<string, array{concrete: mixed, shared: bool}>
     */
    protected array $bindings = [];

    /**
     * The container's shared instances.
     *
     * @var array<string, mixed>
     */
    protected array $instances = [];

    /**
     * The container's scoped bindings.
     *
     * @var array<string, mixed>
     */
    protected array $scopedBindings = [];

    /**
     * The container's scoped instances (per-request/context).
     *
     * @var array<string, mixed>
     */
    protected array $scopedInstances = [];

    /**
     * Whether we are currently in a scope.
     *
     * @var bool
     */
    protected bool $inScope = false;

    /**
     * The registered aliases.
     *
     * @var array<string, string>
     */
    protected array $aliases = [];

    /**
     * The tagged services.
     *
     * @var array<string, array<int, string>>
     */
    protected array $tags = [];

    /**
     * Method bindings for setter injection.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $methodBindings = [];

    /**
     * The stack of concretions currently being built.
     * Used for circular dependency detection.
     *
     * @var array<string>
     */
    protected array $buildStack = [];

    /**
     * Bounded cache for reflection classes to prevent memory leaks.
     * Uses LRU eviction when capacity is exceeded.
     */
    private LruCache $reflectionCache;

    /**
     * Bounded cache for resolved method/constructor parameters.
     * Uses LRU eviction when capacity is exceeded.
     */
    private LruCache $parameterCache;

    /**
     * Maximum cache size for reflection caches.
     */
    private const CACHE_MAX_SIZE = 256;

    /**
     * Create a new container instance.
     */
    public function __construct()
    {
        $this->reflectionCache = new LruCache(self::CACHE_MAX_SIZE);
        $this->parameterCache = new LruCache(self::CACHE_MAX_SIZE);
    }

    /**
     * Bind a service to the container.
     *
     * @param string $abstract The interface or class name
     * @param mixed $concrete The implementation (class name, closure, or instance)
     * @return void
     */
    public function bind(string $abstract, mixed $concrete = null): void
    {
        // If no concrete is provided, use the abstract as concrete
        if ($concrete === null) {
            $concrete = $abstract;
        }

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared' => false,
        ];
    }

    /**
     * Bind a service as a singleton.
     *
     * @param string $abstract The interface or class name
     * @param mixed $concrete The implementation (class name, closure, or instance)
     * @return void
     */
    public function singleton(string $abstract, mixed $concrete = null): void
    {
        if ($concrete === null) {
            $concrete = $abstract;
        }

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared' => true,
        ];
    }

    /**
     * Bind a service as scoped (one instance per request/context).
     *
     * Scoped bindings create a single instance within a scope (typically a request).
     * When the scope ends, the instance is discarded.
     *
     * @param string $abstract The interface or class name
     * @param mixed $concrete The implementation (class name, closure, or instance)
     * @return void
     */
    public function scoped(string $abstract, mixed $concrete = null): void
    {
        if ($concrete === null) {
            $concrete = $abstract;
        }

        $this->scopedBindings[$abstract] = $concrete;
    }

    /**
     * Register an existing instance as shared.
     *
     * @param string $abstract The service identifier
     * @param mixed $instance The instance to register
     * @return void
     */
    public function instance(string $abstract, mixed $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    /**
     * Alias a type to a different name.
     *
     * @param string $abstract The original name
     * @param string $alias The alias name
     * @return void
     */
    public function alias(string $abstract, string $alias): void
    {
        $this->aliases[$alias] = $abstract;
    }

    /**
     * Determine if a given type has been bound.
     *
     * @param string $abstract The service identifier
     * @return bool
     */
    public function bound(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) ||
            isset($this->instances[$abstract]) ||
            isset($this->scopedBindings[$abstract]) ||
            isset($this->aliases[$abstract]);
    }

    /**
     * Check if the container has a given binding.
     * PSR-11 compatibility method.
     *
     * @param string $id The service identifier
     * @return bool
     */
    public function has(string $id): bool
    {
        return $this->bound($id);
    }

    /**
     * Resolve a service from the container.
     * PSR-11 compatibility method.
     *
     * @param string $id The service identifier
     * @return mixed
     * @throws NotFoundException
     * @throws ContainerException
     */
    public function get(string $id): mixed
    {
        try {
            return $this->resolve($id);
        } catch (ContainerException $e) {
            if (
                str_contains($e->getMessage(), 'not found') ||
                str_contains($e->getMessage(), 'does not exist')
            ) {
                throw new NotFoundException($e->getMessage(), 0, $e);
            }
            throw $e;
        }
    }

    /**
     * Resolve a service from the container with auto-wiring.
     *
     * @param string $abstract The service identifier
     * @param array $parameters Additional parameters for instantiation
     * @return mixed The resolved instance
     * @throws ContainerException
     */
    public function resolve(string $abstract, array $parameters = []): mixed
    {
        // Resolve alias
        $abstract = $this->getAlias($abstract);

        // Check if we have a shared instance
        if (isset($this->instances[$abstract]) && empty($parameters)) {
            return $this->instances[$abstract];
        }

        // Check if we have a scoped instance (if in scope)
        if (
            $this->inScope &&
            isset($this->scopedInstances[$abstract]) &&
            empty($parameters)
        ) {
            return $this->scopedInstances[$abstract];
        }

        // Get the concrete implementation
        $concrete = $this->getConcrete($abstract);

        // Build the object
        $object = $this->build($concrete, $parameters, $abstract);

        // If this is a singleton, store the instance
        if ($this->isShared($abstract) && empty($parameters)) {
            $this->instances[$abstract] = $object;
        }

        // If this is a scoped binding and we're in scope, store the instance
        if (
            $this->isScoped($abstract) &&
            $this->inScope &&
            empty($parameters)
        ) {
            $this->scopedInstances[$abstract] = $object;
        }

        return $object;
    }

    /**
     * Get the alias for an abstract if available.
     *
     * @param string $abstract
     * @return string
     */
    protected function getAlias(string $abstract): string
    {
        return $this->aliases[$abstract] ?? $abstract;
    }

    /**
     * Get the concrete type for a given abstract.
     *
     * @param string $abstract
     * @return mixed
     */
    protected function getConcrete(string $abstract): mixed
    {
        // If we have a binding, return it
        if (isset($this->bindings[$abstract])) {
            return $this->bindings[$abstract]['concrete'];
        }

        // If we have a scoped binding, return it
        if (isset($this->scopedBindings[$abstract])) {
            return $this->scopedBindings[$abstract];
        }

        // Otherwise, return the abstract itself (auto-wiring)
        return $abstract;
    }

    /**
     * Determine if the given abstract is shared (singleton).
     *
     * @param string $abstract
     * @return bool
     */
    protected function isShared(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) &&
            $this->bindings[$abstract]['shared'] === true;
    }

    /**
     * Determine if the given abstract is scoped.
     *
     * @param string $abstract
     * @return bool
     */
    protected function isScoped(string $abstract): bool
    {
        return isset($this->scopedBindings[$abstract]);
    }

    /**
     * Build an instance of the given concrete type.
     *
     * @param mixed $concrete
     * @param array $parameters
     * @param string|null $abstract The abstract being resolved (for method bindings)
     * @return mixed
     * @throws ContainerException
     */
    protected function build(mixed $concrete, array $parameters = [], ?string $abstract = null): mixed
    {
        // If concrete is a closure, invoke it
        if ($concrete instanceof Closure) {
            $instance = $concrete($this, $parameters);
            // Apply method bindings to closure results if abstract is provided
            if ($abstract !== null && is_object($instance)) {
                $instance = $this->applyMethodBindings($instance, $abstract);
            }
            return $instance;
        }

        // If concrete is not a string (class name), return it as-is
        if (!is_string($concrete)) {
            return $concrete;
        }

        // Check for circular dependencies
        if (in_array($concrete, $this->buildStack, true)) {
            throw new ContainerException(
                "Circular dependency detected while resolving [{$concrete}]",
            );
        }

        // Add to build stack
        $this->buildStack[] = $concrete;

        try {
            // Use cached reflection to instantiate the class with dependencies
            $reflector = $this->getReflectionClass($concrete);

            // Check if class is instantiable
            if (!$reflector->isInstantiable()) {
                throw new ContainerException(
                    "Target [{$concrete}] is not instantiable.",
                );
            }

            // Get the constructor (cached)
            $constructor = $reflector->getConstructor();

            // If there's no constructor, just instantiate
            if ($constructor === null) {
                $instance = new $concrete();
                // Apply method bindings - check both abstract and concrete
                $instance = $this->applyMethodBindingsForClass($instance, $concrete, $abstract);
                array_pop($this->buildStack);
                return $instance;
            }

            // Get constructor parameters
            $dependencies = $this->resolveParameters(
                $this->getParametersCache($constructor),
                $parameters,
            );

            // Instantiate with dependencies
            $instance = $reflector->newInstanceArgs($dependencies);

            // Apply method bindings - check both abstract and concrete
            $instance = $this->applyMethodBindingsForClass($instance, $concrete, $abstract);

            array_pop($this->buildStack);

            return $instance;
        } catch (\ReflectionException $e) {
            array_pop($this->buildStack);
            throw new ContainerException(
                "Error while resolving [{$concrete}]: {$e->getMessage()}",
                0,
                $e,
            );
        }
    }

    /**
     * Apply method bindings checking both concrete class and abstract.
     *
     * @param object $instance
     * @param string $concrete
     * @param string|null $abstract
     * @return object
     */
    private function applyMethodBindingsForClass(object $instance, string $concrete, ?string $abstract): object
    {
        // Apply bindings registered for concrete class name
        $instance = $this->applyMethodBindings($instance, $concrete);

        // Also apply bindings registered for abstract (if different)
        if ($abstract !== null && $abstract !== $concrete) {
            $instance = $this->applyMethodBindings($instance, $abstract);
        }

        return $instance;
    }

    /**
     * Resolve all parameters for a method/constructor.
     *
     * @param array<ReflectionParameter> $parameters
     * @param array $primitives User-provided parameters
     * @return array
     * @throws ContainerException
     */
    protected function resolveParameters(
        array $parameters,
        array $primitives = [],
    ): array {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $name = $parameter->getName();

            // Check if user provided this parameter
            if (array_key_exists($name, $primitives)) {
                $dependencies[] = $primitives[$name];
                continue;
            }

            // Check for numeric indexed parameters
            if (array_key_exists($parameter->getPosition(), $primitives)) {
                $dependencies[] = $primitives[$parameter->getPosition()];
                continue;
            }

            // Try to resolve by type hint
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                // This is a class dependency - resolve from container
                try {
                    $dependencies[] = $this->resolve($type->getName());
                    continue;
                } catch (ContainerException $e) {
                    // Re-throw circular dependency exceptions as-is
                    if (str_contains($e->getMessage(), 'Circular dependency')) {
                        throw $e;
                    }

                    // If we can't resolve and there's no default, throw
                    if (!$parameter->isOptional()) {
                        throw new ContainerException(
                            "Unable to resolve dependency [{$name}] for parameter [{$parameter->getName()}]",
                            0,
                            $e,
                        );
                    }
                }
            }

            // Try to use default value
            if ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
                continue;
            }

            // If parameter is optional, use null
            if ($parameter->allowsNull()) {
                $dependencies[] = null;
                continue;
            }

            // Can't resolve this parameter
            throw new ContainerException(
                "Unable to resolve parameter [{$name}] when building [" .
                    $parameter->getDeclaringClass()?->getName() .
                    ']',
            );
        }

        return $dependencies;
    }

    /**
     * Call a callback with dependency injection.
     *
     * @param callable $callback The callback to call
     * @param array $parameters Additional parameters
     * @return mixed
     * @throws ContainerException
     */
    public function call(callable $callback, array $parameters = []): mixed
    {
        try {
            // Handle different callable types
            if ($callback instanceof Closure) {
                $reflector = new ReflectionFunction($callback);
                $dependencies = $this->resolveParameters(
                    $this->getParametersCache($reflector),
                    $parameters,
                );
                return $reflector->invokeArgs($dependencies);
            }

            if (is_array($callback)) {
                [$class, $method] = $callback;

                // If class is a string, resolve it from container
                if (is_string($class)) {
                    $class = $this->resolve($class);
                }

                $reflector = new ReflectionMethod($class, $method);
                $dependencies = $this->resolveParameters(
                    $this->getParametersCache($reflector),
                    $parameters,
                );

                return $reflector->invokeArgs($class, $dependencies);
            }

            // For other callables, just call them
            return $callback(...array_values($parameters));
        } catch (\ReflectionException $e) {
            throw new ContainerException(
                "Error while calling callback: {$e->getMessage()}",
                0,
                $e,
            );
        }
    }

    /**
     * Obtém ReflectionClass com cache (LRU bounded).
     */
    private function getReflectionClass(string $class): ReflectionClass
    {
        if ($this->reflectionCache->has($class)) {
            return $this->reflectionCache->get($class);
        }

        $reflection = new ReflectionClass($class);
        $this->reflectionCache->set($class, $reflection);

        return $reflection;
    }

    /**
     * Obtém parâmetros de um método/closure com cache (LRU bounded).
     *
     * @param ReflectionMethod|ReflectionFunction $reflector
     * @return array<ReflectionParameter>
     */
    private function getParametersCache(
        ReflectionMethod|ReflectionFunction $reflector,
    ): array {
        $key =
            ($reflector instanceof ReflectionMethod
                ? $reflector->getDeclaringClass()->getName() .
                    '::' .
                    $reflector->getName()
                : $reflector->getName()) .
            '@' .
            ($reflector->getFileName() ?: '') .
            ':' .
            $reflector->getStartLine();

        if ($this->parameterCache->has($key)) {
            return $this->parameterCache->get($key);
        }

        $parameters = $reflector->getParameters();
        $this->parameterCache->set($key, $parameters);

        return $parameters;
    }

    /**
     * Begin a new scope.
     *
     * This should be called at the start of each request/context.
     * Scoped instances will be created and cached until endScope() is called.
     *
     * @return void
     */
    public function beginScope(): void
    {
        $this->inScope = true;
        $this->scopedInstances = [];
    }

    /**
     * End the current scope.
     *
     * This should be called at the end of each request/context.
     * All scoped instances will be discarded.
     *
     * @return void
     */
    public function endScope(): void
    {
        $this->inScope = false;
        $this->scopedInstances = [];
    }

    /**
     * Check if we are currently in a scope.
     *
     * @return bool
     */
    public function inScope(): bool
    {
        return $this->inScope;
    }

    /**
     * Flush all bindings and resolved instances.
     *
     * @return void
     */
    public function flush(): void
    {
        $this->bindings = [];
        $this->instances = [];
        $this->scopedBindings = [];
        $this->scopedInstances = [];
        $this->aliases = [];
        $this->tags = [];
        $this->methodBindings = [];
        $this->buildStack = [];
        $this->reflectionCache->clear();
        $this->parameterCache->clear();
        $this->inScope = false;
    }

    /**
     * Assign tags to an abstract type.
     *
     * @param string $abstract The service identifier
     * @param string|array<string> $tags One or more tags to assign
     * @return void
     */
    public function tag(string $abstract, string|array $tags): void
    {
        $tags = is_array($tags) ? $tags : [$tags];

        foreach ($tags as $tag) {
            if (!isset($this->tags[$tag])) {
                $this->tags[$tag] = [];
            }

            if (!in_array($abstract, $this->tags[$tag], true)) {
                $this->tags[$tag][] = $abstract;
            }
        }
    }

    /**
     * Resolve all services tagged with the given tag.
     *
     * @param string $tag The tag to resolve
     * @return array<int, mixed> Array of resolved instances
     */
    public function tagged(string $tag): array
    {
        if (!isset($this->tags[$tag])) {
            return [];
        }

        return array_map(
            fn (string $abstract) => $this->resolve($abstract),
            $this->tags[$tag],
        );
    }

    /**
     * Get all abstracts registered under a tag without resolving them.
     *
     * @param string $tag The tag to look up
     * @return array<int, string> Array of abstract identifiers
     */
    public function getTaggedAbstracts(string $tag): array
    {
        return $this->tags[$tag] ?? [];
    }

    /**
     * Check if a tag exists.
     *
     * @param string $tag The tag to check
     * @return bool
     */
    public function hasTag(string $tag): bool
    {
        return isset($this->tags[$tag]) && !empty($this->tags[$tag]);
    }

    /**
     * Start defining method injections for a class.
     *
     * @param string $concrete The class to configure method injections for
     * @return MethodBindingBuilder
     */
    public function when(string $concrete): MethodBindingBuilder
    {
        return new MethodBindingBuilder($this, $concrete);
    }

    /**
     * Add a method binding for a class.
     *
     * @param string $concrete The class name
     * @param string $method The method name
     * @param mixed $implementation The concrete implementation
     * @return void
     * @internal Used by MethodBindingBuilder
     */
    public function addMethodBinding(string $concrete, string $method, mixed $implementation): void
    {
        if (!isset($this->methodBindings[$concrete])) {
            $this->methodBindings[$concrete] = [];
        }

        $this->methodBindings[$concrete][$method] = $implementation;
    }

    /**
     * Get all method bindings for a class.
     *
     * @param string $concrete The class to get method bindings for
     * @return array<string, mixed> Map of method name to concrete implementation
     */
    public function getMethodBindings(string $concrete): array
    {
        return $this->methodBindings[$concrete] ?? [];
    }

    /**
     * Check if a class has any method bindings.
     *
     * @param string $concrete The class to check
     * @return bool
     */
    public function hasMethodBindings(string $concrete): bool
    {
        return isset($this->methodBindings[$concrete]) && !empty($this->methodBindings[$concrete]);
    }

    /**
     * Apply method bindings to an instance.
     *
     * @param object $instance The instance to apply bindings to
     * @param string $concrete The class name
     * @return object The instance with bindings applied
     */
    protected function applyMethodBindings(object $instance, string $concrete): object
    {
        if (!$this->hasMethodBindings($concrete)) {
            return $instance;
        }

        foreach ($this->methodBindings[$concrete] as $method => $implementation) {
            if (!method_exists($instance, $method)) {
                continue;
            }

            // Resolve the implementation
            $resolved = $this->resolveMethodBinding($implementation);

            // If resolved is an array, pass as named parameters
            if (is_array($resolved)) {
                $instance->$method(...$resolved);
            } else {
                // Pass as single argument
                $instance->$method($resolved);
            }
        }

        return $instance;
    }

    /**
     * Resolve a method binding implementation.
     *
     * @param mixed $implementation
     * @return mixed
     */
    protected function resolveMethodBinding(mixed $implementation): mixed
    {
        // If it's a closure, invoke it with the container
        if ($implementation instanceof \Closure) {
            return $implementation($this);
        }

        // If it's a string (class name or interface), resolve from container
        if (is_string($implementation)) {
            // Check if it's bound in the container or if it's a valid class/interface
            if ($this->bound($implementation) || class_exists($implementation) || interface_exists($implementation)) {
                return $this->resolve($implementation);
            }
        }

        // Otherwise, return as-is (for scalar values, objects, etc.)
        return $implementation;
    }
}
