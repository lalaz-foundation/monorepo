<?php

declare(strict_types=1);

namespace Lalaz\Auth\Guards;

use Lalaz\Auth\Contracts\AuthenticatableInterface;
use Lalaz\Auth\Contracts\GuardInterface;
use Lalaz\Auth\Contracts\UserProviderInterface;

/**
 * Base Guard
 *
 * Abstract base class with common guard functionality.
 *
 * @package Lalaz\Auth\Guards
 */
abstract class BaseGuard implements GuardInterface
{
    /**
     * The user provider instance.
     *
     * @var UserProviderInterface|null
     */
    protected ?UserProviderInterface $provider = null;

    /**
     * The currently authenticated user.
     *
     * @var mixed
     */
    protected mixed $user = null;

    /**
     * Create a new guard instance.
     *
     * @param UserProviderInterface|null $provider
     */
    public function __construct(?UserProviderInterface $provider = null)
    {
        $this->provider = $provider;
    }

    /**
     * {@inheritdoc}
     */
    public function setProvider(UserProviderInterface $provider): void
    {
        $this->provider = $provider;
    }

    /**
     * {@inheritdoc}
     */
    public function check(): bool
    {
        return $this->user() !== null;
    }

    /**
     * {@inheritdoc}
     */
    public function guest(): bool
    {
        return !$this->check();
    }

    /**
     * {@inheritdoc}
     */
    public function id(): mixed
    {
        $user = $this->user();

        if ($user === null) {
            return null;
        }

        return $this->getUserId($user);
    }

    /**
     * {@inheritdoc}
     */
    public function validate(array $credentials): bool
    {
        if ($this->provider === null) {
            return false;
        }

        $user = $this->provider->retrieveByCredentials($credentials);

        if ($user === null) {
            return false;
        }

        return $this->provider->validateCredentials($user, $credentials);
    }

    /**
     * Get the user ID from a user instance.
     *
     * Extraction priority:
     * 1. AuthenticatableInterface - canonical interface for authentication
     * 2. Arrays - supports JWT tokens (id, sub, user_id) and API payloads
     * 3. Generic objects - fallback for getId() method or 'id' property
     *
     * @param mixed $user
     * @return mixed
     */
    protected function getUserId(mixed $user): mixed
    {
        // Priority 1: AuthenticatableInterface (canonical)
        if ($user instanceof AuthenticatableInterface) {
            return $user->getAuthIdentifier();
        }

        // Priority 2: Arrays (JWT tokens, decoded payloads, API data)
        if (is_array($user)) {
            return $user['id'] ?? $user['sub'] ?? $user['user_id'] ?? null;
        }

        // Priority 3: Generic objects (fallback)
        if (is_object($user)) {
            if (method_exists($user, 'getId')) {
                return $user->getId();
            }

            return $user->id ?? null;
        }

        return null;
    }

    /**
     * Get a claim/attribute from a user.
     *
     * @param mixed $user
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function getUserAttribute(mixed $user, string $key, mixed $default = null): mixed
    {
        if (is_array($user)) {
            return $user[$key] ?? $default;
        }

        if (is_object($user)) {
            if (method_exists($user, 'getAttribute')) {
                return $user->getAttribute($key) ?? $default;
            }

            return $user->{$key} ?? $default;
        }

        return $default;
    }
}
