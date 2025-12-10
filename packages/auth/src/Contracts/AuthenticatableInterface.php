<?php

declare(strict_types=1);

namespace Lalaz\Auth\Contracts;

/**
 * Contract for entities that can be authenticated.
 *
 * Models implementing this interface can be used with the
 * authentication system to verify credentials and manage sessions.
 *
 * @package Lalaz\Auth\Contracts
 */
interface AuthenticatableInterface
{
    /**
     * Get the unique identifier for the user.
     *
     * @return mixed
     */
    public function getAuthIdentifier(): mixed;

    /**
     * Get the name of the unique identifier column.
     *
     * @return string
     */
    public function getAuthIdentifierName(): string;

    /**
     * Get the password for the user.
     *
     * @return string
     */
    public function getAuthPassword(): string;

    /**
     * Get the remember token for the user.
     *
     * @return string|null
     */
    public function getRememberToken(): ?string;

    /**
     * Set the remember token for the user.
     *
     * @param string|null $value
     * @return void
     */
    public function setRememberToken(?string $value): void;

    /**
     * Get the column name for the remember token.
     *
     * @return string
     */
    public function getRememberTokenName(): string;
}
