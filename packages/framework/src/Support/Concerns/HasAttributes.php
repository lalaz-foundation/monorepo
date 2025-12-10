<?php

declare(strict_types=1);

namespace Lalaz\Support\Concerns;

/**
 * Lightweight attribute bag to attach arbitrary data to value objects.
 *
 * This trait provides a simple key-value store for attaching metadata
 * or additional data to objects without modifying their class definitions.
 * It also implements magic methods for convenient property-style access.
 *
 * Example usage:
 * ```php
 * class MyValueObject {
 *     use HasAttributes;
 * }
 *
 * $obj = new MyValueObject();
 * $obj->setAttribute('foo', 'bar');
 * echo $obj->getAttribute('foo'); // 'bar'
 * echo $obj->foo; // 'bar' (magic getter)
 * $obj->baz = 'qux'; // Magic setter
 * ```
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
trait HasAttributes
{
    /**
     * The attribute storage array.
     *
     * @var array<string, mixed>
     */
    private array $attributes = [];

    /**
     * Sets an attribute value.
     *
     * @param string $name  The attribute name
     * @param mixed  $value The attribute value
     *
     * @return void
     */
    public function setAttribute(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    /**
     * Gets an attribute value.
     *
     * @param string $name    The attribute name
     * @param mixed  $default Default value if attribute doesn't exist
     *
     * @return mixed The attribute value or default
     */
    public function getAttribute(string $name, mixed $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    /**
     * Checks if an attribute exists.
     *
     * @param string $name The attribute name
     *
     * @return bool True if the attribute exists
     */
    public function hasAttribute(string $name): bool
    {
        return array_key_exists($name, $this->attributes);
    }

    /**
     * Magic getter for property-style access.
     *
     * @param string $name The property name
     *
     * @return mixed The attribute value or null
     */
    public function __get(string $name): mixed
    {
        return $this->getAttribute($name);
    }

    /**
     * Magic setter for property-style access.
     *
     * @param string $name  The property name
     * @param mixed  $value The value to set
     *
     * @return void
     */
    public function __set(string $name, mixed $value): void
    {
        $this->setAttribute($name, $value);
    }
}
