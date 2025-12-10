<?php

declare(strict_types=1);

namespace Lalaz\Auth\Contracts;

/**
 * Contract for session storage used by authentication.
 *
 * This interface allows the auth package to work with different
 * session implementations (e.g., from the web package or custom).
 *
 * @package Lalaz\Auth\Contracts
 */
interface SessionInterface
{
    /**
     * Start or resume a session.
     *
     * @return void
     */
    public function start(): void;

    /**
     * Set a session value.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function set(string $key, mixed $value): void;

    /**
     * Get a session value.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Check if a session key exists.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Remove a session value.
     *
     * @param string $key
     * @return void
     */
    public function remove(string $key): void;

    /**
     * Regenerate the session ID.
     *
     * @param bool $deleteOldSession
     * @return void
     */
    public function regenerate(bool $deleteOldSession = true): void;

    /**
     * Destroy the session completely.
     *
     * @return void
     */
    public function destroy(): void;
}
