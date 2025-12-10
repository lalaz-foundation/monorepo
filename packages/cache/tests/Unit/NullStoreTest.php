<?php declare(strict_types=1);

namespace Lalaz\Cache\Tests\Unit;

use Lalaz\Cache\Tests\Common\CacheUnitTestCase;
use Lalaz\Cache\Stores\NullStore;
use DateInterval;

class NullStoreTest extends CacheUnitTestCase
{
    private NullStore $store;

    protected function setUpCache(): void
    {
        $this->store = $this->createNullStore();
    }

    // =========================================================================
    // Get
    // =========================================================================

    public function test_get_always_returns_default(): void
    {
        $this->assertNull($this->store->get('any_key'));
        $this->assertSame('default', $this->store->get('any_key', 'default'));
    }

    public function test_get_returns_default_even_after_set(): void
    {
        $this->store->set('key', 'value');
        $this->assertNull($this->store->get('key'));
        $this->assertSame('default', $this->store->get('key', 'default'));
    }

    // =========================================================================
    // Set
    // =========================================================================

    public function test_set_always_returns_true(): void
    {
        $this->assertTrue($this->store->set('key', 'value'));
        $this->assertTrue($this->store->set('key', 'value', 3600));
        $this->assertTrue($this->store->set('key', 'value', new DateInterval('PT1H')));
    }

    public function test_set_does_not_store_value(): void
    {
        $this->store->set('key', 'value');
        $this->assertFalse($this->store->has('key'));
    }

    // =========================================================================
    // Has
    // =========================================================================

    public function test_has_always_returns_false(): void
    {
        $this->assertFalse($this->store->has('any_key'));
        $this->assertFalse($this->store->has('nonexistent'));
    }

    public function test_has_returns_false_even_after_set(): void
    {
        $this->store->set('key', 'value');
        $this->assertFalse($this->store->has('key'));
    }

    // =========================================================================
    // Delete
    // =========================================================================

    public function test_delete_always_returns_true(): void
    {
        $this->assertTrue($this->store->delete('any_key'));
        $this->assertTrue($this->store->delete('nonexistent'));
    }

    // =========================================================================
    // Clear
    // =========================================================================

    public function test_clear_always_returns_true(): void
    {
        $this->assertTrue($this->store->clear());
    }

    // =========================================================================
    // Remember
    // =========================================================================

    public function test_remember_always_calls_callback(): void
    {
        $callCount = 0;

        $value1 = $this->store->remember('key', null, function () use (&$callCount) {
            $callCount++;
            return 'computed_value';
        });

        $value2 = $this->store->remember('key', null, function () use (&$callCount) {
            $callCount++;
            return 'computed_again';
        });

        $this->assertSame('computed_value', $value1);
        $this->assertSame('computed_again', $value2);
        $this->assertSame(2, $callCount);
    }

    public function test_remember_with_ttl_still_calls_callback(): void
    {
        $callCount = 0;

        $value = $this->store->remember('key', 3600, function () use (&$callCount) {
            $callCount++;
            return 'value';
        });

        $this->assertSame('value', $value);
        $this->assertSame(1, $callCount);
    }

    // =========================================================================
    // Forever
    // =========================================================================

    public function test_forever_always_returns_true(): void
    {
        $this->assertTrue($this->store->forever('key', 'value'));
    }

    public function test_forever_does_not_store_value(): void
    {
        $this->store->forever('key', 'value');
        $this->assertFalse($this->store->has('key'));
        $this->assertNull($this->store->get('key'));
    }

    // =========================================================================
    // Complex Values
    // =========================================================================

    public function test_handles_array_values(): void
    {
        $array = ['foo' => 'bar', 'nested' => ['a' => 1]];
        $this->assertTrue($this->store->set('array_key', $array));
        $this->assertNull($this->store->get('array_key'));
    }

    public function test_handles_object_values(): void
    {
        $object = new \stdClass();
        $object->name = 'test';

        $this->assertTrue($this->store->set('object_key', $object));
        $this->assertNull($this->store->get('object_key'));
    }

    // =========================================================================
    // Use Case: Disabled Cache
    // =========================================================================

    public function test_acts_as_disabled_cache(): void
    {
        // NullStore is typically used when caching is disabled
        // All operations should succeed without storing anything
        
        $this->store->set('key1', 'value1');
        $this->store->set('key2', 'value2');
        $this->store->forever('key3', 'value3');

        $this->assertFalse($this->store->has('key1'));
        $this->assertFalse($this->store->has('key2'));
        $this->assertFalse($this->store->has('key3'));

        $this->assertTrue($this->store->clear());
    }
}
