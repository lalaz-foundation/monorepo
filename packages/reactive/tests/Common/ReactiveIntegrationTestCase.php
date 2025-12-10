<?php

declare(strict_types=1);

namespace Lalaz\Reactive\Tests\Common;

use Lalaz\Reactive\ReactiveComponent;
use Lalaz\Reactive\ReactiveManager;
use Lalaz\Reactive\ComponentRegistry;
use Lalaz\Reactive\Http\ReactiveController;
use Lalaz\Reactive\Tests\Fixtures\Mocks\MockContainer;

/**
 * Base test case for Reactive package integration tests.
 *
 * Provides extended functionality for testing component lifecycle,
 * HTTP controller interactions, and complex scenarios.
 */
abstract class ReactiveIntegrationTestCase extends ReactiveUnitTestCase
{
    protected ReactiveController $controller;
    protected array $registeredComponents = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new ReactiveController($this->manager);
        $this->registeredComponents = [];
    }

    protected function tearDown(): void
    {
        $this->registeredComponents = [];
        parent::tearDown();
    }

    /**
     * Create a controller instance.
     */
    protected function createController(
        ?ReactiveManager $manager = null
    ): ReactiveController {
        return new ReactiveController($manager ?? $this->manager);
    }

    /**
     * Register a component class in the container.
     */
    protected function registerComponentClass(string $class): void
    {
        $this->container->bind($class, fn() => new $class());
        $this->registeredComponents[] = $class;
    }

    /**
     * Mount a component and get its snapshot.
     */
    protected function mountAndSnapshot(
        string $class,
        array $params = []
    ): array {
        $this->registerComponentClass($class);
        return $this->manager->mount($class, $params);
    }

    /**
     * Mount, restore, and return the component instance.
     */
    protected function mountAndRestore(
        string $class,
        array $params = []
    ): ReactiveComponent {
        $snapshot = $this->mountAndSnapshot($class, $params);
        return $this->manager->restore($snapshot);
    }

    /**
     * Simulate a full component lifecycle.
     *
     * 1. Mount the component
     * 2. Restore from snapshot
     * 3. Call a method
     * 4. Re-render
     */
    protected function simulateLifecycle(
        string $class,
        array $mountParams = [],
        string $method = '',
        array $methodParams = []
    ): array {
        $this->registerComponentClass($class);

        // Mount
        $snapshot = $this->manager->mount($class, $mountParams);

        // Restore
        $component = $this->manager->restore($snapshot);

        // Call method if specified
        $result = null;
        if ($method) {
            $result = $this->manager->call($component, $method, $methodParams);
        }

        // Re-render
        $html = $this->manager->render($component);

        return [
            'snapshot' => $snapshot,
            'component' => $component,
            'result' => $result,
            'html' => $html,
        ];
    }

    /**
     * Simulate a property update lifecycle.
     */
    protected function simulatePropertyUpdate(
        string $class,
        array $mountParams,
        string $property,
        mixed $value
    ): array {
        $this->registerComponentClass($class);

        // Mount
        $snapshot = $this->manager->mount($class, $mountParams);

        // Restore
        $component = $this->manager->restore($snapshot);

        // Update property
        $this->manager->updateProperty($component, $property, $value);

        // Re-render
        $html = $this->manager->render($component);

        return [
            'snapshot' => $snapshot,
            'component' => $component,
            'html' => $html,
            'newValue' => $component->getProperty($property),
        ];
    }

    /**
     * Create a mock AJAX request for method call.
     */
    protected function createCallRequest(
        string $id,
        string $name,
        string $method,
        array $params = [],
        array $state = [],
        string $checksum = ''
    ): array {
        return [
            'id' => $id,
            'name' => $name,
            'method' => $method,
            'params' => $params,
            'state' => $state,
            'checksum' => $checksum ?: $this->generateChecksum($state),
        ];
    }

    /**
     * Create a mock AJAX request for property update.
     */
    protected function createUpdateRequest(
        string $id,
        string $name,
        string $property,
        mixed $value,
        array $state = [],
        string $checksum = ''
    ): array {
        return [
            'id' => $id,
            'name' => $name,
            'property' => $property,
            'value' => $value,
            'state' => $state,
            'checksum' => $checksum ?: $this->generateChecksum($state),
        ];
    }

    /**
     * Generate a checksum for state verification.
     */
    protected function generateChecksum(array $state): string
    {
        return hash('sha256', json_encode($state) . ($this->getChecksumSecret()));
    }

    /**
     * Get the checksum secret (should match ReactiveController).
     */
    protected function getChecksumSecret(): string
    {
        return 'reactive-secret-key';
    }

    /**
     * Assert that a component snapshot is valid.
     */
    protected function assertValidSnapshot(array $snapshot): void
    {
        $this->assertArrayHasKey('id', $snapshot);
        $this->assertArrayHasKey('name', $snapshot);
        $this->assertArrayHasKey('state', $snapshot);
        $this->assertArrayHasKey('checksum', $snapshot);
        $this->assertArrayHasKey('html', $snapshot);
        $this->assertNotEmpty($snapshot['id']);
        $this->assertNotEmpty($snapshot['name']);
        $this->assertNotEmpty($snapshot['html']);
    }

    /**
     * Assert that HTML contains expected elements.
     */
    protected function assertHtmlContains(string $html, string $expected): void
    {
        $this->assertStringContainsString(
            $expected,
            $html,
            "HTML does not contain expected content: {$expected}"
        );
    }

    /**
     * Assert that HTML does not contain elements.
     */
    protected function assertHtmlNotContains(string $html, string $notExpected): void
    {
        $this->assertStringNotContainsString(
            $notExpected,
            $html,
            "HTML should not contain: {$notExpected}"
        );
    }

    /**
     * Assert that a component method was called successfully.
     */
    protected function assertMethodCallSucceeds(
        string $class,
        string $method,
        array $params = []
    ): mixed {
        $lifecycle = $this->simulateLifecycle($class, [], $method, $params);
        $this->assertNotNull($lifecycle['component']);
        return $lifecycle['result'];
    }

    /**
     * Assert that property was updated correctly.
     */
    protected function assertPropertyUpdated(
        string $class,
        string $property,
        mixed $originalValue,
        mixed $newValue
    ): void {
        $result = $this->simulatePropertyUpdate(
            $class,
            [$property => $originalValue],
            $property,
            $newValue
        );

        $this->assertEquals($newValue, $result['newValue']);
    }

    /**
     * Assert that an event was dispatched during lifecycle.
     */
    protected function assertEventDispatchedDuringLifecycle(
        string $class,
        string $method,
        string $expectedEvent
    ): void {
        $eventDispatched = false;
        $lifecycle = $this->simulateLifecycle($class, [], '', []);
        $component = $lifecycle['component'];

        $component->on($expectedEvent, function () use (&$eventDispatched) {
            $eventDispatched = true;
        });

        $this->manager->call($component, $method, []);
        $this->assertTrue($eventDispatched, "Event '{$expectedEvent}' was not dispatched");
    }

    /**
     * Create a component with predefined state for testing.
     */
    protected function createPreloadedComponent(
        string $class,
        array $state
    ): ReactiveComponent {
        $this->registerComponentClass($class);
        $component = new $class();
        $component->hydrate($state);
        return $component;
    }

    /**
     * Simulate multiple sequential method calls.
     */
    protected function simulateMultipleCalls(
        string $class,
        array $mountParams,
        array $calls
    ): array {
        $this->registerComponentClass($class);

        $snapshot = $this->manager->mount($class, $mountParams);
        $component = $this->manager->restore($snapshot);

        $results = [];
        foreach ($calls as $call) {
            $method = $call['method'];
            $params = $call['params'] ?? [];
            $results[] = $this->manager->call($component, $method, $params);
        }

        return [
            'component' => $component,
            'results' => $results,
            'finalHtml' => $this->manager->render($component),
        ];
    }

    /**
     * Assert component state after multiple operations.
     */
    protected function assertStateAfterOperations(
        string $class,
        array $mountParams,
        array $operations,
        array $expectedState
    ): void {
        $result = $this->simulateMultipleCalls($class, $mountParams, $operations);
        $component = $result['component'];

        foreach ($expectedState as $property => $value) {
            $this->assertEquals(
                $value,
                $component->getProperty($property),
                "Property '{$property}' does not match expected value after operations"
            );
        }
    }
}

