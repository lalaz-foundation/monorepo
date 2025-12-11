<?php

declare(strict_types=1);

namespace Lalaz\Reactive;

use Lalaz\Reactive\Concerns\HandlesEvents;
use Lalaz\Reactive\Concerns\ValidatesInput;

/**
 * ReactiveComponent - Base class for reactive components
 *
 * Components extend this class to create interactive UIs without JavaScript.
 *
 * Example:
 *
 * class Counter extends ReactiveComponent {
 *     public int $count = 0;
 *
 *     public function increment() {
 *         $this->count++;
 *     }
 *
 *     public function render(): string {
 *         return view('counter', ['count' => $this->count]);
 *     }
 * }
 *
 * @package lalaz/reactive
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
class ReactiveComponent
{
    use HandlesEvents;
    use ValidatesInput;

    /**
     * Unique component ID
     */
    protected string $id;

    /**
     * Component name (class name)
     */
    protected string $name;

    /**
     * Public properties that are reactive
     */
    protected array $publicProperties = [];

    /**
     * Event listeners registered in mount()
     */
    protected array $listeners = [];

    /**
     * Events to dispatch after render
     */
    protected array $dispatchQueue = [];

    /**
     * Redirect URL (if set)
     */
    protected ?string $redirectTo = null;

    /**
     * Notifications to show
     */
    protected array $notifications = [];

    /**
     * Original mount parameters (used for rehydration)
     */
    protected array $mountParams = [];

    /**
     * Initialize component
     * Override this method to set up initial state
     *
     * @param mixed ...$params Parameters passed to component
     * @return void
     */
    public function mount(...$params): void
    {
        // Override in child class
    }

    /**
     * Render component to HTML.
     *
     * If a child class implements its own render() method, that will be used.
     * Otherwise, this default implementation will look for an inline template
     * in the component's file after a `__halt_compiler();` token.
     *
     * @return string HTML output
     */
    public function render(): string
    {
        $reflector = new \ReflectionClass($this);

        // This check ensures that if a child class (like Counter) implements render(),
        // we don't accidentally get stuck in a loop. PHP's method resolution will
        // call the child's method directly. This parent method only runs if the
        // child does NOT have a render() method.
        if (
            $reflector->getMethod('render')->getDeclaringClass()->getName() !==
            self::class
        ) {
            // This line should theoretically not be reached if a child has a render method.
            // It's a safeguard. A real implementation would likely just be empty
            // or throw an error, as the child's method takes precedence.
            // For our purpose, we proceed to the single-file logic.
        }

        $filePath = $reflector->getFileName();

        if (!$filePath || !file_exists($filePath)) {
            throw new \Exception(
                'Cannot determine file path for component: ' .
                    $reflector->getName(),
            );
        }

        $content = file_get_contents($filePath);
        $haltPosition = strpos($content, '__halt_compiler();');

        if ($haltPosition === false) {
            throw new \Exception(
                sprintf(
                    'Component [%s] must either implement a `render()` method or contain a `__halt_compiler();` token for single-file components.',
                    $reflector->getName(),
                ),
            );
        }

        $template = substr(
            $content,
            $haltPosition + strlen('__halt_compiler();'),
        );

        // Trim potential newlines after the halt compiler token
        $template = ltrim($template);

        return $this->inlineView($template, $this->getPublicProperties());
    }

    /**
     * Called after a property is updated
     *
     * @param string $property Property name that was updated
     * @return void
     */
    public function updated(string $property): void
    {
        // Override in child class if needed
    }

    /**
     * Listen to an event
     *
     * @param string $event Event name
     * @param string|callable $handler Method name or callable
     * @return void
     */
    protected function listen(string $event, string|callable $handler): void
    {
        $this->listeners[$event] = $handler;
    }

    /**
     * Alias for listen()
     *
     * @param string $event Event name
     * @param string $method Method name to call
     * @return void
     */
    protected function on(string $event, string $method): void
    {
        $this->listen($event, $method);
    }

    /**
     * Dispatch an event to other components
     *
     * @param string $event Event name
     * @param array $data Event data
     * @return void
     */
    protected function dispatch(string $event, array $data = []): void
    {
        $this->dispatchQueue[] = [
            'event' => $event,
            'data' => $data,
        ];
    }

    /**
     * Emit an event (alias for dispatch)
     *
     * @param string $event Event name
     * @param array $data Event data
     * @return void
     */
    protected function emit(string $event, array $data = []): void
    {
        $this->dispatch($event, $data);
    }

    /**
     * Show a notification
     *
     * @param string $message Message to show
     * @param string $type Notification type (success, error, warning, info)
     * @return void
     */
    protected function notify(string $message, string $type = 'success'): void
    {
        $this->notifications[] = [
            'message' => $message,
            'type' => $type,
        ];
    }

    /**
     * Redirect to a URL
     *
     * @param string $url URL to redirect to
     * @return void
     */
    protected function redirect(string $url): void
    {
        $this->redirectTo = $url;
    }

    /**
     * Reset component properties to initial state
     *
     * @param string ...$properties Properties to reset (empty = reset all)
     * @return void
     */
    protected function reset(string ...$properties): void
    {
        if (empty($properties)) {
            // Reset all public properties
            $reflection = new \ReflectionClass($this);
            foreach (
                $reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property
            ) {
                $property->setValue(
                    $this,
                    $property->getDeclaringClass()->getDefaultProperties()[
                        $property->getName()
                    ] ?? null,
                );
            }
        } else {
            // Reset specific properties
            $reflection = new \ReflectionClass($this);
            foreach ($properties as $propertyName) {
                if ($reflection->hasProperty($propertyName)) {
                    $property = $reflection->getProperty($propertyName);
                    $property->setValue(
                        $this,
                        $property->getDeclaringClass()->getDefaultProperties()[
                            $propertyName
                        ] ?? null,
                    );
                }
            }
        }
    }

    /**
     * Set component ID
     *
     * @param string $id Component ID
     * @return void
     */
    public function setId(string $id): void
    {
        $this->id = $id;
    }

    /**
     * Get component ID
     *
     * @return string Component ID
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Set component name
     *
     * @param string $name Component name
     * @return void
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * Store original mount parameters for later rehydration.
     *
     * @param array $params
     * @return void
     */
    public function setMountParams(array $params): void
    {
        $this->mountParams = $params;
    }

    /**
     * Retrieve stored mount parameters.
     *
     * @return array
     */
    public function getMountParams(): array
    {
        return $this->mountParams;
    }

    /**
     * Get component name
     *
     * @return string Component name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get all public properties
     *
     * @return array Public properties
     */
    public function getPublicProperties(): array
    {
        $properties = [];
        $reflection = new \ReflectionClass($this);

        foreach (
            $reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property
        ) {
            if (!$property->isStatic()) {
                $properties[$property->getName()] = $property->getValue($this);
            }
        }

        return $properties;
    }

    /**
     * Set a public property value
     *
     * @param string $property Property name
     * @param mixed $value New value
     * @return void
     */
    public function setProperty(string $property, mixed $value): void
    {
        if (property_exists($this, $property)) {
            $reflection = new \ReflectionProperty($this, $property);
            if ($reflection->isPublic() && !$reflection->isStatic()) {
                // Cast to the correct type and set using reflection to bypass strict types
                $castedValue = $this->castToPropertyType($property, $value);
                $reflection->setValue($this, $castedValue);
                $this->updated($property);
            }
        }
    }

    /**
     * Get event listeners
     *
     * @return array Event listeners
     */
    public function getListeners(): array
    {
        return $this->listeners;
    }

    /**
     * Get queued events to dispatch
     *
     * @return array Queued events
     */
    public function getDispatchQueue(): array
    {
        return $this->dispatchQueue;
    }

    /**
     * Get redirect URL
     *
     * @return string|null Redirect URL
     */
    public function getRedirect(): ?string
    {
        return $this->redirectTo;
    }

    /**
     * Get notifications
     *
     * @return array Notifications
     */
    public function getNotifications(): array
    {
        return $this->notifications;
    }

    /**
     * Render a view template
     *
     * @param string $template Template name
     * @param array $data Template data
     * @return string Rendered HTML
     */
    protected function view(string $template, array $data = []): string
    {
        $viewEngine = \Lalaz\Runtime\Application::context()->container->resolve(
            \Lalaz\Web\View\Contracts\TemplateEngineInterface::class,
        );

        return $viewEngine->render($template, $data);
    }

    /**
     * Render an inline view template from a string.
     *
     * @param string $content Template content
     * @param array $data Template data
     * @return string Rendered HTML
     */
    protected function inlineView(string $content, array $data = []): string
    {
        $viewEngine = \Lalaz\Runtime\Application::context()->container->resolve(
            \Lalaz\Web\View\Contracts\TemplateEngineInterface::class,
        );

        return $viewEngine->renderFromString($content, $data);
    }

    /**
     * Dehydrate component state for client
     *
     * @return array Component state
     */
    public function dehydrate(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'properties' => $this->getPublicProperties(),
            'mount' => $this->mountParams,
            'listeners' => array_keys($this->listeners),
            'dispatches' => $this->dispatchQueue,
            'redirect' => $this->redirectTo,
            'notifications' => $this->notifications,
        ];
    }

    /**
     * Hydrate component state from client
     *
     * @param array $state Component state
     * @return void
     */
    public function hydrate(array $state): void
    {
        $this->id = $state['id'] ?? '';
        $this->name = $state['name'] ?? '';
        $this->mountParams = $state['mount'] ?? [];

        // Restore properties with recursive array conversion and type casting
        if (isset($state['properties'])) {
            foreach ($state['properties'] as $property => $value) {
                if (property_exists($this, $property)) {
                    // Convert objects to arrays recursively
                    $convertedValue = $this->convertToArray($value);

                    // Cast to the correct type and set using reflection to bypass strict types
                    $castedValue = $this->castToPropertyType($property, $convertedValue);
                    $reflection = new \ReflectionProperty($this, $property);
                    $reflection->setValue($this, $castedValue);
                }
            }
        }
    }

    /**
     * Cast a value to match the property's declared type
     *
     * @param string $property Property name
     * @param mixed $value Value to cast
     * @return mixed Casted value
     */
    private function castToPropertyType(string $property, mixed $value): mixed
    {
        $reflection = new \ReflectionProperty($this, $property);
        $type = $reflection->getType();

        if (!$type instanceof \ReflectionNamedType) {
            return $value;
        }

        $typeName = $type->getName();

        // Handle null values
        if ($value === null && $type->allowsNull()) {
            return null;
        }

        // Don't cast if value is already the correct type
        if (gettype($value) === $typeName || ($typeName === 'int' && is_int($value)) ||
            ($typeName === 'float' && is_float($value)) || ($typeName === 'bool' && is_bool($value)) ||
            ($typeName === 'string' && is_string($value)) || ($typeName === 'array' && is_array($value))) {
            return $value;
        }

        // Only cast scalar values to scalar types
        if (!is_scalar($value) && in_array($typeName, ['int', 'float', 'bool', 'string'])) {
            return $value; // Can't safely cast non-scalar to scalar
        }

        // Cast based on type
        return match ($typeName) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => (bool) filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            'string' => (string) $value,
            'array' => is_array($value) ? $value : [$value],
            default => $value,
        };
    }

    /**
     * Recursively convert objects to arrays
     *
     * @param mixed $value Value to convert
     * @return mixed Converted value
     */
    private function convertToArray(mixed $value): mixed
    {
        if (is_object($value)) {
            $value = (array) $value;
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->convertToArray($item);
            }
        }

        return $value;
    }
}
