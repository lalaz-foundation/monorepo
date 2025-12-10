<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Common;

/**
 * Mock session manager for testing WebSessionAdapter.
 */
class MockSessionManager
{
    private array $data = [];
    private bool $started = false;
    private bool $destroyed = false;
    private bool $regenerated = false;

    public function start(): void
    {
        $this->started = true;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    public function regenerate(bool $deleteOldSession = true): void
    {
        $this->regenerated = true;
    }

    public function destroy(): void
    {
        $this->destroyed = true;
        $this->data = [];
    }

    public function isStarted(): bool
    {
        return $this->started;
    }

    public function isDestroyed(): bool
    {
        return $this->destroyed;
    }

    public function wasRegenerated(): bool
    {
        return $this->regenerated;
    }
}
