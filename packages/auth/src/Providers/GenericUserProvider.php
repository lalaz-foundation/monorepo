<?php

declare(strict_types=1);

namespace Lalaz\Auth\Providers;

use Closure;
use Lalaz\Auth\Contracts\ApiKeyProviderInterface;
use Lalaz\Auth\Contracts\AuthenticatableInterface;
use Lalaz\Auth\Contracts\PasswordHasherInterface;
use Lalaz\Auth\Contracts\RememberTokenProviderInterface;
use Lalaz\Auth\Contracts\UserProviderInterface;
use Lalaz\Auth\NativePasswordHasher;

/**
 * Generic User Provider
 *
 * A flexible user provider that uses callbacks for user retrieval.
 * Useful when you don't have an ORM or need custom logic.
 *
 * Implements:
 * - UserProviderInterface - Core user retrieval and credential validation
 * - RememberTokenProviderInterface - Remember me token support
 * - ApiKeyProviderInterface - API key authentication support
 *
 * @package Lalaz\Auth\Providers
 */
class GenericUserProvider implements
    UserProviderInterface,
    RememberTokenProviderInterface,
    ApiKeyProviderInterface
{
    /**
     * The password hasher instance.
     *
     * @var PasswordHasherInterface
     */
    private PasswordHasherInterface $hasher;

    /**
     * Callback to retrieve user by ID.
     *
     * @var Closure|null
     */
    private ?Closure $byIdCallback = null;

    /**
     * Callback to retrieve user by credentials.
     *
     * @var Closure|null
     */
    private ?Closure $byCredentialsCallback = null;

    /**
     * Callback to validate credentials.
     *
     * @var Closure|null
     */
    private ?Closure $validateCallback = null;

    /**
     * Callback to retrieve user by token.
     *
     * @var Closure|null
     */
    private ?Closure $byTokenCallback = null;

    /**
     * Callback to retrieve user by API key.
     *
     * @var Closure|null
     */
    private ?Closure $byApiKeyCallback = null;

    /**
     * Callback to update remember token.
     *
     * @var Closure|null
     */
    private ?Closure $updateTokenCallback = null;

    /**
     * Create a new generic user provider.
     *
     * @param PasswordHasherInterface|null $hasher The password hasher (defaults to NativePasswordHasher).
     */
    public function __construct(?PasswordHasherInterface $hasher = null)
    {
        $this->hasher = $hasher ?? new NativePasswordHasher();
    }

    /**
     * Set the callback for retrieving users by ID.
     *
     * @param Closure $callback fn(mixed $id): ?AuthenticatableInterface
     * @return self
     */
    public function setByIdCallback(Closure $callback): self
    {
        $this->byIdCallback = $callback;
        return $this;
    }

    /**
     * Set the callback for retrieving users by credentials.
     *
     * @param Closure $callback fn(array $credentials): ?AuthenticatableInterface
     * @return self
     */
    public function setByCredentialsCallback(Closure $callback): self
    {
        $this->byCredentialsCallback = $callback;
        return $this;
    }

    /**
     * Set the callback for validating credentials.
     *
     * @param Closure $callback fn(AuthenticatableInterface $user, array $credentials): bool
     * @return self
     */
    public function setValidateCallback(Closure $callback): self
    {
        $this->validateCallback = $callback;
        return $this;
    }

    /**
     * Set the callback for retrieving users by token.
     *
     * @param Closure $callback fn(mixed $identifier, string $token): ?AuthenticatableInterface
     * @return self
     */
    public function setByTokenCallback(Closure $callback): self
    {
        $this->byTokenCallback = $callback;
        return $this;
    }

    /**
     * Set the callback for updating remember token.
     *
     * @param Closure $callback fn(mixed $user, string $token): void
     * @return self
     */
    public function setUpdateTokenCallback(Closure $callback): self
    {
        $this->updateTokenCallback = $callback;
        return $this;
    }

    /**
     * Set the callback for retrieving users by API key.
     *
     * @param Closure $callback fn(string $apiKey): ?AuthenticatableInterface
     * @return self
     */
    public function setByApiKeyCallback(Closure $callback): self
    {
        $this->byApiKeyCallback = $callback;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveById(mixed $identifier): mixed
    {
        if ($this->byIdCallback === null) {
            return null;
        }

        return ($this->byIdCallback)($identifier);
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveByCredentials(array $credentials): mixed
    {
        if ($this->byCredentialsCallback === null) {
            return null;
        }

        return ($this->byCredentialsCallback)($credentials);
    }

    /**
     * {@inheritdoc}
     */
    public function validateCredentials(mixed $user, array $credentials): bool
    {
        if ($this->validateCallback !== null) {
            return ($this->validateCallback)($user, $credentials);
        }

        // Default validation using hasher
        $password = $credentials['password'] ?? null;

        if ($password === null) {
            return false;
        }

        if (!method_exists($user, 'getAuthPassword')) {
            return false;
        }

        return $this->hasher->verify($password, $user->getAuthPassword());
    }

    /**
     * Check if a user's password needs rehashing.
     *
     * @param mixed $user The user instance.
     * @return bool True if rehashing is recommended.
     */
    public function passwordNeedsRehash(mixed $user): bool
    {
        if (!method_exists($user, 'getAuthPassword')) {
            return false;
        }

        return $this->hasher->needsRehash($user->getAuthPassword());
    }

    /**
     * Hash a plain text password.
     *
     * @param string $password The plain text password.
     * @return string The hashed password.
     */
    public function hashPassword(string $password): string
    {
        return $this->hasher->hash($password);
    }

    /**
     * Get the password hasher instance.
     *
     * @return PasswordHasherInterface
     */
    public function getHasher(): PasswordHasherInterface
    {
        return $this->hasher;
    }

    /**
     * Set the password hasher instance.
     *
     * @param PasswordHasherInterface $hasher
     * @return self
     */
    public function setHasher(PasswordHasherInterface $hasher): self
    {
        $this->hasher = $hasher;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveByToken(mixed $identifier, string $token): mixed
    {
        if ($this->byTokenCallback === null) {
            return null;
        }

        return ($this->byTokenCallback)($identifier, $token);
    }

    /**
     * {@inheritdoc}
     */
    public function updateRememberToken(mixed $user, string $token): void
    {
        if ($this->updateTokenCallback !== null) {
            ($this->updateTokenCallback)($user, $token);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveByApiKey(string $apiKey): mixed
    {
        if ($this->byApiKeyCallback === null) {
            return null;
        }

        return ($this->byApiKeyCallback)($apiKey);
    }

    /**
     * Create a configured instance with common callbacks.
     *
     * @param Closure $byId fn(mixed $id): ?AuthenticatableInterface
     * @param Closure $byCredentials fn(array $credentials): ?AuthenticatableInterface
     * @param PasswordHasherInterface|null $hasher Optional password hasher.
     * @return self
     */
    public static function create(
        Closure $byId,
        Closure $byCredentials,
        ?PasswordHasherInterface $hasher = null,
    ): self {
        return (new self($hasher))
            ->setByIdCallback($byId)
            ->setByCredentialsCallback($byCredentials);
    }
}
