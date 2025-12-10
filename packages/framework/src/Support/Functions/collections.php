<?php

declare(strict_types=1);

/**
 * Collection helper functions.
 *
 * Provides global helper functions for working with collections
 * and accessing nested array/object values.
 *
 * @package lalaz/framework
 * @author Lalaz Framework <hello@lalaz.dev>
 * @link https://lalaz.dev
 */

use Lalaz\Support\Collections\Collection;

if (!function_exists('collect')) {
    /**
     * Create a new collection from the given value.
     *
     * @template TKey of array-key
     * @template TValue
     * @param mixed $value
     * @return Collection<TKey, TValue>
     *
     * @example
     * ```php
     * // From array
     * $users = collect([
     *     ['name' => 'John', 'age' => 30],
     *     ['name' => 'Jane', 'age' => 25],
     * ]);
     *
     * // Chain operations
     * $names = collect($users)
     *     ->filter(fn($u) => $u['age'] >= 25)
     *     ->pluck('name')
     *     ->all();
     *
     * // From empty
     * $empty = collect();
     * ```
     */
    function collect(mixed $value = []): Collection
    {
        return Collection::create($value);
    }
}

if (!function_exists('data_get')) {
    /**
     * Get an item from an array or object using "dot" notation.
     *
     * @param mixed $target The array or object to access
     * @param string|array|int|null $key The key using dot notation
     * @param mixed $default The default value if key not found
     * @return mixed
     *
     * @example
     * ```php
     * $data = [
     *     'user' => [
     *         'name' => 'John',
     *         'address' => [
     *             'city' => 'New York',
     *             'zip' => '10001'
     *         ]
     *     ]
     * ];
     *
     * data_get($data, 'user.name');           // "John"
     * data_get($data, 'user.address.city');   // "New York"
     * data_get($data, 'user.email', 'N/A');   // "N/A"
     *
     * // Works with objects too
     * $user = new stdClass;
     * $user->name = 'Jane';
     * data_get($user, 'name');                // "Jane"
     *
     * // Wildcard support
     * $users = [
     *     ['name' => 'John'],
     *     ['name' => 'Jane'],
     * ];
     * data_get($users, '*.name');             // ["John", "Jane"]
     * ```
     */
    function data_get(mixed $target, string|array|int|null $key, mixed $default = null): mixed
    {
        if ($key === null) {
            return $target;
        }

        $key = is_array($key) ? $key : explode('.', (string) $key);

        foreach ($key as $i => $segment) {
            unset($key[$i]);

            if ($segment === '*') {
                if (!is_iterable($target)) {
                    return value($default);
                }

                $result = [];

                foreach ($target as $item) {
                    $result[] = data_get($item, implode('.', $key));
                }

                return in_array('*', $key) ? collect($result)->collapse()->all() : $result;
            }

            if (is_array($target) && array_key_exists($segment, $target)) {
                $target = $target[$segment];
            } elseif (is_object($target)) {
                if (isset($target->{$segment})) {
                    $target = $target->{$segment};
                } elseif (method_exists($target, $segment)) {
                    $target = $target->{$segment}();
                } else {
                    return value($default);
                }
            } else {
                return value($default);
            }
        }

        return $target;
    }
}

