<?php

declare(strict_types=1);

namespace Lalaz\Auth\Contracts;

/**
 * Remember Token Provider Interface
 *
 * Contract for providers that support remember me functionality.
 * This interface is separate from UserProviderInterface following
 * the Interface Segregation Principle (ISP).
 *
 * Implement this interface when your provider needs to support
 * session-based authentication with remember me tokens.
 *
 * @package Lalaz\Auth\Contracts
 */
interface RememberTokenProviderInterface
{
    /**
     * Retrieve a user by their unique identifier and remember token.
     *
     * @param mixed $identifier The user's unique identifier.
     * @param string $token The remember token.
     * @return mixed The user or null if not found.
     */
    public function retrieveByToken(mixed $identifier, string $token): mixed;

    /**
     * Update the remember token for the given user.
     *
     * @param mixed $user The user instance.
     * @param string $token The new remember token.
     * @return void
     */
    public function updateRememberToken(mixed $user, string $token): void;
}
