<?php declare(strict_types=1);

// Create a mock SessionManager in the Lalaz\Web\Http namespace
// This allows us to test WebSessionAdapter without the actual web package
namespace Lalaz\Web\Http;

/**
 * Mock SessionManager for testing WebSessionAdapter.
 *
 * This mock simulates the real SessionManager from lalaz/web package,
 * allowing unit tests to run without the dependency.
 */
if (!class_exists(SessionManager::class)) {
class SessionManager
{
    public array $data = [];
    public bool $startCalled = false;
    public bool $regenerateCalled = false;
    public bool $deleteOldSession = true;
    public bool $destroyCalled = false;

    public function start(): void
    {
        $this->startCalled = true;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    public function regenerate(bool $deleteOldSession = true): void
    {
        $this->regenerateCalled = true;
        $this->deleteOldSession = $deleteOldSession;
    }

    public function destroy(): void
    {
        $this->destroyCalled = true;
        $this->data = [];
    }
}
}

// Now define the test class in its proper namespace
namespace Lalaz\Auth\Tests\Unit\Adapters;

use Lalaz\Auth\Tests\Common\AuthUnitTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use Lalaz\Auth\Adapters\WebSessionAdapter;
use Lalaz\Auth\Contracts\SessionInterface;
use Lalaz\Web\Http\SessionManager;

/**
 * Tests for WebSessionAdapter.
 *
 * Uses a mock SessionManager to test the adapter behavior
 * without requiring the actual web package.
 */
#[CoversClass(WebSessionAdapter::class)]
final class WebSessionAdapterTest extends AuthUnitTestCase
{
    private SessionManager $mockSessionManager;
    private WebSessionAdapter $adapter;

    protected function setUp(): void
    {
        if (class_exists(SessionManager::class) && !property_exists(SessionManager::class, 'startCalled')) {
            $this->markTestSkipped('Cannot run WebSessionAdapterTest when real SessionManager is loaded.');
        }

        $this->mockSessionManager = new SessionManager();
        $this->adapter = new WebSessionAdapter($this->mockSessionManager);
    }

    // ===== Interface Implementation Tests =====

    #[Test]
    public function adapter_implements_session_interface(): void
    {
        $this->assertInstanceOf(SessionInterface::class, $this->adapter);
    }

    #[Test]
    public function adapter_has_required_methods(): void
    {
        $reflection = new \ReflectionClass(WebSessionAdapter::class);

        $requiredMethods = ['get', 'set', 'remove', 'has', 'start', 'regenerate', 'destroy'];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                "WebSessionAdapter should have method: {$method}"
            );
        }
    }

    #[Test]
    public function adapter_has_get_session_manager_method(): void
    {
        $reflection = new \ReflectionClass(WebSessionAdapter::class);

        $this->assertTrue(
            $reflection->hasMethod('getSessionManager'),
            'WebSessionAdapter should have getSessionManager method'
        );
    }

    // ===== Delegation Tests =====

    #[Test]
    public function start_delegates_to_session_manager(): void
    {
        $this->adapter->start();

        $this->assertTrue($this->mockSessionManager->startCalled);
    }

    #[Test]
    public function set_delegates_to_session_manager(): void
    {
        $this->adapter->set('user_id', 123);

        $this->assertSame(['user_id' => 123], $this->mockSessionManager->data);
    }

    #[Test]
    public function get_delegates_to_session_manager(): void
    {
        $this->mockSessionManager->data['user_id'] = 456;

        $result = $this->adapter->get('user_id');

        $this->assertSame(456, $result);
    }

    #[Test]
    public function get_returns_default_when_key_not_found(): void
    {
        $result = $this->adapter->get('nonexistent', 'default_value');

        $this->assertSame('default_value', $result);
    }

    #[Test]
    public function has_delegates_to_session_manager(): void
    {
        $this->mockSessionManager->data['exists'] = true;

        $this->assertTrue($this->adapter->has('exists'));
        $this->assertFalse($this->adapter->has('not_exists'));
    }

    #[Test]
    public function remove_delegates_to_session_manager(): void
    {
        $this->mockSessionManager->data['to_remove'] = 'value';

        $this->adapter->remove('to_remove');

        $this->assertArrayNotHasKey('to_remove', $this->mockSessionManager->data);
    }

    #[Test]
    public function regenerate_delegates_to_session_manager(): void
    {
        $this->adapter->regenerate();

        $this->assertTrue($this->mockSessionManager->regenerateCalled);
        $this->assertTrue($this->mockSessionManager->deleteOldSession);
    }

    #[Test]
    public function regenerate_passes_delete_old_session_parameter(): void
    {
        $this->adapter->regenerate(false);

        $this->assertTrue($this->mockSessionManager->regenerateCalled);
        $this->assertFalse($this->mockSessionManager->deleteOldSession);
    }

    #[Test]
    public function destroy_delegates_to_session_manager(): void
    {
        $this->adapter->destroy();

        $this->assertTrue($this->mockSessionManager->destroyCalled);
    }

    #[Test]
    public function get_session_manager_returns_underlying_manager(): void
    {
        $result = $this->adapter->getSessionManager();

        $this->assertSame($this->mockSessionManager, $result);
    }

    // ===== Integration Behavior Tests =====

    #[Test]
    public function full_session_workflow(): void
    {
        // Start session
        $this->adapter->start();
        $this->assertTrue($this->mockSessionManager->startCalled);

        // Set data
        $this->adapter->set('auth_user', ['id' => 1, 'name' => 'John']);
        $this->assertArrayHasKey('auth_user', $this->mockSessionManager->data);

        // Get data
        $user = $this->adapter->get('auth_user');
        $this->assertSame(['id' => 1, 'name' => 'John'], $user);

        // Check existence
        $this->assertTrue($this->adapter->has('auth_user'));

        // Regenerate
        $this->adapter->regenerate();
        $this->assertTrue($this->mockSessionManager->regenerateCalled);

        // Remove data
        $this->adapter->remove('auth_user');
        $this->assertFalse($this->adapter->has('auth_user'));

        // Destroy session
        $this->adapter->destroy();
        $this->assertTrue($this->mockSessionManager->destroyCalled);
    }

    #[Test]
    public function set_and_get_multiple_values(): void
    {
        $this->adapter->set('key1', 'value1');
        $this->adapter->set('key2', 'value2');
        $this->adapter->set('key3', ['nested' => 'data']);

        $this->assertSame('value1', $this->adapter->get('key1'));
        $this->assertSame('value2', $this->adapter->get('key2'));
        $this->assertSame(['nested' => 'data'], $this->adapter->get('key3'));
    }

    #[Test]
    public function overwrite_existing_value(): void
    {
        $this->adapter->set('key', 'original');
        $this->assertSame('original', $this->adapter->get('key'));

        $this->adapter->set('key', 'updated');
        $this->assertSame('updated', $this->adapter->get('key'));
    }

    #[Test]
    public function get_with_null_default(): void
    {
        $result = $this->adapter->get('missing', null);

        $this->assertNull($result);
    }

    #[Test]
    public function set_null_value(): void
    {
        $this->adapter->set('nullable', null);

        $this->assertTrue($this->adapter->has('nullable'));
        $this->assertNull($this->adapter->get('nullable'));
    }

    #[Test]
    public function remove_non_existent_key_does_not_throw(): void
    {
        // Should not throw exception
        $this->adapter->remove('does_not_exist');

        $this->assertFalse($this->adapter->has('does_not_exist'));
    }
}
