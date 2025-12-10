<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Common;

use Lalaz\Auth\Contracts\SessionInterface;

/**
 * Fake session for testing authentication.
 */
class FakeSession implements SessionInterface
{
    private array $data = [];
    private bool $started = false;
    private string $id = '';

    public function __construct()
    {
        $this->id = bin2hex(random_bytes(16));
    }

    public function start(): void
    {
        $this->started = true;
    }

    public function isStarted(): bool
    {
        return $this->started;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function regenerate(bool $deleteOldSession = true): void
    {
        $this->id = bin2hex(random_bytes(16));
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    public function clear(): void
    {
        $this->data = [];
    }

    public function destroy(): void
    {
        $this->data = [];
        $this->started = false;
    }

    public function all(): array
    {
        return $this->data;
    }
}
