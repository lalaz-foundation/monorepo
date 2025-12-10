<?php

declare(strict_types=1);

namespace Lalaz\Reactive\Http;

use Lalaz\Reactive\Exceptions\InvalidRequestException;
use Lalaz\Reactive\Exceptions\MethodNotAccessibleException;
use Lalaz\Reactive\Exceptions\PropertyNotAccessibleException;
use Lalaz\Reactive\ReactiveComponent;
use Lalaz\Reactive\ReactiveManager;
use Lalaz\Web\Http\Request;

/**
 * ReactiveController - Handles AJAX requests from reactive components
 *
 * @package lalaz/reactive
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
class ReactiveController
{
    private ReactiveManager $manager;

    public function __construct(ReactiveManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Handle component method call
     *
     * POST /lalaz-reactive/call
     *
     * @param Request $request
     * @return string Rendered HTML
     */
    public function call(Request $request): string
    {
        try {
            $requestData = $this->validateCallRequest($request->json());

            // Ensure state is an array
            if (
                isset($requestData['state']) &&
                is_object($requestData['state'])
            ) {
                $requestData['state'] = json_decode(
                    json_encode($requestData['state']),
                    true,
                );
            }

            $component = $this->restoreComponent(
                $requestData['name'],
                $requestData['state'],
            );

            if ($this->isListenerCall($requestData['method'])) {
                $this->handleListenerCall(
                    $component,
                    $requestData['method'],
                    $requestData,
                );
            } else {
                $this->executeComponentMethod(
                    $component,
                    $requestData['method'],
                    $requestData['params'],
                );
            }

            return $this->buildResponse($component);
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }

    /**
     * Handle property update
     *
     * POST /lalaz-reactive/update
     *
     * @param Request $request
     * @return string Rendered HTML
     */
    public function update(Request $request): string
    {
        try {
            $requestData = $this->validateUpdateRequest($request->json());

            $component = $this->restoreComponent(
                $requestData['name'],
                $requestData['state'],
            );

            $this->updateComponentProperty(
                $component,
                $requestData['property'],
                $requestData['value'],
            );

            return $this->buildResponse($component);
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }

    /**
     * Validate call request data
     *
     * @param array|null $data
     * @return array
     * @throws InvalidRequestException
     */
    private function validateCallRequest(?array $data): array
    {
        if (!is_array($data)) {
            throw new InvalidRequestException('Invalid request data');
        }

        if (empty($data['name']) || !is_string($data['name'])) {
            throw new InvalidRequestException('Component name is required');
        }

        if (empty($data['method']) || !is_string($data['method'])) {
            throw new InvalidRequestException('Method name is required');
        }

        // Return all data (including extra fields for event listeners)
        return $data;
    }

    /**
     * Validate update request data
     *
     * @param array|null $data
     * @return array
     * @throws InvalidRequestException
     */
    private function validateUpdateRequest(?array $data): array
    {
        if (!is_array($data)) {
            throw new InvalidRequestException('Invalid request data');
        }

        if (empty($data['name']) || !is_string($data['name'])) {
            throw new InvalidRequestException('Component name is required');
        }

        if (empty($data['property']) || !is_string($data['property'])) {
            throw new InvalidRequestException('Property name is required');
        }

        return [
            'id' => $data['id'] ?? '',
            'name' => $data['name'],
            'property' => $data['property'],
            'value' => $data['value'] ?? null,
            'state' => $data['state'] ?? [],
        ];
    }

    /**
     * Restore component from state
     *
     * @param string $name
     * @param array $state
     * @return ReactiveComponent
     */
    private function restoreComponent(string $name, array $state): ReactiveComponent
    {
        return $this->manager->restore($name, $state);
    }

    /**
     * Check if method call is a listener call
     *
     * @param string $method
     * @return bool
     */
    private function isListenerCall(string $method): bool
    {
        return strpos($method, '$listener(') === 0;
    }

    /**
     * Handle listener call
     *
     * @param ReactiveComponent $component
     * @param string $method
     * @param array $requestData
     * @return void
     */
    private function handleListenerCall(
        ReactiveComponent $component,
        string $method,
        array $requestData,
    ): void {
        preg_match('/\$listener\([\'"](.+?)[\'"]\)/', $method, $matches);
        $eventName = $matches[1] ?? '';

        if (empty($eventName)) {
            return;
        }

        $listeners = $component->getListeners();
        if (!isset($listeners[$eventName])) {
            return;
        }

        $handlerMethod = $listeners[$eventName];
        if (!method_exists($component, $handlerMethod)) {
            return;
        }

        // Validate method is public
        $this->validateMethodAccess($component, $handlerMethod);

        // Extract event data (remove standard fields)
        $eventData = $requestData;
        unset(
            $eventData['id'],
            $eventData['name'],
            $eventData['method'],
            $eventData['params'],
            $eventData['state'],
        );

        call_user_func([$component, $handlerMethod], $eventData);
    }

    /**
     * Execute component method
     *
     * @param ReactiveComponent $component
     * @param string $method
     * @param array $params
     * @return void
     * @throws MethodNotAccessibleException
     */
    private function executeComponentMethod(
        ReactiveComponent $component,
        string $method,
        array $params,
    ): void {
        if (!method_exists($component, $method)) {
            throw new MethodNotAccessibleException($method);
        }

        // Validate method is public
        $this->validateMethodAccess($component, $method);

        call_user_func_array([$component, $method], $params);
    }

    /**
     * Validate method is public and accessible
     *
     * @param ReactiveComponent $component
     * @param string $method
     * @return void
     * @throws MethodNotAccessibleException
     */
    private function validateMethodAccess(
        ReactiveComponent $component,
        string $method,
    ): void {
        $reflection = new \ReflectionMethod($component, $method);

        if (!$reflection->isPublic()) {
            throw new MethodNotAccessibleException($method);
        }

        // Additional security: prevent calling certain protected methods
        $protectedMethods = [
            'hydrate',
            'dehydrate',
            'setId',
            'setName',
            'setProperty',
        ];

        if (in_array($method, $protectedMethods, true)) {
            throw new MethodNotAccessibleException($method);
        }
    }

    /**
     * Update component property
     *
     * @param ReactiveComponent $component
     * @param string $property
     * @param mixed $value
     * @return void
     * @throws PropertyNotAccessibleException
     */
    private function updateComponentProperty(
        ReactiveComponent $component,
        string $property,
        mixed $value,
    ): void {
        if (!property_exists($component, $property)) {
            throw new PropertyNotAccessibleException($property);
        }

        // Validate property is public
        $reflection = new \ReflectionProperty($component, $property);

        if (!$reflection->isPublic()) {
            throw new PropertyNotAccessibleException($property);
        }

        $component->{$property} = $value;
        $component->updated($property);
    }

    /**
     * Build response with component HTML and metadata
     *
     * @param ReactiveComponent $component
     * @return string
     */
    private function buildResponse(ReactiveComponent $component): string
    {
        $html = $this->manager->render($component);

        $metadata = [
            'dispatches' => $component->getDispatchQueue(),
            'redirect' => $component->getRedirect(),
            'notifications' => $component->getNotifications(),
        ];

        // Inject metadata as HTML comment
        if (
            !empty($metadata['dispatches']) ||
            $metadata['redirect'] ||
            !empty($metadata['notifications'])
        ) {
            $html .= '<!-- LALAZ-REACTIVE: ' . json_encode($metadata) . ' -->';
        }

        return $html;
    }

    /**
     * Handle errors and return error response
     *
     * @param \Throwable $e
     * @return string
     */
    private function handleError(\Throwable $e): string
    {
        error_log(
            'Lalaz Reactive Error: ' .
                $e->getMessage() .
                "\n" .
                $e->getTraceAsString(),
        );

        // Always show detailed errors for now (can be toggled with config later)
        return sprintf(
            '<div style="color: red; padding: 10px; border: 1px solid red; background: #fff3cd; font-family: monospace; font-size: 12px;">' .
                '<strong>Lalaz Reactive Error:</strong><br>' .
                '<strong>Message:</strong> %s<br>' .
                '<strong>File:</strong> %s<br>' .
                '<strong>Line:</strong> %d' .
                '</div>',
            htmlspecialchars($e->getMessage()),
            htmlspecialchars($e->getFile()),
            $e->getLine(),
        );
    }
}
