<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Common;

use Lalaz\Web\Http\Contracts\RequestInterface;

/**
 * Simple request stub for testing.
 * RequestInterface cannot be mocked due to method() method.
 */
class StubRequest implements RequestInterface
{
    public mixed $user = null;
    private string $method = 'GET';

    public function method(): string
    {
        return $this->method;
    }

    public function setMethod(string $method): void
    {
        $this->method = $method;
    }

    public function path(): string
    {
        return '/';
    }

    public function uri(): string
    {
        return '/';
    }

    public function params(): array
    {
        return [];
    }

    public function param(string $name, mixed $default = null): mixed
    {
        return $default;
    }

    public function routeParams(): array
    {
        return [];
    }

    public function routeParam(string $name, mixed $default = null): mixed
    {
        return $default;
    }

    public function queryParams(): array
    {
        return [];
    }

    public function queryParam(string $name, mixed $default = null): mixed
    {
        return $default;
    }

    public function body(): mixed
    {
        return [];
    }

    public function input(string $name, mixed $default = null): mixed
    {
        return $default;
    }

    public function all(): array
    {
        return [];
    }

    public function json(?string $key = null, mixed $default = null): mixed
    {
        return $default;
    }

    public function cookie(string $name): mixed
    {
        return null;
    }

    public function hasCookie(string $name): bool
    {
        return false;
    }

    public function isJson(): bool
    {
        return false;
    }

    public function headers(): array
    {
        return [];
    }

    public function header(string $name, mixed $default = null): mixed
    {
        return $default;
    }

    public function has(string $key): bool
    {
        return false;
    }

    public function boolean(string $key, bool $default = false): bool
    {
        return $default;
    }

    public function file(string $key): ?array
    {
        return null;
    }

    public function ip(): string
    {
        return '127.0.0.1';
    }

    public function userAgent(): string
    {
        return 'Test Agent';
    }

    public function wantsJson(): bool
    {
        return false;
    }

    public function isSecure(): bool
    {
        return false;
    }
}
