<?php declare(strict_types=1);

namespace Lalaz\Cache\Tests\Unit;

use Lalaz\Cache\Tests\Common\CacheUnitTestCase;
use Lalaz\Cache\PerRequestCache;

class PerRequestCacheTest extends CacheUnitTestCase
{
    private PerRequestCache $cache;

    protected function setUpCache(): void
    {
        $this->cache = $this->createPerRequestCache();
    }

    // =========================================================================
    // Remember
    // =========================================================================

    public function test_remember_stores_value_on_first_call(): void
    {
        $value = $this->cache->remember('key', fn() => 'computed_value');
        $this->assertSame('computed_value', $value);
    }

    public function test_remember_returns_cached_value_on_subsequent_calls(): void
    {
        $first = $this->cache->remember('foo', fn() => 'bar');
        $second = $this->cache->remember('foo', fn() => 'baz');

        $this->assertSame('bar', $first);
        $this->assertSame('bar', $second);
    }

    public function test_remember_callback_only_called_once(): void
    {
        $callCount = 0;

        $this->cache->remember('key', function () use (&$callCount) {
            $callCount++;
            return 'value';
        });

        $this->cache->remember('key', function () use (&$callCount) {
            $callCount++;
            return 'new_value';
        });

        $this->assertSame(1, $callCount);
    }

    public function test_remember_caches_null_value(): void
    {
        $callCount = 0;

        $value1 = $this->cache->remember('null_key', function () use (&$callCount) {
            $callCount++;
            return null;
        });

        $value2 = $this->cache->remember('null_key', function () use (&$callCount) {
            $callCount++;
            return 'should_not_be_called';
        });

        $this->assertNull($value1);
        $this->assertNull($value2);
        $this->assertSame(1, $callCount);
    }

    public function test_remember_caches_false_value(): void
    {
        $callCount = 0;

        $value1 = $this->cache->remember('false_key', function () use (&$callCount) {
            $callCount++;
            return false;
        });

        $value2 = $this->cache->remember('false_key', function () use (&$callCount) {
            $callCount++;
            return true;
        });

        $this->assertFalse($value1);
        $this->assertFalse($value2);
        $this->assertSame(1, $callCount);
    }

    public function test_remember_stores_different_keys_separately(): void
    {
        $value1 = $this->cache->remember('key1', fn() => 'value1');
        $value2 = $this->cache->remember('key2', fn() => 'value2');

        $this->assertSame('value1', $value1);
        $this->assertSame('value2', $value2);
    }

    // =========================================================================
    // Stats
    // =========================================================================

    public function test_stats_tracks_hits_and_misses(): void
    {
        $this->cache->remember('foo', fn() => 'bar');
        $this->cache->remember('foo', fn() => 'baz');

        $stats = $this->cache->stats();

        $this->assertSame(1, $stats['total_hits']);
        $this->assertSame(1, $stats['total_misses']);
    }

    public function test_stats_returns_zero_initially(): void
    {
        $stats = $this->cache->stats();

        $this->assertSame(0, $stats['total_hits']);
        $this->assertSame(0, $stats['total_misses']);
    }

    public function test_stats_accumulates_correctly(): void
    {
        $this->cache->remember('key1', fn() => 'value1'); // miss
        $this->cache->remember('key1', fn() => 'value1'); // hit
        $this->cache->remember('key1', fn() => 'value1'); // hit
        $this->cache->remember('key2', fn() => 'value2'); // miss
        $this->cache->remember('key2', fn() => 'value2'); // hit

        $stats = $this->cache->stats();

        $this->assertSame(3, $stats['total_hits']);
        $this->assertSame(2, $stats['total_misses']);
    }

    // =========================================================================
    // Complex Values
    // =========================================================================

    public function test_caches_array_values(): void
    {
        $array = ['foo' => 'bar', 'nested' => ['a' => 1, 'b' => 2]];
        $value = $this->cache->remember('array_key', fn() => $array);
        $this->assertSame($array, $value);
    }

    public function test_caches_object_values(): void
    {
        $object = new \stdClass();
        $object->name = 'test';
        $object->value = 123;

        $value = $this->cache->remember('object_key', fn() => $object);
        $this->assertSame($object, $value);
    }

    public function test_caches_integer_values(): void
    {
        $value = $this->cache->remember('int_key', fn() => 42);
        $this->assertSame(42, $value);
    }

    // =========================================================================
    // Flush
    // =========================================================================

    public function test_flush_removes_all_cached_values(): void
    {
        $this->cache->remember('key1', fn() => 'value1');
        $this->cache->remember('key2', fn() => 'value2');

        $this->cache->flush();

        $callCount = 0;
        $this->cache->remember('key1', function () use (&$callCount) {
            $callCount++;
            return 'new_value';
        });

        $this->assertSame(1, $callCount);
    }

    public function test_flush_resets_stats(): void
    {
        $this->cache->remember('key', fn() => 'value');
        $this->cache->remember('key', fn() => 'value');

        $this->cache->flush();

        $stats = $this->cache->stats();
        $this->assertSame(0, $stats['total_hits']);
        $this->assertSame(0, $stats['total_misses']);
    }

    // =========================================================================
    // Has
    // =========================================================================

    public function test_has_returns_false_when_key_not_cached(): void
    {
        $this->assertFalse($this->cache->has('nonexistent'));
    }

    public function test_has_returns_true_when_key_cached(): void
    {
        $this->cache->remember('exists', fn() => 'value');
        $this->assertTrue($this->cache->has('exists'));
    }

    // =========================================================================
    // Get
    // =========================================================================

    public function test_get_returns_default_when_key_not_found(): void
    {
        $this->assertNull($this->cache->get('nonexistent'));
        $this->assertSame('default', $this->cache->get('nonexistent', 'default'));
    }

    public function test_get_returns_cached_value(): void
    {
        $this->cache->remember('key', fn() => 'cached_value');
        $this->assertSame('cached_value', $this->cache->get('key'));
    }
}
