<?php

declare(strict_types=1);

namespace Lalaz\Auth\Contracts;

/**
 * API Key Provider Interface
 *
 * Contract for providers that support API key authentication.
 * This interface is separate from UserProviderInterface following
 * the Interface Segregation Principle (ISP).
 *
 * Implement this interface when your provider needs to support
 * API key-based authentication for server-to-server communication.
 *
 * @package Lalaz\Auth\Contracts
 */
interface ApiKeyProviderInterface
{
    /**
     * Retrieve a user by their API key.
     *
     * The implementation should handle API key hashing/comparison
     * according to the application's security requirements.
     *
     * @param string $apiKey The API key (typically unhashed from request).
     * @return mixed The user or null if not found or key is invalid.
     */
    public function retrieveByApiKey(string $apiKey): mixed;
}
