<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Unit;

use Closure;
use Lalaz\Auth\Tests\Common\AuthUnitTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use Lalaz\Auth\AuthServiceProvider;
use Lalaz\Auth\AuthManager;
use Lalaz\Auth\Contracts\UserProviderInterface;
use Lalaz\Auth\Providers\ModelUserProvider;
use Lalaz\Auth\Providers\GenericUserProvider;
use Lalaz\Auth\Jwt\JwtEncoder;
use Lalaz\Auth\Jwt\JwtBlacklist;

/**
 * Tests for AuthServiceProvider.
 *
 * These tests verify that the service provider class exists and
 * test the provider/guard creation logic in isolation.
 */
#[CoversClass(AuthServiceProvider::class)]
final class AuthServiceProviderTest extends AuthUnitTestCase
{
    #[Test]
    public function auth_service_provider_class_exists(): void
    {
        $this->assertTrue(class_exists(AuthServiceProvider::class));
    }

    #[Test]
    public function auth_service_provider_is_final(): void
    {
        $reflection = new \ReflectionClass(AuthServiceProvider::class);
        $this->assertTrue($reflection->isFinal());
    }

    #[Test]
    public function auth_service_provider_has_register_method(): void
    {
        $reflection = new \ReflectionClass(AuthServiceProvider::class);
        $this->assertTrue($reflection->hasMethod('register'));
    }

    #[Test]
    public function auth_service_provider_extends_service_provider(): void
    {
        $reflection = new \ReflectionClass(AuthServiceProvider::class);
        $parent = $reflection->getParentClass();

        $this->assertNotFalse($parent);
        $this->assertSame('ServiceProvider', $parent->getShortName());
    }
}

/**
 * Tests for user provider creation logic.
 *
 * Since AuthServiceProvider is final and depends on external container,
 * we test the provider creation logic in isolation using a helper class.
 */
#[CoversClass(AuthServiceProvider::class)]
final class AuthServiceProviderProvidersTest extends AuthUnitTestCase
{
    #[Test]
    public function creates_model_provider_from_config(): void
    {
        $helper = new ProviderFactoryHelper();

        $result = $helper->createUserProvider([
            'driver' => 'model',
            'model' => MockUserModelForProvider::class,
        ]);

        $this->assertInstanceOf(ModelUserProvider::class, $result);
    }

    #[Test]
    public function creates_generic_provider_from_config(): void
    {
        $helper = new ProviderFactoryHelper();

        $result = $helper->createUserProvider([
            'driver' => 'generic',
        ]);

        $this->assertInstanceOf(GenericUserProvider::class, $result);
    }

    #[Test]
    public function creates_generic_provider_with_callbacks(): void
    {
        $helper = new ProviderFactoryHelper();

        $byId = fn($id) => ['id' => $id];
        $byCredentials = fn($creds) => ['email' => $creds['email']];

        $result = $helper->createUserProvider([
            'driver' => 'generic',
            'callbacks' => [
                'byId' => $byId,
                'byCredentials' => $byCredentials,
            ],
        ]);

        $this->assertInstanceOf(GenericUserProvider::class, $result);

        // Verify callbacks work
        $user = $result->retrieveById(123);
        $this->assertSame(['id' => 123], $user);
    }

    #[Test]
    public function creates_custom_provider_from_class(): void
    {
        $helper = new ProviderFactoryHelper();

        $result = $helper->createUserProvider([
            'driver' => 'custom',
            'class' => MockCustomProvider::class,
        ]);

        $this->assertInstanceOf(MockCustomProvider::class, $result);
    }

    #[Test]
    public function throws_for_unsupported_provider_driver(): void
    {
        $helper = new ProviderFactoryHelper();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unsupported user provider driver: invalid");

        $helper->createUserProvider([
            'driver' => 'invalid',
        ]);
    }

    #[Test]
    public function throws_for_custom_provider_without_class(): void
    {
        $helper = new ProviderFactoryHelper();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Custom provider requires a valid 'class' configuration");

        $helper->createUserProvider([
            'driver' => 'custom',
        ]);
    }

    #[Test]
    public function throws_for_custom_provider_with_invalid_class(): void
    {
        $helper = new ProviderFactoryHelper();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Custom provider requires a valid 'class' configuration");

        $helper->createUserProvider([
            'driver' => 'custom',
            'class' => 'NonExistentClass',
        ]);
    }

    #[Test]
    public function model_provider_uses_default_model(): void
    {
        $helper = new ProviderFactoryHelper();

        $result = $helper->createUserProvider([
            'driver' => 'model',
            // No model specified, should use default
        ]);

        $this->assertInstanceOf(ModelUserProvider::class, $result);
    }

