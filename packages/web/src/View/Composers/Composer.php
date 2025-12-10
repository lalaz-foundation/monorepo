<?php

declare(strict_types=1);

namespace Lalaz\Web\View\Composers;

use Lalaz\Web\View\Contracts\ViewComposerInterface;

abstract class Composer implements ViewComposerInterface
{
    abstract public function compose(array $data): array;

    protected function mergeData(
        array $data,
        array $newData,
        bool $overwrite = false,
    ): array {
        if ($overwrite) {
            return array_merge($data, $newData);
        }

        // Only add keys that don't exist
        foreach ($newData as $key => $value) {
            if (!array_key_exists($key, $data)) {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    protected function lazy(callable $callback): \Closure
    {
        return function () use ($callback) {
            static $result = null;
            if ($result === null) {
                $result = $callback();
            }
            return $result;
        };
    }

    protected function has(array $data, string $key): bool
    {
        return array_key_exists($key, $data);
    }

    protected function get(
        array $data,
        string $key,
        mixed $default = null,
    ): mixed {
        return $data[$key] ?? $default;
    }
}
