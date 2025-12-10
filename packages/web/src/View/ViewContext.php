<?php

declare(strict_types=1);

namespace Lalaz\Web\View;

use Lalaz\Support\LazyObject;

class ViewContext
{
    protected static array $data = [];

    public static function set(string $key, mixed $value): void
    {
        self::$data[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (!\array_key_exists($key, self::$data)) {
            return $default;
        }

        $value = self::$data[$key];

        return is_callable($value) ? $value() : $value;
    }

    public static function resolved(): array
    {
        $resolved = [];

        foreach (self::$data as $key => $value) {
            $resolved[$key] = is_callable($value)
                ? new LazyObject($value)
                : $value;
        }

        return $resolved;
    }

    public static function reset(): void
    {
        self::$data = [];
    }
}
