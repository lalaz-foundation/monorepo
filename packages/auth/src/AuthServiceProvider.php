<?php

declare(strict_types=1);

namespace Lalaz\Auth;

use Closure;
use Lalaz\Auth\Adapters\WebSessionAdapter;
use Lalaz\Auth\Contracts\SessionInterface;
use Lalaz\Auth\Contracts\UserProviderInterface;
use Lalaz\Auth\Guards\ApiKeyGuard;
use Lalaz\Auth\Guards\JwtGuard;
use Lalaz\Auth\Guards\SessionGuard;
use Lalaz\Auth\Jwt\JwtBlacklist;
use Lalaz\Auth\Jwt\JwtEncoder;
use Lalaz\Auth\Providers\GenericUserProvider;
use Lalaz\Auth\Providers\ModelUserProvider;
use Lalaz\Container\ServiceProvider;

/**
 * Service provider for the Auth package.
 *
 * Registers authentication context, session bindings, guards, and
 * user providers based on configuration.
 *
 * @package Lalaz\Auth
 */
final class AuthServiceProvider extends ServiceProvider
{
    /**
     * Guard driver mappings.
     *
     * @var array<string, string>
     * @phpstan-ignore-next-line Used for driver resolution
     */
    private const DRIVER_ALIASES = [
        'session' => 'session',
        'web' => 'session',
        'jwt' => 'jwt',
        'api' => 'jwt',
        'api_key' => 'api_key',
        'token' => 'api_key',
    ];

    /**
     * User provider instances.
     *
     * @var array<string, UserProviderInterface>
     */
    private array $providers = [];

    /**
     * Register auth services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->registerAuthContext();
        $this->registerSessionAdapter();
        $this->registerJwtComponents();
        $this->registerUserProviders();
        $this->registerAuthManager();
    }

    /**
     * Register the authentication context.
     *
     * @return void
     */
    private function registerAuthContext(): void
    {
        $this->singleton(AuthContext::class, function () {
            $defaultGuard = $this->config('auth.defaults.guard', 'web');
            $context = new AuthContext();
            $context->setDefaultGuard($defaultGuard);

            return $context;
        });
    }

    /**
     * Register the session adapter.
     *
     * If the Web package is available, it will use the WebSessionAdapter
     * to integrate with the Web package's SessionManager.
     *
     * @return void
     */
    private function registerSessionAdapter(): void
    {
        $this->singleton(SessionInterface::class, function ($container) {
            // Check if Web package's SessionManager is available
            if (class_exists(\Lalaz\Web\Http\SessionManager::class)) {
                try {
                    $sessionManager = $container->get(\Lalaz\Web\Http\SessionManager::class);
                    return new WebSessionAdapter($sessionManager);
                } catch (\Throwable) {
                    // Web package not properly configured, use fallback
                }
            }

            // Return null - auth will use direct PHP session
            return null;
        });
    }

    /**
     * Register JWT components.
     *
     * @return void
     */
    private function registerJwtComponents(): void
    {
        $this->singleton(JwtEncoder::class, function () {
            $secret = $this->config('auth.jwt.secret', env('JWT_SECRET', ''));
            $algorithm = $this->config('auth.jwt.algorithm', 'HS256');

            return new JwtEncoder($secret, $algorithm);
        });

        $this->singleton(JwtBlacklist::class, function () {
            return new JwtBlacklist();
        });
    }

    /**
     * Register user providers from configuration.
     *
     * @return void
     */
    private function registerUserProviders(): void
    {
        $providersConfig = $this->config('auth.providers', []);

        foreach ($providersConfig as $name => $config) {
            $this->providers[$name] = $this->createUserProvider($config);
        }

        // Register the default provider in the container
        $defaultProvider = $this->config('auth.defaults.provider', 'users');

        if (isset($this->providers[$defaultProvider])) {
            $this->singleton(UserProviderInterface::class, function () use ($defaultProvider) {
                return $this->providers[$defaultProvider];
            });
        }
    }

    /**
     * Create a user provider from configuration.
     *
     * @param array<string, mixed> $config
     * @return UserProviderInterface
     */
    private function createUserProvider(array $config): UserProviderInterface
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

    /**
     * Create a custom user provider from a class.
     *
     * @param array<string, mixed> $config
     * @return UserProviderInterface
     */
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

