<?php

declare(strict_types=1);

namespace Lalaz\Auth\Contracts;

/**
 * User Provider Interface
 *
 * Base contract for retrieving users from storage.
 * Implementations can use ORM, database queries, or any other storage.
 *
 * This interface contains only the core methods needed by all authentication
 * guards. For additional functionality, implement the segregated interfaces:
 *
 * - {@see RememberTokenProviderInterface} - For remember me functionality
 * - {@see ApiKeyProviderInterface} - For API key authentication
 *
 * @package Lalaz\Auth\Contracts
 */
interface UserProviderInterface
{
    /**
     * Retrieve a user by their unique identifier.
     *
     * @param mixed $identifier The user's unique identifier (e.g., ID).
     * @return mixed The user or null if not found.
     */
    public function retrieveById(mixed $identifier): mixed;

    /**
     * Retrieve a user by credentials (e.g., email/password).
     *
     * The implementation should NOT validate the password here.
     * Use validateCredentials() for password validation.
     *
     * @param array<string, mixed> $credentials The credentials to search by.
     * @return mixed The user or null if not found.
     */
    public function retrieveByCredentials(array $credentials): mixed;

    /**
     * Validate a user against the given credentials.
     *
     * Typically used to verify the password matches the stored hash.
     *
     * @param mixed $user The user instance.
     * @param array<string, mixed> $credentials The credentials containing password.
     * @return bool True if credentials are valid.
     */
    public function validateCredentials(mixed $user, array $credentials): bool;
}