if (!function_exists('data_set')) {
    /**
     * Set an item on an array or object using "dot" notation.
     *
     * @param mixed $target The array or object to modify
     * @param string|array $key The key using dot notation
     * @param mixed $value The value to set
     * @param bool $overwrite Whether to overwrite existing values
     * @return mixed
     *
     * @example
     * ```php
     * $data = ['user' => ['name' => 'John']];
     *
     * data_set($data, 'user.email', 'john@example.com');
     * // ['user' => ['name' => 'John', 'email' => 'john@example.com']]
     *
     * data_set($data, 'user.name', 'Jane', false);
     * // Name not changed (overwrite = false)
     * ```
     */
    function data_set(mixed &$target, string|array $key, mixed $value, bool $overwrite = true): mixed
    {
        $segments = is_array($key) ? $key : explode('.', $key);
        $segment = array_shift($segments);

        if ($segment === '*') {
            if (!is_array($target)) {
                $target = [];
            }

            if ($segments) {
                foreach ($target as &$inner) {
                    data_set($inner, $segments, $value, $overwrite);
                }
            } elseif ($overwrite) {
                foreach ($target as &$inner) {
                    $inner = $value;
                }
            }
        } elseif (is_array($target)) {
            if ($segments) {
                if (!array_key_exists($segment, $target)) {
                    $target[$segment] = [];
                }

                data_set($target[$segment], $segments, $value, $overwrite);
            } elseif ($overwrite || !array_key_exists($segment, $target)) {
                $target[$segment] = $value;
            }
        } elseif (is_object($target)) {
            if ($segments) {
                if (!isset($target->{$segment})) {
                    $target->{$segment} = [];
                }

                data_set($target->{$segment}, $segments, $value, $overwrite);
            } elseif ($overwrite || !isset($target->{$segment})) {
                $target->{$segment} = $value;
            }
        }

        return $target;
    }
}

if (!function_exists('data_forget')) {
    /**
     * Remove an item from an array or object using "dot" notation.
     *
     * @param mixed $target The array or object to modify
     * @param string|array $keys The key(s) to remove
     * @return void
     *
     * @example
     * ```php
     * $data = ['user' => ['name' => 'John', 'email' => 'john@example.com']];
     *
     * data_forget($data, 'user.email');
     * // ['user' => ['name' => 'John']]
     * ```
     */
    function data_forget(mixed &$target, string|array $keys): void
    {
        $keys = (array) $keys;

        if (count($keys) === 0) {
            return;
        }

        foreach ($keys as $key) {
            if (is_array($target) && array_key_exists($key, $target)) {
                unset($target[$key]);
                continue;
            }

            $segments = explode('.', $key);
            $lastSegment = array_pop($segments);

            $current = &$target;

            foreach ($segments as $segment) {
                if (is_array($current) && array_key_exists($segment, $current)) {
                    $current = &$current[$segment];
                } elseif (is_object($current) && isset($current->{$segment})) {
                    $current = &$current->{$segment};
                } else {
                    continue 2;
                }
            }

            if (is_array($current)) {
                unset($current[$lastSegment]);
            } elseif (is_object($current)) {
                unset($current->{$lastSegment});
            }
        }
    }
}

if (!function_exists('data_fill')) {
    /**
     * Fill in data where it's missing.
     *
     * @param mixed $target The array or object to fill
     * @param string|array $key The key using dot notation
     * @param mixed $value The value to set if missing
     * @return mixed
     *
     * @example
     * ```php
     * $data = ['user' => ['name' => 'John']];
     *
     * data_fill($data, 'user.email', 'default@example.com');
     * // ['user' => ['name' => 'John', 'email' => 'default@example.com']]
     *
     * data_fill($data, 'user.name', 'Jane');
     * // Name not changed (already exists)
     * ```
     */
    function data_fill(mixed &$target, string|array $key, mixed $value): mixed
    {
        return data_set($target, $key, $value, false);
    }
}

if (!function_exists('value')) {
    /**
     * Return the default value of the given value.
     *
     * @param mixed $value
     * @param mixed ...$args
     * @return mixed
     */
    function value(mixed $value, mixed ...$args): mixed
    {
        return is_callable($value) ? $value(...$args) : $value;
    }
}

if (!function_exists('head')) {
    /**
     * Get the first element of an array.
     *
     * @param array $array
     * @return mixed
     */
    function head(array $array): mixed
    {
        return reset($array);
    }
}

if (!function_exists('last')) {
    /**
     * Get the last element of an array.
     *
     * @param array $array
     * @return mixed
     */
    function last(array $array): mixed
    {
        return end($array);
    }
}