/**
 * Test component for integration testing.
 */
class IntegrationTestComponent extends ReactiveComponent
{
    public string $name = '';
    public int $value = 0;
    public array $history = [];
    public bool $active = false;

    public function mount(string $name = '', int $value = 0): void
    {
        $this->name = $name;
        $this->value = $value;
        $this->history[] = ['action' => 'mount', 'name' => $name, 'value' => $value];
    }

    public function setValue(int $value): void
    {
        $oldValue = $this->value;
        $this->value = $value;
        $this->history[] = ['action' => 'setValue', 'old' => $oldValue, 'new' => $value];
        $this->dispatch('value:changed', ['old' => $oldValue, 'new' => $value]);
    }

    public function incrementValue(): void
    {
        $this->setValue($this->value + 1);
    }

    public function toggle(): void
    {
        $this->active = !$this->active;
        $this->history[] = ['action' => 'toggle', 'active' => $this->active];
    }

    public function getHistory(): array
    {
        return $this->history;
    }

    public function clearHistory(): void
    {
        $this->history = [];
    }

    public function render(): string
    {
        $status = $this->active ? 'active' : 'inactive';
        return "<div class='integration-test' data-status='{$status}'><h1>{$this->name}</h1><span>{$this->value}</span></div>";
    }
}

/**
 * Component with events for integration testing.
 */
class EventTestComponent extends ReactiveComponent
{
    public string $message = '';
    public array $eventLog = [];

    public function mount(): void
    {
        $this->listen('external:event', 'handleExternalEvent');
    }

    public function sendMessage(string $message): void
    {
        $this->message = $message;
        $this->emit('message:sent', ['message' => $message]);
    }

    public function handleExternalEvent(array $data): void
    {
        $this->eventLog[] = $data;
    }

    public function broadcastNotification(): void
    {
        $this->notify('success', 'Notification sent');
    }

    public function triggerRedirect(): void
    {
        $this->redirect('/dashboard');
    }

    public function render(): string
    {
        return "<div class='event-test'>{$this->message}</div>";
    }
}

/**
 * Component for testing validation in integration.
 */
class ValidationTestComponent extends ReactiveComponent
{
    public string $email = '';
    public string $password = '';
    public array $tags = [];

    public function validateForm(): bool
    {
        $this->validate([
            'email' => 'required|email',
            'password' => 'required|min:8',
        ]);

        return !$this->hasErrors();
    }

    public function addTag(string $tag): void
    {
        if (!in_array($tag, $this->tags)) {
            $this->tags[] = $tag;
        }
    }

    public function removeTag(string $tag): void
    {
        $this->tags = array_values(array_filter(
            $this->tags,
            fn($t) => $t !== $tag
        ));
    }

    public function render(): string
    {
        $tagList = implode(', ', $this->tags);
        return "<form><input name='email' value='{$this->email}' /><input name='password' type='password' /><div class='tags'>{$tagList}</div></form>";
    }
}
