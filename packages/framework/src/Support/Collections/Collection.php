<?php

declare(strict_types=1);

namespace Lalaz\Support\Collections;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * Collection class for fluent array manipulation.
 *
 * Provides a wrapper around arrays with chainable methods
 * for filtering, mapping, reducing, and transforming data.
 *
 * @template TKey of array-key
 * @template TValue
 * @implements ArrayAccess<TKey, TValue>
 * @implements IteratorAggregate<TKey, TValue>
 *
 * @package lalaz/framework
 * @author Lalaz Framework <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
class Collection implements
    ArrayAccess,
    Countable,
    IteratorAggregate,
    JsonSerializable
{
    /**
     * The items in the collection.
     *
     * @var array<TKey, TValue>
     */
    protected array $items = [];

    /**
     * Create a new collection instance.
     *
     * @param array<TKey, TValue> $items
     */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * Create a new collection instance.
     *
     * @param mixed $items
     * @return static
     */
    public static function create(mixed $items = []): static
    {
        if ($items instanceof static) {
            return $items;
        }

        if ($items instanceof self) {
            return new static($items->all());
        }

        return new static($items ?? []);
    }

    /**
     * Create a collection from a range of numbers.
     *
     * @param int $from
     * @param int $to
     * @param int $step
     * @return static<int, int>
     */
    public static function range(int $from, int $to, int $step = 1): static
    {
        return new static(range($from, $to, $step));
    }

    /**
     * Create a collection with n copies of a value.
     *
     * @template TFillValue
     * @param int $count
     * @param TFillValue $value
     * @return static<int, TFillValue>
     */
    public static function fill(int $count, mixed $value): static
    {
        return new static(array_fill(0, $count, $value));
    }

    /**
     * Wrap the given value in a collection if applicable.
     *
     * @param mixed $value
     * @return static
     */
    public static function wrap(mixed $value): static
    {
        return $value instanceof self
            ? new static($value->all())
            : new static(is_array($value) ? $value : [$value]);
    }

    /**
     * Get the underlying array of items.
     *
     * @return array<TKey, TValue>
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Get the average value of a given key.
     *
     * @param callable|string|null $callback
     * @return float|int|null
     */
    public function avg(callable|string|null $callback = null): float|int|null
    {
        $items = $this->items;

        if ($callback !== null) {
            $items = $this->pluckValues($callback);
        }

        if (empty($items)) {
            return null;
        }

        return array_sum($items) / count($items);
    }

    /**
     * Alias for avg().
     */
    public function average(callable|string|null $callback = null): float|int|null
    {
        return $this->avg($callback);
    }

    /**
     * Chunk the collection into chunks of the given size.
     *
     * @param int $size
     * @return static<int, static<TKey, TValue>>
     */
    public function chunk(int $size): static
    {
        if ($size <= 0) {
            return new static();
        }

        $chunks = [];
        foreach (array_chunk($this->items, $size, true) as $chunk) {
            $chunks[] = new static($chunk);
        }

        return new static($chunks);
    }

    /**
     * Collapse a collection of arrays into a single, flat collection.
     *
     * @return static<int, mixed>
     */
    public function collapse(): static
    {
        $results = [];

        foreach ($this->items as $values) {
            if ($values instanceof self) {
                $values = $values->all();
            } elseif (!is_array($values)) {
                continue;
            }

            $results = array_merge($results, $values);
        }

        return new static($results);
    }

    /**
     * Combine the collection with arrays of keys and values.
     *
     * @param iterable $values
     * @return static
     */
    public function combine(iterable $values): static
    {
        $values = $values instanceof self ? $values->all() : (array) $values;
        return new static(array_combine($this->items, $values));
    }

    /**
     * Determine if an item exists in the collection.
     *
     * @param mixed $key
     * @param mixed $operator
     * @param mixed $value
     * @return bool
     */
    public function contains(mixed $key, mixed $operator = null, mixed $value = null): bool
    {
        if (func_num_args() === 1) {
            if (is_callable($key)) {
                foreach ($this->items as $k => $item) {
                    if ($key($item, $k)) {
                        return true;
                    }
                }
                return false;
            }

            return in_array($key, $this->items, true);
        }

        return $this->contains($this->operatorForWhere(...func_get_args()));
    }

    /**
     * Determine if an item exists in the collection using strict comparison.
     *
     * @param mixed $key
     * @param mixed $value
     * @return bool
     */
    public function containsStrict(mixed $key, mixed $value = null): bool
    {
        if (func_num_args() === 2) {
            return $this->contains(fn ($item) => data_get($item, $key) === $value);
        }

        return in_array($key, $this->items, true);
    }

    /**
     * Count the number of items in the collection.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Count the occurrences of values in the collection.
     *
     * @param callable|null $callback
     * @return static<array-key, int>
     */
    public function countBy(?callable $callback = null): static
    {
        $counts = [];

        foreach ($this->items as $key => $value) {
            $group = $callback ? $callback($value, $key) : $value;
            $counts[$group] = ($counts[$group] ?? 0) + 1;
        }

        return new static($counts);
    }

    /**
     * Get the items that are not present in the given items.
     *
     * @param iterable $items
     * @return static<TKey, TValue>
     */
    public function diff(iterable $items): static
    {
        $items = $items instanceof self ? $items->all() : (array) $items;
        return new static(array_diff($this->items, $items));
    }

    /**
     * Get the items whose keys are not present in the given items.
     *
     * @param iterable $items
     * @return static<TKey, TValue>
     */
    public function diffKeys(iterable $items): static
    {
        $items = $items instanceof self ? $items->all() : (array) $items;
        return new static(array_diff_key($this->items, $items));
    }

    /**
     * Get the items whose keys and values are not present in the given items.
     *
     * @param iterable $items
     * @return static<TKey, TValue>
     */
    public function diffAssoc(iterable $items): static
    {
        $items = $items instanceof self ? $items->all() : (array) $items;
        return new static(array_diff_assoc($this->items, $items));
    }

    /**
     * Get duplicate items from the collection.
     *
     * @param callable|string|null $callback
     * @param bool $strict
     * @return static
     */
    public function duplicates(callable|string|null $callback = null, bool $strict = false): static
    {
        $items = $this->map($this->valueRetriever($callback));

        $duplicates = [];
        $seen = [];

        foreach ($items as $key => $value) {
            $id = $strict ? $value : (is_object($value) ? spl_object_hash($value) : $value);

            if (isset($seen[$id])) {
                $duplicates[$key] = $value;
            } else {
                $seen[$id] = true;
            }
        }

        return new static($duplicates);
    }

    /**
     * Execute a callback over each item.
     *
     * @param callable $callback
     * @return $this
     */
    public function each(callable $callback): static
    {
        foreach ($this->items as $key => $item) {
            if ($callback($item, $key) === false) {
                break;
            }
        }

        return $this;
    }

    /**
     * Execute a callback over each nested chunk of items.
     *
     * @param callable $callback
     * @return static
     */
    public function eachSpread(callable $callback): static
    {
        return $this->each(function ($chunk, $key) use ($callback) {
            $chunk = $chunk instanceof self ? $chunk->all() : (array) $chunk;
            return $callback(...[...$chunk, $key]);
        });
    }

    /**
     * Determine if all items pass the given truth test.
     *
     * @param callable|string $key
     * @param mixed $operator
     * @param mixed $value
     * @return bool
     */
    public function every(callable|string $key, mixed $operator = null, mixed $value = null): bool
    {
        if (func_num_args() === 1) {
            $callback = $this->valueRetriever($key);

            foreach ($this->items as $k => $item) {
                if (!$callback($item, $k)) {
                    return false;
                }
            }

            return true;
        }

        return $this->every($this->operatorForWhere(...func_get_args()));
    }

    /**
     * Get all items except for those with the specified keys.
     *
     * @param mixed $keys
     * @return static<TKey, TValue>
     */
    public function except(mixed $keys): static
    {
        $keys = $keys instanceof self ? $keys->all() : (array) $keys;
        return new static(array_diff_key($this->items, array_flip($keys)));
    }

    /**
     * Filter items by the given callback.
     *
     * @param callable|null $callback
     * @return static<TKey, TValue>
     */
    public function filter(?callable $callback = null): static
    {
        if ($callback !== null) {
            return new static(
                array_filter($this->items, $callback, ARRAY_FILTER_USE_BOTH)
            );
        }

        return new static(array_filter($this->items));
    }

    /**
     * Get the first item from the collection.
     *
     * @param callable|null $callback
     * @param mixed $default
     * @return TValue|mixed
     */
    public function first(?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            if (empty($this->items)) {
                return $default;
            }

            foreach ($this->items as $item) {
                return $item;
            }
        }

        foreach ($this->items as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return $default;
    }

    /**
     * Get the first item or throw an exception.
     *
     * @param callable|null $callback
     * @return TValue
     * @throws \RuntimeException
     */
    public function firstOrFail(?callable $callback = null): mixed
    {
        $result = $this->first($callback);

        if ($result === null) {
            throw new \RuntimeException('Item not found.');
        }

        return $result;
    }

    /**
     * Get a flattened array of the items in the collection.
     *
     * @param int $depth
     * @return static<int, mixed>
     */
    public function flatten(int $depth = PHP_INT_MAX): static
    {
        return new static($this->flattenArray($this->items, $depth));
    }

    /**
     * Flip the items in the collection.
     *
     * @return static<TValue, TKey>
     */
    public function flip(): static
    {
        return new static(array_flip($this->items));
    }

    /**
     * Remove an item from the collection by key.
     *
     * @param mixed $keys
     * @return $this
     */
    public function forget(mixed $keys): static
    {
        foreach ((array) $keys as $key) {
            unset($this->items[$key]);
        }

        return $this;
    }

    /**
     * Get an item from the collection by key.
     *
     * @param TKey $key
     * @param mixed $default
     * @return TValue|mixed
     */
    public function get(mixed $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->items)) {
            return $this->items[$key];
        }

        return is_callable($default) ? $default() : $default;
    }

    /**
     * Group an associative array by a field or using a callback.
     *
     * @param callable|string $groupBy
     * @param bool $preserveKeys
     * @return static<array-key, static<TKey, TValue>>
     */
    public function groupBy(callable|string $groupBy, bool $preserveKeys = false): static
    {
        $results = [];
        $callback = $this->valueRetriever($groupBy);

        foreach ($this->items as $key => $value) {
            $groupKey = $callback($value, $key);

            if (!isset($results[$groupKey])) {
                $results[$groupKey] = new static();
            }

            if ($preserveKeys) {
                $results[$groupKey]->items[$key] = $value;
            } else {
                $results[$groupKey]->items[] = $value;
            }
        }

        return new static($results);
    }

    /**
     * Determine if an item exists in the collection by key.
     *
     * @param mixed $key
     * @return bool
     */
    public function has(mixed $key): bool
    {
        $keys = is_array($key) ? $key : func_get_args();

        foreach ($keys as $k) {
            if (!array_key_exists($k, $this->items)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine if any of the keys exist in the collection.
     *
     * @param mixed $key
     * @return bool
     */
    public function hasAny(mixed $key): bool
    {
        $keys = is_array($key) ? $key : func_get_args();

        foreach ($keys as $k) {
            if ($this->has($k)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Concatenate values of a given key as a string.
     *
     * @param callable|string $value
     * @param string|null $glue
     * @return string
     */
    public function implode(callable|string $value, ?string $glue = null): string
    {
        if ($glue === null) {
            return implode($value, $this->items);
        }

        return implode($glue, $this->pluck($value)->all());
    }

    /**
     * Intersect the collection with the given items.
     *
     * @param iterable $items
     * @return static<TKey, TValue>
     */
    public function intersect(iterable $items): static
    {
        $items = $items instanceof self ? $items->all() : (array) $items;
        return new static(array_intersect($this->items, $items));
    }

    /**
     * Intersect the collection with the given items by key.
     *
     * @param iterable $items
     * @return static<TKey, TValue>
     */
    public function intersectByKeys(iterable $items): static
    {
        $items = $items instanceof self ? $items->all() : (array) $items;
        return new static(array_intersect_key($this->items, $items));
    }

    /**
     * Determine if the collection is empty.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * Determine if the collection is not empty.
     *
     * @return bool
     */
    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * Join items with a string.
     *
     * @param string $glue
     * @param string $finalGlue
     * @return string
     */
    public function join(string $glue, string $finalGlue = ''): string
    {
        if ($finalGlue === '') {
            return implode($glue, $this->items);
        }

        $count = $this->count();

        if ($count === 0) {
            return '';
        }

        if ($count === 1) {
            return (string) $this->first();
        }

        $collection = new static($this->items);
        $last = $collection->pop();

        return $collection->implode($glue) . $finalGlue . $last;
    }

    /**
     * Key an associative array by a field or using a callback.
     *
     * @param callable|string $keyBy
     * @return static<array-key, TValue>
     */
    public function keyBy(callable|string $keyBy): static
    {
        $results = [];
        $callback = $this->valueRetriever($keyBy);

        foreach ($this->items as $key => $item) {
            $results[$callback($item, $key)] = $item;
        }

        return new static($results);
    }

    /**
     * Get the keys of the collection items.
     *
     * @return static<int, TKey>
     */
    public function keys(): static
    {
        return new static(array_keys($this->items));
    }

    /**
     * Get the last item from the collection.
     *
     * @param callable|null $callback
     * @param mixed $default
     * @return TValue|mixed
     */
    public function last(?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            return empty($this->items) ? $default : end($this->items);
        }

        return $this->reverse()->first($callback, $default);
    }

    /**
     * Run a map over each of the items.
     *
     * @template TMapValue
     * @param callable(TValue, TKey): TMapValue $callback
     * @return static<TKey, TMapValue>
     */
    public function map(callable $callback): static
    {
        $keys = array_keys($this->items);
        $items = array_map($callback, $this->items, $keys);

        return new static(array_combine($keys, $items));
    }

    /**
     * Run a map over each nested chunk of items.
     *
     * @param callable $callback
     * @return static
     */
    public function mapSpread(callable $callback): static
    {
        return $this->map(function ($chunk, $key) use ($callback) {
            $chunk = $chunk instanceof self ? $chunk->all() : (array) $chunk;
            return $callback(...[...$chunk, $key]);
        });
    }

    /**
     * Run a map and flatten the result.
     *
     * @param callable $callback
     * @return static
     */
    public function flatMap(callable $callback): static
    {
        return $this->map($callback)->collapse();
    }

    /**
     * Map into a new class.
     *
     * @template TMapIntoValue
     * @param class-string<TMapIntoValue> $class
     * @return static<TKey, TMapIntoValue>
     */
    public function mapInto(string $class): static
    {
        return $this->map(fn ($value, $key) => new $class($value, $key));
    }

    /**
     * Map with keys.
     *
     * @param callable $callback
     * @return static
     */
    public function mapWithKeys(callable $callback): static
    {
        $result = [];

        foreach ($this->items as $key => $value) {
            $assoc = $callback($value, $key);

            foreach ($assoc as $mapKey => $mapValue) {
                $result[$mapKey] = $mapValue;
            }
        }

        return new static($result);
    }

    /**
     * Get the max value of a given key.
     *
     * @param callable|string|null $callback
     * @return mixed
     */
    public function max(callable|string|null $callback = null): mixed
    {
        if ($callback === null) {
            return empty($this->items) ? null : max($this->items);
        }

        return $this->map($this->valueRetriever($callback))->max();
    }

    /**
     * Get the median of a given key.
     *
     * @param callable|string|null $key
     * @return float|int|null
     */
    public function median(callable|string|null $key = null): float|int|null
    {
        $values = $key !== null
            ? $this->pluck($key)->filter()->values()
            : $this->filter()->values();

        $count = $values->count();

        if ($count === 0) {
            return null;
        }

        $sorted = $values->sort()->values();
        $middle = (int) ($count / 2);

        if ($count % 2) {
            return $sorted->get($middle);
        }

        return ($sorted->get($middle - 1) + $sorted->get($middle)) / 2;
    }

    /**
     * Merge the collection with the given items.
     *
     * @param iterable $items
     * @return static<TKey, TValue>
     */
    public function merge(iterable $items): static
    {
        $items = $items instanceof self ? $items->all() : (array) $items;
        return new static(array_merge($this->items, $items));
    }

    /**
     * Recursively merge the collection with the given items.
     *
     * @param iterable $items
     * @return static<TKey, TValue>
     */
    public function mergeRecursive(iterable $items): static
    {
        $items = $items instanceof self ? $items->all() : (array) $items;
        return new static(array_merge_recursive($this->items, $items));
    }

    /**
     * Get the min value of a given key.
     *
     * @param callable|string|null $callback
     * @return mixed
     */
    public function min(callable|string|null $callback = null): mixed
    {
        if ($callback === null) {
            return empty($this->items) ? null : min($this->items);
        }

        return $this->map($this->valueRetriever($callback))->min();
    }

    /**
     * Get the mode of a given key.
     *
     * @param callable|string|null $key
     * @return array|null
     */
    public function mode(callable|string|null $key = null): ?array
    {
        $values = $key !== null
            ? $this->pluck($key)
            : $this;

        $counts = $values->countBy();

        if ($counts->isEmpty()) {
            return null;
        }

        $maxCount = $counts->max();

        return $counts->filter(fn ($count) => $count === $maxCount)->keys()->all();
    }

    /**
     * Get the items with the specified keys.
     *
     * @param mixed $keys
     * @return static<TKey, TValue>
     */
    public function only(mixed $keys): static
    {
        $keys = $keys instanceof self ? $keys->all() : (array) $keys;
        return new static(array_intersect_key($this->items, array_flip($keys)));
    }

    /**
     * Pad collection to the specified length with a value.
     *
     * @param int $size
     * @param mixed $value
     * @return static
     */
    public function pad(int $size, mixed $value): static
    {
        return new static(array_pad($this->items, $size, $value));
    }

    /**
     * Partition the collection into two arrays using the callback.
     *
     * @param callable $callback
     * @return static<int, static<TKey, TValue>>
     */
    public function partition(callable $callback): static
    {
        $passed = [];
        $failed = [];

        foreach ($this->items as $key => $item) {
            if ($callback($item, $key)) {
                $passed[$key] = $item;
            } else {
                $failed[$key] = $item;
            }
        }

        return new static([new static($passed), new static($failed)]);
    }

    /**
     * Pass the collection to the given callback and return the result.
     *
     * @template TPipeReturnType
     * @param callable(static): TPipeReturnType $callback
     * @return TPipeReturnType
     */
    public function pipe(callable $callback): mixed
    {
        return $callback($this);
    }

    /**
     * Pass the collection through a series of callbacks.
     *
     * @param array<callable> $callbacks
     * @return static
     */
    public function pipeThrough(array $callbacks): static
    {
        return static::create(array_reduce(
            $callbacks,
            fn ($carry, $callback) => $callback($carry),
            $this
        ));
    }

    /**
     * Get the values of a given key.
     *
     * @param callable|string $value
     * @param string|null $key
     * @return static<array-key, mixed>
     */
    public function pluck(callable|string $value, ?string $key = null): static
    {
        $results = [];
        $valueCallback = $this->valueRetriever($value);

        foreach ($this->items as $item) {
            $itemValue = $valueCallback($item);

            if ($key === null) {
                $results[] = $itemValue;
            } else {
                $itemKey = data_get($item, $key);
                $results[$itemKey] = $itemValue;
            }
        }

        return new static($results);
    }

    /**
     * Get and remove the last item from the collection.
     *
     * @return TValue|null
     */
    public function pop(): mixed
    {
        return array_pop($this->items);
    }

    /**
     * Push an item onto the beginning of the collection.
     *
     * @param mixed $value
     * @param mixed $key
     * @return $this
     */
    public function prepend(mixed $value, mixed $key = null): static
    {
        if (func_num_args() === 1) {
            array_unshift($this->items, $value);
        } else {
            $this->items = [$key => $value] + $this->items;
        }

        return $this;
    }

    /**
     * Push an item onto the end of the collection.
     *
     * @param TValue ...$values
     * @return $this
     */
    public function push(mixed ...$values): static
    {
        foreach ($values as $value) {
            $this->items[] = $value;
        }

        return $this;
    }

    /**
     * Put an item in the collection by key.
     *
     * @param TKey $key
     * @param TValue $value
     * @return $this
     */
    public function put(mixed $key, mixed $value): static
    {
        $this->items[$key] = $value;
        return $this;
    }

    /**
     * Get one or a specified number of items randomly from the collection.
     *
     * @param int|null $number
     * @return static|TValue
     */
    public function random(?int $number = null): mixed
    {
        if ($number === null) {
            return $this->items[array_rand($this->items)];
        }

        if ($number <= 0 || $number > $this->count()) {
            throw new \InvalidArgumentException(
                "You requested {$number} items, but there are only {$this->count()} items available."
            );
        }

        $keys = array_rand($this->items, $number);
        $results = [];

        foreach ((array) $keys as $key) {
            $results[$key] = $this->items[$key];
        }

        return new static($results);
    }

    /**
     * Reduce the collection to a single value.
     *
     * @template TReduceInitial
     * @template TReduceReturnType
     * @param callable(TReduceInitial|TReduceReturnType, TValue, TKey): TReduceReturnType $callback
     * @param TReduceInitial $initial
     * @return TReduceReturnType
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        $result = $initial;

        foreach ($this->items as $key => $value) {
            $result = $callback($result, $value, $key);
        }

        return $result;
    }

    /**
     * Reduce the collection to multiple aggregate values.
     *
     * @param callable $callback
     * @param mixed ...$initial
     * @return array
     */
    public function reduceSpread(callable $callback, mixed ...$initial): array
    {
        $result = $initial;

        foreach ($this->items as $key => $value) {
            $args = array_merge($result, [$value, $key]);
            $result = array_values($callback(...$args));
        }

        return $result;
    }

    /**
     * Create a collection of all elements that do not pass a given truth test.
     *
     * @param callable|mixed $callback
     * @return static<TKey, TValue>
     */
    public function reject(callable|bool $callback): static
    {
        $useAsCallable = is_callable($callback);

        return $this->filter(function ($value, $key) use ($callback, $useAsCallable) {
            return $useAsCallable
                ? !$callback($value, $key)
                : $value != $callback;
        });
    }

    /**
     * Replace the collection items with the given items.
     *
     * @param iterable $items
     * @return static<TKey, TValue>
     */
    public function replace(iterable $items): static
    {
        $items = $items instanceof self ? $items->all() : (array) $items;
        return new static(array_replace($this->items, $items));
    }

    /**
     * Recursively replace the collection items with the given items.
     *
     * @param iterable $items
     * @return static<TKey, TValue>
     */
    public function replaceRecursive(iterable $items): static
    {
        $items = $items instanceof self ? $items->all() : (array) $items;
        return new static(array_replace_recursive($this->items, $items));
    }

    /**
     * Reverse items order.
     *
     * @return static<TKey, TValue>
     */
    public function reverse(): static
    {
        return new static(array_reverse($this->items, true));
    }

    /**
     * Search the collection for a given value and return the corresponding key.
     *
     * @param mixed $value
     * @param bool $strict
     * @return TKey|false
     */
    public function search(mixed $value, bool $strict = false): mixed
    {
        if (is_callable($value)) {
            foreach ($this->items as $key => $item) {
                if ($value($item, $key)) {
                    return $key;
                }
            }
            return false;
        }

        return array_search($value, $this->items, $strict);
    }

    /**
     * Get and remove the first item from the collection.
     *
     * @return TValue|null
     */
    public function shift(): mixed
    {
        return array_shift($this->items);
    }

    /**
     * Shuffle the items in the collection.
     *
     * @return static<TKey, TValue>
     */
    public function shuffle(): static
    {
        $items = $this->items;
        shuffle($items);
        return new static($items);
    }

    /**
     * Skip the first n items.
     *
     * @param int $count
     * @return static<TKey, TValue>
     */
    public function skip(int $count): static
    {
        return new static(array_slice($this->items, $count, null, true));
    }

    /**
     * Skip items until the callback returns true.
     *
     * @param callable $callback
     * @return static<TKey, TValue>
     */
    public function skipUntil(callable $callback): static
    {
        $found = false;

        return $this->filter(function ($item, $key) use ($callback, &$found) {
            if ($found) {
                return true;
            }

            if ($callback($item, $key)) {
                $found = true;
                return true;
            }

            return false;
        });
    }

    /**
     * Skip items while the callback returns true.
     *
     * @param callable $callback
     * @return static<TKey, TValue>
     */
    public function skipWhile(callable $callback): static
    {
        $found = false;

        return $this->filter(function ($item, $key) use ($callback, &$found) {
            if ($found) {
                return true;
            }

            if (!$callback($item, $key)) {
                $found = true;
                return true;
            }

            return false;
        });
    }

    /**
     * Slice the underlying collection array.
     *
     * @param int $offset
     * @param int|null $length
     * @return static<TKey, TValue>
     */
    public function slice(int $offset, ?int $length = null): static
    {
        return new static(array_slice($this->items, $offset, $length, true));
    }

    /**
     * Split a collection into a certain number of groups.
     *
     * @param int $numberOfGroups
     * @return static<int, static<TKey, TValue>>
     */
    public function split(int $numberOfGroups): static
    {
        if ($this->isEmpty()) {
            return new static();
        }

        $groupSize = ceil($this->count() / $numberOfGroups);

        return $this->chunk((int) $groupSize);
    }

    /**
     * Chunk the collection into groups if the given callback returns true.
     *
     * @param callable $callback
     * @return static<int, static<TKey, TValue>>
     */
    public function splitIn(int $numberOfGroups): static
    {
        return $this->chunk((int) ceil($this->count() / $numberOfGroups));
    }

    /**
     * Get the first item in the collection, but only if exactly one item exists.
     *
     * @param callable|null $callback
     * @return TValue|null
     * @throws \RuntimeException
     */
    public function sole(?callable $callback = null): mixed
    {
        $items = $callback ? $this->filter($callback) : $this;

        if ($items->isEmpty()) {
            throw new \RuntimeException('No items found.');
        }

        if ($items->count() > 1) {
            throw new \RuntimeException('Multiple items found.');
        }

        return $items->first();
    }

    /**
     * Sort through each item with a callback.
     *
     * @param callable|int|null $callback
     * @return static<TKey, TValue>
     */
    public function sort(callable|int|null $callback = null): static
    {
        $items = $this->items;

        if (is_callable($callback)) {
            uasort($items, $callback);
        } elseif ($callback === SORT_DESC) {
            arsort($items);
        } else {
            asort($items);
        }

        return new static($items);
    }

    /**
     * Sort the collection using the given callback.
     *
     * @param callable|string $callback
     * @param int $options
     * @param bool $descending
     * @return static<TKey, TValue>
     */
    public function sortBy(
        callable|string $callback,
        int $options = SORT_REGULAR,
        bool $descending = false
    ): static {
        $results = [];
        $callback = $this->valueRetriever($callback);

        foreach ($this->items as $key => $value) {
            $results[$key] = $callback($value, $key);
        }

        $descending ? arsort($results, $options) : asort($results, $options);

        foreach (array_keys($results) as $key) {
            $results[$key] = $this->items[$key];
        }

        return new static($results);
    }

    /**
     * Sort the collection in descending order using the given callback.
     *
     * @param callable|string $callback
     * @param int $options
     * @return static<TKey, TValue>
     */
    public function sortByDesc(callable|string $callback, int $options = SORT_REGULAR): static
    {
        return $this->sortBy($callback, $options, true);
    }

    /**
     * Sort the collection in descending order.
     *
     * @param int $options
     * @return static<TKey, TValue>
     */
    public function sortDesc(int $options = SORT_REGULAR): static
    {
        $items = $this->items;
        arsort($items, $options);
        return new static($items);
    }

    /**
     * Sort the collection keys.
     *
     * @param int $options
     * @param bool $descending
     * @return static<TKey, TValue>
     */
    public function sortKeys(int $options = SORT_REGULAR, bool $descending = false): static
    {
        $items = $this->items;
        $descending ? krsort($items, $options) : ksort($items, $options);
        return new static($items);
    }

    /**
     * Sort the collection keys in descending order.
     *
     * @param int $options
     * @return static<TKey, TValue>
     */
    public function sortKeysDesc(int $options = SORT_REGULAR): static
    {
        return $this->sortKeys($options, true);
    }

    /**
     * Splice a portion of the underlying collection array.
     *
     * @param int $offset
     * @param int|null $length
     * @param mixed $replacement
     * @return static<TKey, TValue>
     */
    public function splice(int $offset, ?int $length = null, mixed $replacement = []): static
    {
        if (func_num_args() === 1) {
            return new static(array_splice($this->items, $offset));
        }

        return new static(array_splice($this->items, $offset, $length, $replacement));
    }

    /**
     * Get the sum of the given values.
     *
     * @param callable|string|null $callback
     * @return float|int
     */
    public function sum(callable|string|null $callback = null): float|int
    {
        if ($callback === null) {
            return array_sum($this->items);
        }

        return $this->map($this->valueRetriever($callback))->sum();
    }

    /**
     * Take the first or last n items.
     *
     * @param int $limit
     * @return static<TKey, TValue>
     */
    public function take(int $limit): static
    {
        if ($limit < 0) {
            return $this->slice($limit, abs($limit));
        }

        return $this->slice(0, $limit);
    }

    /**
     * Take items until the callback returns true.
     *
     * @param callable $callback
     * @return static<TKey, TValue>
     */
    public function takeUntil(callable $callback): static
    {
        $results = [];

        foreach ($this->items as $key => $value) {
            if ($callback($value, $key)) {
                break;
            }

            $results[$key] = $value;
        }

        return new static($results);
    }

    /**
     * Take items while the callback returns true.
     *
     * @param callable $callback
     * @return static<TKey, TValue>
     */
    public function takeWhile(callable $callback): static
    {
        $results = [];

        foreach ($this->items as $key => $value) {
            if (!$callback($value, $key)) {
                break;
            }

            $results[$key] = $value;
        }

        return new static($results);
    }

    /**
     * Pass the collection to the given callback and then return it.
     *
     * @param callable $callback
     * @return $this
     */
    public function tap(callable $callback): static
    {
        $callback(new static($this->items));
        return $this;
    }

    /**
     * Convert the collection to its array representation.
     *
     * @return array<TKey, TValue>
     */
    public function toArray(): array
    {
        return $this->map(function ($value) {
            return $value instanceof self ? $value->toArray() : $value;
        })->all();
    }

    /**
     * Convert the collection to JSON.
     *
     * @param int $options
     * @return string
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Transform each item in the collection using a callback.
     *
     * @param callable $callback
     * @return $this
     */
    public function transform(callable $callback): static
    {
        $this->items = $this->map($callback)->all();
        return $this;
    }

    /**
     * Union the collection with the given items.
     *
     * @param iterable $items
     * @return static<TKey, TValue>
     */
    public function union(iterable $items): static
    {
        $items = $items instanceof self ? $items->all() : (array) $items;
        return new static($this->items + $items);
    }

    /**
     * Return only unique items from the collection array.
     *
     * @param callable|string|null $key
     * @param bool $strict
     * @return static<TKey, TValue>
     */
    public function unique(callable|string|null $key = null, bool $strict = false): static
    {
        if ($key === null) {
            return new static(array_unique($this->items, SORT_REGULAR));
        }

        $callback = $this->valueRetriever($key);
        $exists = [];

        return $this->reject(function ($item, $key) use ($callback, $strict, &$exists) {
            $id = $callback($item, $key);

            if (in_array($id, $exists, $strict)) {
                return true;
            }

            $exists[] = $id;
            return false;
        });
    }

    /**
     * Return only unique items from the collection using strict comparison.
     *
     * @param callable|string|null $key
     * @return static<TKey, TValue>
     */
    public function uniqueStrict(callable|string|null $key = null): static
    {
        return $this->unique($key, true);
    }

    /**
     * Reset the keys on the underlying array.
     *
     * @return static<int, TValue>
     */
    public function values(): static
    {
        return new static(array_values($this->items));
    }

    /**
     * Apply the callback if the value is truthy.
     *
     * @param bool|mixed $value
     * @param callable $callback
     * @param callable|null $default
     * @return static|mixed
     */
    public function when(mixed $value, callable $callback, ?callable $default = null): mixed
    {
        if ($value) {
            return $callback($this, $value);
        }

        if ($default) {
            return $default($this, $value);
        }

        return $this;
    }

    /**
     * Apply the callback if the collection is empty.
     *
     * @param callable $callback
     * @param callable|null $default
     * @return static|mixed
     */
    public function whenEmpty(callable $callback, ?callable $default = null): mixed
    {
        return $this->when($this->isEmpty(), $callback, $default);
    }

    /**
     * Apply the callback if the collection is not empty.
     *
     * @param callable $callback
     * @param callable|null $default
     * @return static|mixed
     */
    public function whenNotEmpty(callable $callback, ?callable $default = null): mixed
    {
        return $this->when($this->isNotEmpty(), $callback, $default);
    }

    /**
     * Filter items by the given key value pair.
     *
     * @param string $key
     * @param mixed $operator
     * @param mixed $value
     * @return static<TKey, TValue>
     */
    public function where(string $key, mixed $operator = null, mixed $value = null): static
    {
        return $this->filter($this->operatorForWhere(...func_get_args()));
    }

    /**
     * Filter items where the value is between two values.
     *
     * @param string $key
     * @param array $values
     * @return static<TKey, TValue>
     */
    public function whereBetween(string $key, array $values): static
    {
        return $this->filter(function ($item) use ($key, $values) {
            $value = data_get($item, $key);
            return $value >= $values[0] && $value <= $values[1];
        });
    }

    /**
     * Filter items by the given key value pair.
     *
     * @param string $key
     * @param iterable $values
     * @param bool $strict
     * @return static<TKey, TValue>
     */
    public function whereIn(string $key, iterable $values, bool $strict = false): static
    {
        $values = $values instanceof self ? $values->all() : (array) $values;

        return $this->filter(function ($item) use ($key, $values, $strict) {
            return in_array(data_get($item, $key), $values, $strict);
        });
    }

    /**
     * Filter items by the given key value pair using strict comparison.
     *
     * @param string $key
     * @param iterable $values
     * @return static<TKey, TValue>
     */
    public function whereInStrict(string $key, iterable $values): static
    {
        return $this->whereIn($key, $values, true);
    }

    /**
     * Filter items where the value is an instance of the given class.
     *
     * @param string $type
     * @return static<TKey, TValue>
     */
    public function whereInstanceOf(string $type): static
    {
        return $this->filter(fn ($item) => $item instanceof $type);
    }

    /**
     * Filter items where the value is not between two values.
     *
     * @param string $key
     * @param array $values
     * @return static<TKey, TValue>
     */
    public function whereNotBetween(string $key, array $values): static
    {
        return $this->filter(function ($item) use ($key, $values) {
            $value = data_get($item, $key);
            return $value < $values[0] || $value > $values[1];
        });
    }

    /**
     * Filter items by the given key value pair.
     *
     * @param string $key
     * @param iterable $values
     * @param bool $strict
     * @return static<TKey, TValue>
     */
    public function whereNotIn(string $key, iterable $values, bool $strict = false): static
    {
        $values = $values instanceof self ? $values->all() : (array) $values;

        return $this->reject(function ($item) use ($key, $values, $strict) {
            return in_array(data_get($item, $key), $values, $strict);
        });
    }

    /**
     * Filter items by the given key value pair using strict comparison.
     *
     * @param string $key
     * @param iterable $values
     * @return static<TKey, TValue>
     */
    public function whereNotInStrict(string $key, iterable $values): static
    {
        return $this->whereNotIn($key, $values, true);
    }

    /**
     * Filter items where the value is not null.
     *
     * @param string|null $key
     * @return static<TKey, TValue>
     */
    public function whereNotNull(?string $key = null): static
    {
        return $this->filter(function ($item) use ($key) {
            return $key !== null
                ? data_get($item, $key) !== null
                : $item !== null;
        });
    }

    /**
     * Filter items where the value is null.
     *
     * @param string|null $key
     * @return static<TKey, TValue>
     */
    public function whereNull(?string $key = null): static
    {
        return $this->filter(function ($item) use ($key) {
            return $key !== null
                ? data_get($item, $key) === null
                : $item === null;
        });
    }

    /**
     * Filter items by the given key value pair using strict comparison.
     *
     * @param string $key
     * @param mixed $value
     * @return static<TKey, TValue>
     */
    public function whereStrict(string $key, mixed $value): static
    {
        return $this->where($key, '===', $value);
    }

    /**
     * Zip the collection together with one or more arrays.
     *
     * @param mixed ...$items
     * @return static<int, static>
     */
    public function zip(mixed ...$items): static
    {
        $arrayableItems = array_map(function ($items) {
            return $items instanceof self ? $items->all() : (array) $items;
        }, $items);

        $params = array_merge(
            [
                function (...$items) {
                    return new static($items);
                },
                $this->items,
            ],
            $arrayableItems
        );

        return new static(array_map(...$params));
    }

    // =========================================================================
    // Protected Helper Methods
    // =========================================================================

    /**
     * Get an operator checker callback.
     *
     * @param string $key
     * @param mixed $operator
     * @param mixed $value
     * @return \Closure
     */
    protected function operatorForWhere(string $key, mixed $operator = null, mixed $value = null): \Closure
    {
        if (func_num_args() === 1) {
            $value = true;
            $operator = '=';
        }

        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        return function ($item) use ($key, $operator, $value) {
            $retrieved = data_get($item, $key);

            return match ($operator) {
                '=', '==' => $retrieved == $value,
                '!=' , '<>' => $retrieved != $value,
                '===' => $retrieved === $value,
                '!==' => $retrieved !== $value,
                '>' => $retrieved > $value,
                '<' => $retrieved < $value,
                '>=' => $retrieved >= $value,
                '<=' => $retrieved <= $value,
                'like' => str_contains(strtolower((string) $retrieved), strtolower((string) $value)),
                'not like' => !str_contains(strtolower((string) $retrieved), strtolower((string) $value)),
                default => $retrieved == $value,
            };
        };
    }

    /**
     * Flatten a multi-dimensional array into a single level.
     *
     * @param array $array
     * @param int $depth
     * @return array
     */
    protected function flattenArray(array $array, int $depth): array
    {
        $result = [];

        foreach ($array as $item) {
            if (!is_array($item) && !$item instanceof self) {
                $result[] = $item;
            } else {
                $values = $item instanceof self ? $item->all() : $item;

                $values = $depth === 1
                    ? array_values($values)
                    : $this->flattenArray($values, $depth - 1);

                foreach ($values as $value) {
                    $result[] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * Get values for a given key from items.
     *
     * @param callable|string $callback
     * @return array
     */
    protected function pluckValues(callable|string $callback): array
    {
        return $this->map($this->valueRetriever($callback))->all();
    }

    /**
     * Get a value retriever callback.
     *
     * @param callable|string|null $value
     * @return callable
     */
    protected function valueRetriever(callable|string|null $value): callable
    {
        if (is_callable($value)) {
            return $value;
        }

        return fn ($item) => data_get($item, $value);
    }

    // =========================================================================
    // Interface Implementations
    // =========================================================================

    /**
     * Get an iterator for the items.
     *
     * @return ArrayIterator<TKey, TValue>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /**
     * Determine if an item exists at an offset.
     *
     * @param TKey $offset
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    /**
     * Get an item at a given offset.
     *
     * @param TKey $offset
     * @return TValue
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset];
    }

    /**
     * Set the item at a given offset.
     *
     * @param TKey|null $offset
     * @param TValue $value
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    /**
     * Unset the item at a given offset.
     *
     * @param TKey $offset
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array<TKey, TValue>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Get the collection of items as a plain array.
     *
     * @return array<TKey, TValue>
     */
    public function __serialize(): array
    {
        return $this->items;
    }

    /**
     * Restore the collection after serialization.
     *
     * @param array<TKey, TValue> $data
     * @return void
     */
    public function __unserialize(array $data): void
    {
        $this->items = $data;
    }

    /**
     * Convert the collection to its string representation.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->toJson();
    }
}
