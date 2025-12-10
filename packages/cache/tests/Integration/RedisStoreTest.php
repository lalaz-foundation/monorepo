<?php declare(strict_types=1);

namespace Lalaz\Cache\Tests\Integration;

use Lalaz\Cache\Tests\Common\CacheIntegrationTestCase;
use Lalaz\Cache\Stores\RedisStore;
use DateInterval;

class RedisStoreTest extends CacheIntegrationTestCase
{
    private ?RedisStore $store = null;

    protected function setUpCache(): void
    {
        $this->skipIfRedisNotAvailable();

        $config = [
            'host' => '127.0.0.1',
            'port' => 6379,
            'database' => 15,
        ];

        $this->store = new RedisStore($config, 'test_');
        $this->store->clear();
    }

    protected function tearDownCache(): void
    {
        if ($this->store !== null) {
            $this->store->clear();
        }
    }

    // =========================================================================
    // Basic Operations
    // =========================================================================

    public function test_get_returns_default_when_key_not_found(): void
    {
        $this->assertNull($this->store->get('nonexistent'));
        $this->assertSame('default', $this->store->get('nonexistent', 'default'));
    }

    public function test_set_and_get_stores_and_retrieves_value(): void
    {
        $this->assertTrue($this->store->set('key', 'value'));
        $this->assertSame('value', $this->store->get('key'));
    }

    public function test_set_with_ttl_expires_value(): void
    {
        $this->store->set('foo', 'bar', 1);
        $this->assertSame('bar', $this->store->get('foo'));

        sleep(2);
        $this->assertSame('default', $this->store->get('foo', 'default'));
    }

    public function test_set_with_date_interval_ttl(): void
    {
        $this->store->set('interval_key', 'interval_value', new DateInterval('PT1S'));
        $this->assertSame('interval_value', $this->store->get('interval_key'));

        sleep(2);
        $this->assertNull($this->store->get('interval_key'));
    }

    // =========================================================================
    // Has
    // =========================================================================

    public function test_has_returns_true_when_key_exists(): void
    {
        $this->store->set('exists', 'value');
        $this->assertTrue($this->store->has('exists'));
    }

    public function test_has_returns_false_when_key_not_exists(): void
    {
        $this->assertFalse($this->store->has('nonexistent'));
    }

    public function test_has_returns_false_when_key_expired(): void
    {
        $this->store->set('expiring', 'value', 1);
        $this->assertTrue($this->store->has('expiring'));

        sleep(2);
        $this->assertFalse($this->store->has('expiring'));
    }

    // =========================================================================
    // Delete
    // =========================================================================

    public function test_delete_removes_existing_key(): void
    {
        $this->store->set('to_delete', 'value');
        $this->assertTrue($this->store->has('to_delete'));

        $this->assertTrue($this->store->delete('to_delete'));
        $this->assertFalse($this->store->has('to_delete'));
    }

    public function test_delete_returns_false_for_nonexistent_key(): void
    {
        $this->assertFalse($this->store->delete('nonexistent'));
    }

    // =========================================================================
    // Clear
    // =========================================================================

    public function test_clear_removes_all_keys(): void
    {
        $this->store->set('key1', 'value1');
        $this->store->set('key2', 'value2');
        $this->store->set('key3', 'value3');

        $this->assertTrue($this->store->clear());

        $this->assertFalse($this->store->has('key1'));
        $this->assertFalse($this->store->has('key2'));
        $this->assertFalse($this->store->has('key3'));
    }

    // =========================================================================
    // Remember
    // =========================================================================

    public function test_remember_returns_cached_value_on_hit(): void
    {
        $this->store->set('cached', 'existing_value');
        $callCount = 0;

        $value = $this->store->remember('cached', null, function () use (&$callCount) {
            $callCount++;
            return 'new_value';
        });

        $this->assertSame('existing_value', $value);
        $this->assertSame(0, $callCount);
    }

    public function test_remember_calls_callback_on_miss(): void
    {
        $callCount = 0;

        $value = $this->store->remember('new_key', null, function () use (&$callCount) {
            $callCount++;
            return 'computed_value';
        });

        $this->assertSame('computed_value', $value);
        $this->assertSame(1, $callCount);
        $this->assertSame('computed_value', $this->store->get('new_key'));
    }

    // =========================================================================
    // Forever
    // =========================================================================

    public function test_forever_stores_value_without_expiration(): void
    {
        $this->assertTrue($this->store->forever('permanent', 'value'));
        $this->assertSame('value', $this->store->get('permanent'));
        $this->assertTrue($this->store->has('permanent'));
    }

    // =========================================================================
    // Complex Values
    // =========================================================================

    public function test_stores_array_values(): void
    {
        $array = ['foo' => 'bar', 'nested' => ['a' => 1, 'b' => 2]];
        $this->store->set('array_key', $array);
        $this->assertSame($array, $this->store->get('array_key'));
    }

    public function test_stores_object_values(): void
    {
        $object = new \stdClass();
        $object->name = 'test';
        $object->value = 123;

        $this->store->set('object_key', $object);
        $this->assertEquals($object, $this->store->get('object_key'));
    }

    public function test_stores_integer_values(): void
    {
        $this->store->set('int_key', 42);
        $this->assertSame(42, $this->store->get('int_key'));
    }

    public function test_stores_boolean_values(): void
    {
        $this->store->set('true_key', true);
        $this->store->set('false_key', false);

        $this->assertTrue($this->store->get('true_key'));
        $this->assertFalse($this->store->get('false_key'));
    }
}
