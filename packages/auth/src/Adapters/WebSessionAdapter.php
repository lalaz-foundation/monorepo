<?php

declare(strict_types=1);

namespace Lalaz\Auth\Adapters;

use Lalaz\Auth\Contracts\SessionInterface;
use Lalaz\Web\Http\SessionManager;

/**
 * Web Session Adapter
 *
 * Adapts the Web package's SessionManager to the Auth package's
 * SessionInterface, allowing seamless integration between packages.
 *
 * @package Lalaz\Auth\Adapters
 */
class WebSessionAdapter implements SessionInterface
{
    /**
     * The underlying session manager.
     *
     * @var SessionManager
     */
    private SessionManager $sessionManager;

    /**
     * Create a new adapter instance.
     *
     * @param SessionManager $sessionManager The web session manager.
     */
    public function __construct(SessionManager $sessionManager)
    {
        $this->sessionManager = $sessionManager;
    }

    /**
     * {@inheritdoc}
     */
    public function start(): void
    {
        $this->sessionManager->start();
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value): void
    {
        $this->sessionManager->set($key, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->sessionManager->get($key, $default);
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        return $this->sessionManager->has($key);
    }

    /**
     * {@inheritdoc}
     */
    public function remove(string $key): void
    {
        $this->sessionManager->remove($key);
    }

    /**
     * {@inheritdoc}
     */
    public function regenerate(bool $deleteOldSession = true): void
    {
        $this->sessionManager->regenerate($deleteOldSession);
    }

    /**
     * {@inheritdoc}
     */
    public function destroy(): void
    {
        $this->sessionManager->destroy();
    }

    /**
     * Get the underlying session manager.
     *
     * @return SessionManager
     */
    public function getSessionManager(): SessionManager
    {
        return $this->sessionManager;
    }
}