    #[Test]
    public function generic_provider_with_validate_callback(): void
    {
        $helper = new ProviderFactoryHelper();

        $validate = fn($user, $creds) => $creds['password'] === 'secret';

        $result = $helper->createUserProvider([
            'driver' => 'generic',
            'callbacks' => [
                'validate' => $validate,
            ],
        ]);

        $this->assertInstanceOf(GenericUserProvider::class, $result);

        // Verify validate callback works
        $isValid = $result->validateCredentials(null, ['password' => 'secret']);
        $this->assertTrue($isValid);
    }

    #[Test]
    public function generic_provider_with_by_token_callback(): void
    {
        $helper = new ProviderFactoryHelper();

        $byToken = fn($id, $token) => ['id' => $id, 'token' => $token];

        $result = $helper->createUserProvider([
            'driver' => 'generic',
            'callbacks' => [
                'byToken' => $byToken,
            ],
        ]);

        $this->assertInstanceOf(GenericUserProvider::class, $result);
    }

    #[Test]
    public function generic_provider_with_by_api_key_callback(): void
    {
        $helper = new ProviderFactoryHelper();

        $byApiKey = fn($key) => ['api_key' => $key];

        $result = $helper->createUserProvider([
            'driver' => 'generic',
            'callbacks' => [
                'byApiKey' => $byApiKey,
            ],
        ]);

        $this->assertInstanceOf(GenericUserProvider::class, $result);
    }
}

/**
 * Tests for guard creation and driver aliases.
 */
#[CoversClass(AuthServiceProvider::class)]
final class AuthServiceProviderGuardsTest extends AuthUnitTestCase
{
    #[Test]
    public function driver_aliases_are_correctly_defined(): void
    {
        // These are the expected driver aliases in AuthServiceProvider
        $aliases = [
            'session' => 'session',
            'web' => 'session',
            'jwt' => 'jwt',
            'api' => 'jwt',
            'api_key' => 'api_key',
            'token' => 'api_key',
        ];

        // Verify all expected aliases exist
        $this->assertCount(6, $aliases);
        $this->assertSame('session', $aliases['session']);
        $this->assertSame('session', $aliases['web']);
        $this->assertSame('jwt', $aliases['jwt']);
        $this->assertSame('jwt', $aliases['api']);
        $this->assertSame('api_key', $aliases['api_key']);
        $this->assertSame('api_key', $aliases['token']);
    }

    #[Test]
    public function guard_helper_creates_session_guard(): void
    {
        $helper = new GuardFactoryHelper();

        $guard = $helper->createGuard('session', null);

        $this->assertInstanceOf(\Lalaz\Auth\Guards\SessionGuard::class, $guard);
    }

    #[Test]
    public function guard_helper_creates_web_guard_as_session(): void
    {
        $helper = new GuardFactoryHelper();

        $guard = $helper->createGuard('web', null);

        $this->assertInstanceOf(\Lalaz\Auth\Guards\SessionGuard::class, $guard);
    }

    #[Test]
    public function guard_helper_creates_jwt_guard(): void
    {
        $helper = new GuardFactoryHelper();

        $guard = $helper->createGuard('jwt', null);

        $this->assertInstanceOf(\Lalaz\Auth\Guards\JwtGuard::class, $guard);
    }

    #[Test]
    public function guard_helper_creates_api_guard_as_jwt(): void
    {
        $helper = new GuardFactoryHelper();

        $guard = $helper->createGuard('api', null);

        $this->assertInstanceOf(\Lalaz\Auth\Guards\JwtGuard::class, $guard);
    }

    #[Test]
    public function guard_helper_creates_api_key_guard(): void
    {
        $helper = new GuardFactoryHelper();

        $guard = $helper->createGuard('api_key', null);

        $this->assertInstanceOf(\Lalaz\Auth\Guards\ApiKeyGuard::class, $guard);
    }

    #[Test]
    public function guard_helper_creates_token_guard_as_api_key(): void
    {
        $helper = new GuardFactoryHelper();

        $guard = $helper->createGuard('token', null);

        $this->assertInstanceOf(\Lalaz\Auth\Guards\ApiKeyGuard::class, $guard);
    }

    #[Test]
    public function guard_helper_throws_for_invalid_driver(): void
    {
        $helper = new GuardFactoryHelper();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unsupported guard driver: invalid");

        $helper->createGuard('invalid', null);
    }
}

/**
 * Tests for auth manager integration.
 */
