<?php

declare(strict_types=1);

namespace Lalaz\Auth;

use Closure;
use InvalidArgumentException;
use Lalaz\Auth\Contracts\GuardInterface;
use Lalaz\Auth\Contracts\UserProviderInterface;

/**
 * Auth Manager
 *
 * Manages multiple authentication guards and provides a unified
 * interface for authentication across different strategies.
 *
 * @package Lalaz\Auth
 */
class AuthManager
{
    /**
     * Registered guards.
     *
     * @var array<string, GuardInterface>
     */
    private array $guards = [];

    /**
     * Guard resolvers.
     *
     * @var array<string, Closure>
     */
    private array $customCreators = [];

    /**
     * Default guard name.
     *
     * @var string
     */
    private string $defaultGuard = 'session';

    /**
     * User providers.
     *
     * @var array<string, UserProviderInterface>
     */
    private array $providers = [];

    /**
     * Get a guard instance.
     *
     * @param string|null $name
     * @return GuardInterface
     * @throws InvalidArgumentException
     */
    public function guard(?string $name = null): GuardInterface
    {
        $name = $name ?? $this->defaultGuard;

        if (!isset($this->guards[$name])) {
            $this->guards[$name] = $this->resolve($name);
        }

        return $this->guards[$name];
    }

    /**
     * Resolve a guard by name.
     *
     * @param string $name
     * @return GuardInterface
     * @throws InvalidArgumentException
     */
    protected function resolve(string $name): GuardInterface
    {
        if (isset($this->customCreators[$name])) {
            return ($this->customCreators[$name])($name, $this);
        }

        throw new InvalidArgumentException(
            "Auth guard [{$name}] is not defined. Use extend() to register it."
        );
    }

    /**
     * Register a custom guard creator.
     *
     * @param string $name
     * @param Closure $callback
     * @return self
     */
    public function extend(string $name, Closure $callback): self
    {
        $this->customCreators[$name] = $callback;
        return $this;
    }

    /**
     * Register a guard instance directly.
     *
     * @param string $name
     * @param GuardInterface $guard
     * @return self
     */
    public function register(string $name, GuardInterface $guard): self
    {
        $this->guards[$name] = $guard;
        return $this;
    }

    /**
     * Register a user provider.
     *
     * @param string $name
     * @param UserProviderInterface $provider
     * @return self
     */
    public function registerProvider(string $name, UserProviderInterface $provider): self
    {
        $this->providers[$name] = $provider;
        return $this;
    }

    /**
     * Get a user provider.
     *
     * @param string $name
     * @return UserProviderInterface|null
     */
    public function getProvider(string $name): ?UserProviderInterface
    {
        return $this->providers[$name] ?? null;
    }

    /**
     * Set the default guard.
     *
     * @param string $name
     * @return self
     */
    public function setDefaultGuard(string $name): self
    {
        $this->defaultGuard = $name;
        return $this;
    }

    /**
     * Get the default guard name.
     *
     * @return string
     */
    public function getDefaultGuard(): string
    {
        return $this->defaultGuard;
    }

    /**
     * Check if a guard is registered.
     *
     * @param string $name
     * @return bool
     */
    public function hasGuard(string $name): bool
    {
        return isset($this->guards[$name]) || isset($this->customCreators[$name]);
    }

    /**
     * Get all registered guard names.
     *
     * @return array<string>
     */
    public function getGuardNames(): array
    {
        return array_unique(array_merge(
            array_keys($this->guards),
            array_keys($this->customCreators)
        ));
    }

    /**
     * Forget a guard instance.
     *
     * @param string $name
     * @return void
     */
    public function forgetGuard(string $name): void
    {
        unset($this->guards[$name]);
    }

    /**
     * Forget all guard instances.
     *
     * @return void
     */
    public function forgetGuards(): void
    {
        $this->guards = [];
    }

    /**
     * Dynamically call the default guard.
     *
     * @param string $method
     * @param array<int, mixed> $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->guard()->{$method}(...$parameters);
    }

    // ===== Convenience methods that delegate to the default guard =====

    /**
     * Check if a user is authenticated.
     *
     * @return bool
     */
    public function check(): bool
    {
        return $this->guard()->check();
    }

    /**
     * Check if a user is a guest.
     *
     * @return bool
     */
    public function guest(): bool
    {
        return $this->guard()->guest();
    }

    /**
     * Get the authenticated user.
     *
     * @return mixed
     */
    public function user(): mixed
    {
        return $this->guard()->user();
    }

    /**
     * Get the authenticated user's ID.
     *
     * @return mixed
     */
    public function id(): mixed
    {
        return $this->guard()->id();
    }

    /**
     * Attempt to authenticate.
     *
     * @param array<string, mixed> $credentials
     * @return mixed
     */
    public function attempt(array $credentials): mixed
    {
        return $this->guard()->attempt($credentials);
    }

    /**
     * Login a user.
     *
     * @param mixed $user
     * @return void
     */
    public function login(mixed $user): void
    {
        $this->guard()->login($user);
    }

    /**
     * Logout the user.
     *
     * @return void
     */
    public function logout(): void
    {
        $this->guard()->logout();
    }
}
