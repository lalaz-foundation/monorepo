<?php

declare(strict_types=1);

namespace Lalaz\Auth\Contracts;

/**
 * Guard Interface
 *
 * Contract for authentication guards. Each guard implements a different
 * authentication strategy (session, JWT, API key, etc.).
 *
 * @package Lalaz\Auth\Contracts
 */
interface GuardInterface
{
    /**
     * Get the guard name/identifier.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Attempt to authenticate a user with credentials.
     *
     * @param array<string, mixed> $credentials
     * @return mixed The authenticated user or null on failure.
     */
    public function attempt(array $credentials): mixed;

    /**
     * Log a user in directly (without credentials).
     *
     * @param mixed $user The user to authenticate.
     * @return void
     */
    public function login(mixed $user): void;

    /**
     * Log the current user out.
     *
     * @return void
     */
    public function logout(): void;

    /**
     * Get the currently authenticated user.
     *
     * @return mixed The user or null if not authenticated.
     */
    public function user(): mixed;

    /**
     * Check if a user is currently authenticated.
     *
     * @return bool
     */
    public function check(): bool;

    /**
     * Check if the current user is a guest (not authenticated).
     *
     * @return bool
     */
    public function guest(): bool;

    /**
     * Get the ID of the currently authenticated user.
     *
     * @return mixed The user ID or null.
     */
    public function id(): mixed;

    /**
     * Validate credentials without persisting authentication.
     *
     * @param array<string, mixed> $credentials
     * @return bool
     */
    public function validate(array $credentials): bool;

    /**
     * Set the user provider for this guard.
     *
     * @param UserProviderInterface $provider
     * @return void
     */
    public function setProvider(UserProviderInterface $provider): void;
}