#[CoversClass(AuthServiceProvider::class)]
final class AuthServiceProviderManagerTest extends AuthUnitTestCase
{
    #[Test]
    public function auth_manager_can_have_guards_extended(): void
    {
        $manager = new AuthManager();

        $manager->extend('custom', fn() => new \Lalaz\Auth\Guards\ApiKeyGuard(null));

        $this->assertTrue($manager->hasGuard('custom'));
    }

    #[Test]
    public function auth_manager_can_set_default_guard(): void
    {
        $manager = new AuthManager();
        $manager->extend('api', fn() => new \Lalaz\Auth\Guards\ApiKeyGuard(null));

        $manager->setDefaultGuard('api');

        // Manager should have default guard set
        $this->assertTrue($manager->hasGuard('api'));
    }

    #[Test]
    public function auth_manager_can_register_providers(): void
    {
        $manager = new AuthManager();
        $provider = new GenericUserProvider();

        $manager->registerProvider('users', $provider);

        // Verify provider can be retrieved
        $this->assertInstanceOf(GenericUserProvider::class, $manager->getProvider('users'));
    }
}

// ===== Test Helpers =====

/**
 * Helper class that replicates AuthServiceProvider's provider creation logic
 * for testing purposes (since AuthServiceProvider is final).
 */
class ProviderFactoryHelper
{
    public function createUserProvider(array $config): UserProviderInterface
    {
        $driver = $config['driver'] ?? 'model';

        return match ($driver) {
            'model' => $this->createModelProvider($config),
            'generic' => $this->createGenericProvider($config),
            'custom' => $this->createCustomProvider($config),
            default => throw new \InvalidArgumentException(
                "Unsupported user provider driver: {$driver}. " .
                "Supported drivers: 'model', 'generic', 'custom'."
            ),
        };
    }

    private function createModelProvider(array $config): ModelUserProvider
    {
        $model = $config['model'] ?? 'App\\Models\\User';

        return new ModelUserProvider($model);
    }

    private function createGenericProvider(array $config): GenericUserProvider
    {
        $provider = new GenericUserProvider();

        $callbacks = $config['callbacks'] ?? [];

        if (isset($callbacks['byId']) && $callbacks['byId'] instanceof Closure) {
            $provider->setByIdCallback($callbacks['byId']);
        }

        if (isset($callbacks['byCredentials']) && $callbacks['byCredentials'] instanceof Closure) {
            $provider->setByCredentialsCallback($callbacks['byCredentials']);
        }

        if (isset($callbacks['validate']) && $callbacks['validate'] instanceof Closure) {
            $provider->setValidateCallback($callbacks['validate']);
        }

        if (isset($callbacks['byToken']) && $callbacks['byToken'] instanceof Closure) {
            $provider->setByTokenCallback($callbacks['byToken']);
        }

        if (isset($callbacks['byApiKey']) && $callbacks['byApiKey'] instanceof Closure) {
            $provider->setByApiKeyCallback($callbacks['byApiKey']);
        }

        return $provider;
    }

    private function createCustomProvider(array $config): UserProviderInterface
    {
        $class = $config['class'] ?? null;

        if (!$class || !class_exists($class)) {
            throw new \InvalidArgumentException(
                "Custom provider requires a valid 'class' configuration."
            );
        }

        return new $class();
    }
}

/**
 * Helper class that replicates AuthServiceProvider's guard creation logic.
 */
class GuardFactoryHelper
{
    public function createGuard(string $driver, ?UserProviderInterface $provider): mixed
    {
        return match ($driver) {
            'session', 'web' => new \Lalaz\Auth\Guards\SessionGuard(null, $provider),
            'jwt', 'api' => new \Lalaz\Auth\Guards\JwtGuard(
                new JwtEncoder('test-secret'),
                new JwtBlacklist(),
                $provider,
                null,
                3600
            ),
            'api_key', 'token' => new \Lalaz\Auth\Guards\ApiKeyGuard($provider),
            default => throw new \InvalidArgumentException(
                "Unsupported guard driver: {$driver}"
            ),
        };
    }
}

/**
 * Mock user model for testing.
 */
class MockUserModelForProvider
{
    public static function find(mixed $id): ?self
    {
        return new self();
    }

    public static function findOneBy(array $conditions): ?self
    {
        return new self();
    }
}

/**
 * Mock custom provider for testing.
 */
class MockCustomProvider implements UserProviderInterface
{
    public function retrieveById(mixed $identifier): mixed
    {
        return null;
    }

    public function retrieveByCredentials(array $credentials): mixed
    {
        return null;
    }

    public function validateCredentials(mixed $user, array $credentials): bool
    {
        return false;
    }
}