    /**
     * Create a model-based user provider.
     *
     * @param array<string, mixed> $config
     * @return ModelUserProvider
     */
    private function createModelProvider(array $config): ModelUserProvider
    {
        $model = $config['model'] ?? 'App\\Models\\User';

        return new ModelUserProvider($model);
    }

    /**
     * Create a generic callback-based user provider.
     *
     * @param array<string, mixed> $config
     * @return GenericUserProvider
     */
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

    /**
     * Register the auth manager with guards from configuration.
     *
     * @return void
     */
    private function registerAuthManager(): void
    {
        $this->singleton(AuthManager::class, function ($container) {
            $manager = new AuthManager();

            // Register base guard drivers
            $this->registerGuardDrivers($manager, $container);

            // Register configured guards
            $this->registerConfiguredGuards($manager, $container);

            // Register user providers in the manager
            foreach ($this->providers as $name => $provider) {
                $manager->registerProvider($name, $provider);
            }

            // Set default guard from config
            $defaultGuard = $this->config('auth.defaults.guard', 'web');
            $manager->setDefaultGuard($defaultGuard);

            return $manager;
        });
    }

    /**
     * Register the base guard drivers.
     *
     * @param AuthManager $manager
     * @param mixed $container
     * @return void
     */
    private function registerGuardDrivers(AuthManager $manager, mixed $container): void
    {
        // Session guard driver
        $manager->extend('session', function () use ($container) {
            $session = $container->get(SessionInterface::class);
            $provider = $this->getProvider($this->config('auth.defaults.provider', 'users'));

            return new SessionGuard($session, $provider);
        });

        // JWT guard driver
        $manager->extend('jwt', function () use ($container) {
            $encoder = $container->get(JwtEncoder::class);
            $blacklist = $container->get(JwtBlacklist::class);
            $provider = $this->getProvider($this->config('auth.defaults.provider', 'users'));
            $ttl = $this->config('auth.jwt.ttl', 3600);

            return new JwtGuard($encoder, $blacklist, $provider, $ttl);
        });

        // API Key guard driver
        $manager->extend('api_key', function () {
            $provider = $this->getProvider($this->config('auth.defaults.provider', 'users'));

            return new ApiKeyGuard($provider);
        });
    }

    /**
     * Register guards from configuration.
     *
     * @param AuthManager $manager
     * @param mixed $container
     * @return void
     */
    private function registerConfiguredGuards(AuthManager $manager, mixed $container): void
    {
        $guardsConfig = $this->config('auth.guards', []);

        foreach ($guardsConfig as $name => $config) {
            // Register each configured guard using its driver
            $manager->extend($name, function () use ($container, $config) {
                return $this->createGuard($config, $container);
            });
        }
    }

    /**
     * Create a guard from configuration.
     *
     * @param array<string, mixed> $config
     * @param mixed $container
     * @return mixed
     */
    private function createGuard(array $config, mixed $container): mixed
    {
        $driver = $config['driver'] ?? 'session';
        $providerName = $config['provider'] ?? $this->config('auth.defaults.provider', 'users');
        $provider = $this->getProvider($providerName);

        return match ($driver) {
            'session', 'web' => new SessionGuard(
                $container->get(SessionInterface::class),
                $provider
            ),
            'jwt', 'api' => new JwtGuard(
                $container->get(JwtEncoder::class),
                $container->get(JwtBlacklist::class),
                $provider,
                $this->config('auth.jwt.ttl', 3600)
            ),
            'api_key', 'token' => new ApiKeyGuard($provider),
            default => throw new \InvalidArgumentException(
                "Unsupported guard driver: {$driver}"
            ),
        };
    }

    /**
     * Get a user provider by name.
     *
     * @param string $name
     * @return UserProviderInterface|null
     */
    private function getProvider(string $name): ?UserProviderInterface
    {
        return $this->providers[$name] ?? null;
    }

    /**
     * Resolve the user provider (legacy support).
     *
     * @return UserProviderInterface|null
     * @phpstan-ignore-next-line Reserved for future use
     */
    private function resolveUserProvider(): ?UserProviderInterface
    {
        try {
            return $this->container->get(UserProviderInterface::class);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Get a configuration value.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    private function config(string $key, mixed $default = null): mixed
    {
        // Try to use config function if available
        if (function_exists('config')) {
            return config($key, $default);
        }

        return $default;
    }
}
