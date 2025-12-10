<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Support\Cache;

use Lalaz\Support\Cache\LruCache;
use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;

#[CoversClass(LruCache::class)]
/**
 * Tests for the LruCache class.
 */
final class LruCacheTest extends FrameworkUnitTestCase
{
    public function teststoresAndRetrievesValues(): void
    {
        $cache = new LruCache(10);
        $cache->set('key1', 'value1');
        $cache->set('key2', 'value2');

        $this->assertSame('value1', $cache->get('key1'));
        $this->assertSame('value2', $cache->get('key2'));
    }

    public function testreportsHasCorrectly(): void
    {
        $cache = new LruCache(10);

        $this->assertFalse($cache->has('missing'));

        $cache->set('present', 'value');
        $this->assertTrue($cache->has('present'));
    }

    public function testreturnsNullForMissingKeys(): void
    {
        $cache = new LruCache(10);

        $this->assertNull($cache->get('missing'));
    }

    public function testcountsItemsCorrectly(): void
    {
        $cache = new LruCache(10);

        $this->assertSame(0, $cache->count());

        $cache->set('a', 1);
        $cache->set('b', 2);
        $cache->set('c', 3);

        $this->assertSame(3, $cache->count());
    }

    public function testclearsAllItems(): void
    {
        $cache = new LruCache(10);
        $cache->set('a', 1);
        $cache->set('b', 2);

        $cache->clear();

        $this->assertSame(0, $cache->count());
        $this->assertFalse($cache->has('a'));
        $this->assertFalse($cache->has('b'));
    }

    public function testevictsLeastRecentlyUsedItemWhenCapacityExceeded(): void
    {
        $cache = new LruCache(3);

        $cache->set('first', 1);
        $cache->set('second', 2);
        $cache->set('third', 3);

        $this->assertSame(3, $cache->count());

        // Adding fourth item should evict 'first' (oldest)
        $cache->set('fourth', 4);

        $this->assertSame(3, $cache->count());
        $this->assertFalse($cache->has('first'));
        $this->assertTrue($cache->has('second'));
        $this->assertTrue($cache->has('third'));
        $this->assertTrue($cache->has('fourth'));
    }

    public function testupdatesLruOrderOnGet(): void
    {
        $cache = new LruCache(3);

        $cache->set('first', 1);
        $cache->set('second', 2);
        $cache->set('third', 3);

        // Access 'first' to make it most recently used
        $cache->get('first');

        // Add new item - should evict 'second' (now the oldest)
        $cache->set('fourth', 4);

        $this->assertTrue($cache->has('first'));
        $this->assertFalse($cache->has('second'));
        $this->assertTrue($cache->has('third'));
        $this->assertTrue($cache->has('fourth'));
    }

    public function testupdatesExistingKeysWithoutIncreasingCount(): void
    {
        $cache = new LruCache(3);

        $cache->set('key', 'original');
        $cache->set('other', 'value');

        $this->assertSame(2, $cache->count());

        $cache->set('key', 'updated');

        $this->assertSame(2, $cache->count());
        $this->assertSame('updated', $cache->get('key'));
    }

    public function testhandlesObjectValues(): void
    {
        $cache = new LruCache(10);
        $obj = new stdClass();
        $obj->name = 'test';

        $cache->set('object', $obj);

        $this->assertSame($obj, $cache->get('object'));
        $this->assertSame('test', $cache->get('object')->name);
    }

    public function testhandlesArrayValues(): void
    {
        $cache = new LruCache(10);
        $arr = ['a' => 1, 'b' => 2];

        $cache->set('array', $arr);

        $this->assertSame($arr, $cache->get('array'));
    }

    public function test_respects_max_size_of_1(): void
    {
        $cache = new LruCache(1);

        $cache->set('first', 1);
        $this->assertSame(1, $cache->count());
        $this->assertSame(1, $cache->get('first'));

        $cache->set('second', 2);
        $this->assertSame(1, $cache->count());
        $this->assertFalse($cache->has('first'));
        $this->assertSame(2, $cache->get('second'));
    }

    public function testhandlesLargeNumberOfEvictionsCorrectly(): void
    {
        $cache = new LruCache(5);

        // Insert 100 items
        for ($i = 0; $i < 100; $i++) {
            $cache->set("key{$i}", $i);
        }

        $this->assertSame(5, $cache->count());

        // Only last 5 items should remain
        for ($i = 95; $i < 100; $i++) {
            $this->assertTrue($cache->has("key{$i}"));
            $this->assertSame($i, $cache->get("key{$i}"));
        }

        // Earlier items should be evicted
        for ($i = 0; $i < 95; $i++) {
            $this->assertFalse($cache->has("key{$i}"));
        }
    }
}
