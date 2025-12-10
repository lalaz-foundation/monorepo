<?php

declare(strict_types=1);

namespace Lalaz\Events\Tests\Integration;

use Lalaz\Config\Config;
use Lalaz\Events\Contracts\EventDispatcherInterface;
use Lalaz\Events\Contracts\EventDriverInterface;
use Lalaz\Events\Drivers\NullDriver;
use Lalaz\Events\Drivers\QueueDriver;
use Lalaz\Events\EventHub;
use Lalaz\Events\EventListener;
use Lalaz\Events\Events;
use Lalaz\Events\EventServiceProvider;
use Lalaz\Testing\Integration\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Integration tests for EventServiceProvider
 *
 * Tests all branches and configurations of the service provider including:
 * - Different driver configurations (null, sync, queue, custom)
 * - Listener auto-discovery
 * - Container bindings
 */
final class EventServiceProviderTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        // Clear config before each test
        Config::clearCache();
        parent::setUp();
    }

    protected function tearDown(): void
    {
        Events::setInstance(null);
        Config::clearCache();
        parent::tearDown();
    }

    protected function getPackageProviders(): array
    {
        return [
            EventServiceProvider::class,
        ];
    }

    protected function getPackageConfig(): array
    {
        return [
            'base_path' => dirname(__DIR__, 2),
            'debug' => true,
        ];
    }

    // =========================================================================
    // Driver Configuration Tests
    // =========================================================================

    #[Test]
    public function register_creates_null_async_driver_when_driver_config_is_null(): void
    {
        // Default config - no driver set
        Config::setConfig('events', [
            'driver' => null,
        ]);

        $this->refreshApplication();

        $hub = $this->resolve(EventHub::class);

        $this->assertNull($hub->getAsyncDriver());
    }

    #[Test]
    public function register_creates_null_async_driver_when_driver_is_sync(): void
    {
        Config::setConfig('events', [
            'driver' => 'sync',
        ]);

        $this->refreshApplication();

        $hub = $this->resolve(EventHub::class);

        $this->assertNull($hub->getAsyncDriver());
    }

    #[Test]
    public function register_creates_null_driver_when_driver_config_is_null_string(): void
    {
        Config::setConfig('events', [
            'driver' => 'null',
        ]);

        $this->refreshApplication();

        $driver = $this->resolve(EventDriverInterface::class);

        $this->assertInstanceOf(NullDriver::class, $driver);
    }

    #[Test]
    public function register_creates_queue_driver_with_default_config(): void
    {
        Config::setConfig('events', [
            'driver' => 'queue',
        ]);

        $this->refreshApplication();

        $driver = $this->resolve(EventDriverInterface::class);

        $this->assertInstanceOf(QueueDriver::class, $driver);

        // Verify defaults via reflection
        $reflection = new \ReflectionClass($driver);

        $queueProperty = $reflection->getProperty('queue');
        $this->assertSame('events', $queueProperty->getValue($driver));

        $priorityProperty = $reflection->getProperty('priority');
        $this->assertSame(9, $priorityProperty->getValue($driver));
    }

    #[Test]
    public function register_creates_queue_driver_with_custom_config(): void
    {
        Config::setConfig('events', [
            'driver' => 'queue',
            'drivers' => [
                'queue' => [
                    'queue' => 'custom-events-queue',
                    'priority' => 5,
                    'delay' => 30,
                ],
            ],
        ]);

        $this->refreshApplication();

        $driver = $this->resolve(EventDriverInterface::class);

        $this->assertInstanceOf(QueueDriver::class, $driver);

        // Verify custom config
        $reflection = new \ReflectionClass($driver);

        $queueProperty = $reflection->getProperty('queue');
        $this->assertSame('custom-events-queue', $queueProperty->getValue($driver));

        $priorityProperty = $reflection->getProperty('priority');
        $this->assertSame(5, $priorityProperty->getValue($driver));

        $delayProperty = $reflection->getProperty('delay');
        $this->assertSame(30, $delayProperty->getValue($driver));
    }

    #[Test]
    public function register_creates_queue_driver_with_invalid_queue_falls_back_to_default(): void
    {
        Config::setConfig('events', [
            'driver' => 'queue',
            'drivers' => [
                'queue' => [
                    'queue' => 123, // Invalid - not a string
                    'priority' => 'high', // Invalid - not an int
                ],
            ],
        ]);

        $this->refreshApplication();

        $driver = $this->resolve(EventDriverInterface::class);

        $reflection = new \ReflectionClass($driver);

        $queueProperty = $reflection->getProperty('queue');
        $this->assertSame('events', $queueProperty->getValue($driver));

        $priorityProperty = $reflection->getProperty('priority');
        $this->assertSame(9, $priorityProperty->getValue($driver));
    }

    #[Test]
    public function register_creates_custom_driver_when_configured(): void
    {
        Config::setConfig('events', [
            'driver' => 'custom',
            'drivers' => [
                'custom' => [
                    'driver' => TestCustomDriver::class,
                ],
            ],
        ]);

        $this->refreshApplication();

        $driver = $this->resolve(EventDriverInterface::class);

        $this->assertInstanceOf(TestCustomDriver::class, $driver);
    }

    #[Test]
    public function register_returns_null_for_custom_driver_with_invalid_class(): void
    {
        Config::setConfig('events', [
            'driver' => 'custom',
            'drivers' => [
                'custom' => [
                    'driver' => 'NonExistentDriverClass',
                ],
            ],
        ]);

        $this->refreshApplication();

        $hub = $this->resolve(EventHub::class);

        // Should fall back to no async driver
        $this->assertNull($hub->getAsyncDriver());
    }

    #[Test]
    public function register_returns_null_for_custom_driver_not_implementing_interface(): void
    {
        Config::setConfig('events', [
            'driver' => 'custom',
            'drivers' => [
                'custom' => [
                    'driver' => InvalidDriver::class, // Doesn't implement EventDriverInterface
                ],
            ],
        ]);

        $this->refreshApplication();

        $hub = $this->resolve(EventHub::class);

        $this->assertNull($hub->getAsyncDriver());
    }

    #[Test]
    public function register_returns_null_for_custom_driver_with_non_string_class(): void
    {
        Config::setConfig('events', [
            'driver' => 'custom',
            'drivers' => [
                'custom' => [
                    'driver' => 12345, // Not a string
                ],
            ],
        ]);

        $this->refreshApplication();

        $hub = $this->resolve(EventHub::class);

        $this->assertNull($hub->getAsyncDriver());
    }

    // =========================================================================
    // Container Binding Tests
    // =========================================================================

    #[Test]
    public function event_hub_is_registered_as_singleton(): void
    {
        $hub1 = $this->resolve(EventHub::class);
        $hub2 = $this->resolve(EventHub::class);

        $this->assertSame($hub1, $hub2);
    }

    #[Test]
    public function event_dispatcher_interface_resolves_to_event_hub(): void
    {
        $dispatcher = $this->resolve(EventDispatcherInterface::class);

        $this->assertInstanceOf(EventHub::class, $dispatcher);
    }

    #[Test]
    public function boot_sets_events_facade_instance(): void
    {
        $hub = $this->resolve(EventHub::class);
        $facadeInstance = Events::getInstance();

        $this->assertSame($hub, $facadeInstance);
    }

    // =========================================================================
    // Listener Discovery Tests
    // =========================================================================

    #[Test]
    public function boot_does_not_register_listeners_when_discovery_disabled(): void
    {
        Config::setConfig('events', [
            'discovery' => [
                'enabled' => false,
            ],
        ]);

        $this->refreshApplication();

        $hub = $this->resolve(EventHub::class);

        // No listeners should be registered automatically
        $this->assertFalse($hub->hasListeners('some.event'));
    }

    #[Test]
    public function boot_does_not_register_listeners_when_path_is_empty(): void
    {
        Config::setConfig('events', [
            'discovery' => [
                'enabled' => true,
                'path' => '',
            ],
        ]);

        $this->refreshApplication();

        $hub = $this->resolve(EventHub::class);

        $this->assertFalse($hub->hasListeners('some.event'));
    }

    #[Test]
    public function boot_does_not_register_listeners_when_path_is_not_string(): void
    {
        Config::setConfig('events', [
            'discovery' => [
                'enabled' => true,
                'path' => 123, // Invalid
            ],
        ]);

        $this->refreshApplication();

        $hub = $this->resolve(EventHub::class);

        $this->assertFalse($hub->hasListeners('some.event'));
    }

    #[Test]
    public function boot_does_not_register_listeners_when_directory_not_exists(): void
    {
        Config::setConfig('events', [
            'discovery' => [
                'enabled' => true,
                'path' => '/non/existent/path',
            ],
        ]);

        $this->refreshApplication();

        $hub = $this->resolve(EventHub::class);

        $this->assertFalse($hub->hasListeners('some.event'));
    }

    #[Test]
    public function boot_registers_listeners_from_valid_directory(): void
    {
        // Create a temporary directory with a listener file
        $tempDir = sys_get_temp_dir() . '/lalaz_test_listeners_' . uniqid();
        mkdir($tempDir);

        // Create a simple listener file
        $listenerContent = <<<'PHP'
<?php
namespace Lalaz\Events\Tests\TempListeners;

use Lalaz\Events\EventListener;

class TempTestListener extends EventListener
{
    public function subscribers(): array
    {
        return ['temp.test.event'];
    }

    public function handle(mixed $data): void
    {
        // Handle event
    }
}
PHP;
        file_put_contents($tempDir . '/TempTestListener.php', $listenerContent);

        Config::setConfig('events', [
            'discovery' => [
                'enabled' => true,
                'path' => $tempDir,
            ],
        ]);

        try {
            $this->refreshApplication();

            $hub = $this->resolve(EventHub::class);

            // The listener should be registered
            $this->assertTrue($hub->hasListeners('temp.test.event'));
        } finally {
            // Cleanup
            @unlink($tempDir . '/TempTestListener.php');
            @rmdir($tempDir);
        }
    }

    #[Test]
    public function boot_skips_files_that_are_not_event_listeners(): void
    {
        $tempDir = sys_get_temp_dir() . '/lalaz_test_listeners_' . uniqid();
        mkdir($tempDir);

        // Create a non-listener class file
        $nonListenerContent = <<<'PHP'
<?php
namespace Lalaz\Events\Tests\TempListeners;

class NotAListener
{
    public function doSomething(): void {}
}
PHP;
        file_put_contents($tempDir . '/NotAListener.php', $nonListenerContent);

        Config::setConfig('events', [
            'discovery' => [
                'enabled' => true,
                'path' => $tempDir,
            ],
        ]);

        try {
            $this->refreshApplication();

            $hub = $this->resolve(EventHub::class);

            // No listeners should be registered
            $this->assertFalse($hub->hasListeners('any.event'));
        } finally {
            @unlink($tempDir . '/NotAListener.php');
            @rmdir($tempDir);
        }
    }

    #[Test]
    public function boot_handles_files_with_invalid_class_names_gracefully(): void
    {
        $tempDir = sys_get_temp_dir() . '/lalaz_test_listeners_' . uniqid();
        mkdir($tempDir);

        // Create a file with syntax that won't resolve to a valid class
        $invalidContent = <<<'PHP'
<?php
// This file has no class definition
function someFunction() {
    return 'test';
}
PHP;
        file_put_contents($tempDir . '/invalid.php', $invalidContent);

        Config::setConfig('events', [
            'discovery' => [
                'enabled' => true,
                'path' => $tempDir,
            ],
        ]);

        try {
            // Should not throw
            $this->refreshApplication();

            $hub = $this->resolve(EventHub::class);
            $this->assertInstanceOf(EventHub::class, $hub);
        } finally {
            @unlink($tempDir . '/invalid.php');
            @rmdir($tempDir);
        }
    }
}

// =========================================================================
// Test Helper Classes
// =========================================================================

/**
 * Custom driver for testing custom driver configuration
 */
class TestCustomDriver implements EventDriverInterface
{
    public function publish(string $event, mixed $data, array $options = []): void
    {
        // Test implementation
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function getName(): string
    {
        return 'test-custom';
    }
}

/**
 * Invalid driver that doesn't implement EventDriverInterface
 */
class InvalidDriver
{
    public function doSomething(): void
    {
        // Not an event driver
    }
}
