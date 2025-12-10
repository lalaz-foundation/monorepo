<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Support\Collections;

use Lalaz\Support\Collections\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;

#[CoversClass(Collection::class)]
class CollectionTest extends FrameworkUnitTestCase
{
    // =========================================================================
    // Creation Tests
    // =========================================================================

    public function testcreateCreatesCollectionFromArray(): void
    {
        $collection = Collection::create([1, 2, 3]);
        $this->assertEquals([1, 2, 3], $collection->all());
    }

    public function testcreateReturnsSameInstanceIfAlreadyCollection(): void
    {
        $original = Collection::create([1, 2, 3]);
        $result = Collection::create($original);
        $this->assertSame($original, $result);
    }

    public function testrangeCreatesCollectionFromRange(): void
    {
        $collection = Collection::range(1, 5);
        $this->assertEquals([1, 2, 3, 4, 5], $collection->all());
    }

    public function testfillCreatesCollectionWithCopies(): void
    {
        $collection = Collection::fill(3, 'x');
        $this->assertEquals(['x', 'x', 'x'], $collection->all());
    }

    public function testwrapWrapsValueInCollection(): void
    {
        $collection = Collection::wrap('value');
        $this->assertEquals(['value'], $collection->all());
    }

    // =========================================================================
    // Basic Operations Tests
    // =========================================================================

    public function testallReturnsUnderlyingArray(): void
    {
        $collection = new Collection([1, 2, 3]);
        $this->assertEquals([1, 2, 3], $collection->all());
    }

    public function testcountReturnsItemCount(): void
    {
        $collection = new Collection([1, 2, 3]);
        $this->assertEquals(3, $collection->count());
    }

    public function testisEmptyReturnsTrueForEmpty(): void
    {
        $collection = new Collection([]);
        $this->assertTrue($collection->isEmpty());
    }

    public function testisNotEmptyReturnsTrueForNonEmpty(): void
    {
        $collection = new Collection([1]);
        $this->assertTrue($collection->isNotEmpty());
    }

    public function testgetReturnsItemByKey(): void
    {
        $collection = new Collection(['name' => 'John', 'age' => 30]);
        $this->assertEquals('John', $collection->get('name'));
    }

    public function testgetReturnsDefaultForMissingKey(): void
    {
        $collection = new Collection(['name' => 'John']);
        $this->assertEquals('N/A', $collection->get('email', 'N/A'));
    }

    public function testhasChecksKeyExistence(): void
    {
        $collection = new Collection(['name' => 'John']);
        $this->assertTrue($collection->has('name'));
        $this->assertFalse($collection->has('email'));
    }

    // =========================================================================
    // Transformation Tests
    // =========================================================================

    public function testmapTransformsItems(): void
    {
        $collection = new Collection([1, 2, 3]);
        $result = $collection->map(fn($n) => $n * 2);
        $this->assertEquals([2, 4, 6], $result->all());
    }

    public function testfilterFiltersItems(): void
    {
        $collection = new Collection([1, 2, 3, 4, 5]);
        $result = $collection->filter(fn($n) => $n > 2);
        $this->assertEquals([2 => 3, 3 => 4, 4 => 5], $result->all());
    }

    public function testfilterWithoutCallbackRemovesFalsy(): void
    {
        $collection = new Collection([0, 1, false, 2, '', 3, null]);
        $result = $collection->filter();
        $this->assertEquals([1 => 1, 3 => 2, 5 => 3], $result->all());
    }

    public function testreduceReducesToSingleValue(): void
    {
        $collection = new Collection([1, 2, 3, 4]);
        $result = $collection->reduce(fn($carry, $n) => $carry + $n, 0);
        $this->assertEquals(10, $result);
    }

    public function testeachIteratesOverItems(): void
    {
        $collection = new Collection([1, 2, 3]);
        $sum = 0;
        $collection->each(function ($item) use (&$sum) {
            $sum += $item;
        });
        $this->assertEquals(6, $sum);
    }

    public function testeachCanBeStopped(): void
    {
        $collection = new Collection([1, 2, 3, 4, 5]);
        $sum = 0;
        $collection->each(function ($item) use (&$sum) {
            if ($item > 3) {
                return false;
            }
            $sum += $item;
        });
        $this->assertEquals(6, $sum);
    }

    // =========================================================================
    // First/Last Tests
    // =========================================================================

    public function testfirstReturnsFirstItem(): void
    {
        $collection = new Collection([1, 2, 3]);
        $this->assertEquals(1, $collection->first());
    }

    public function testfirstWithCallbackReturnsMatching(): void
    {
        $collection = new Collection([1, 2, 3, 4]);
        $result = $collection->first(fn($n) => $n > 2);
        $this->assertEquals(3, $result);
    }

    public function testfirstReturnsDefaultWhenEmpty(): void
    {
        $collection = new Collection([]);
        $this->assertEquals('default', $collection->first(null, 'default'));
    }

    public function testlastReturnsLastItem(): void
    {
        $collection = new Collection([1, 2, 3]);
        $this->assertEquals(3, $collection->last());
    }

    public function testlastWithCallbackReturnsMatching(): void
    {
        $collection = new Collection([1, 2, 3, 4]);
        $result = $collection->last(fn($n) => $n < 3);
        $this->assertEquals(2, $result);
    }

    // =========================================================================
    // Pluck Tests
    // =========================================================================

    public function testpluckExtractsValues(): void
    {
        $collection = new Collection([
            ['name' => 'John', 'age' => 30],
            ['name' => 'Jane', 'age' => 25],
        ]);
        $result = $collection->pluck('name');
        $this->assertEquals(['John', 'Jane'], $result->all());
    }

    public function testpluckWithKey(): void
    {
        $collection = new Collection([
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane'],
        ]);
        $result = $collection->pluck('name', 'id');
        $this->assertEquals([1 => 'John', 2 => 'Jane'], $result->all());
    }

    // =========================================================================
    // Where Tests
    // =========================================================================

    public function testwhereFiltersByKeyValue(): void
    {
        $collection = new Collection([
            ['name' => 'John', 'active' => true],
            ['name' => 'Jane', 'active' => false],
            ['name' => 'Bob', 'active' => true],
        ]);
        $result = $collection->where('active', true);
        $this->assertEquals(2, $result->count());
    }

    public function testwhereWithOperator(): void
    {
        $collection = new Collection([
            ['name' => 'John', 'age' => 30],
            ['name' => 'Jane', 'age' => 25],
            ['name' => 'Bob', 'age' => 35],
        ]);
        $result = $collection->where('age', '>=', 30);
        $this->assertEquals(2, $result->count());
    }

    public function testwhereInFiltersByArray(): void
    {
        $collection = new Collection([
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane'],
            ['id' => 3, 'name' => 'Bob'],
        ]);
        $result = $collection->whereIn('id', [1, 3]);
        $this->assertEquals(2, $result->count());
    }

    // =========================================================================
    // Sort Tests
    // =========================================================================

    public function testsortSortsItems(): void
    {
        $collection = new Collection([3, 1, 2]);
        $result = $collection->sort()->values();
        $this->assertEquals([1, 2, 3], $result->all());
    }

    public function testsortBySortsByKey(): void
    {
        $collection = new Collection([
            ['name' => 'John', 'age' => 30],
            ['name' => 'Jane', 'age' => 25],
        ]);
        $result = $collection->sortBy('age')->values();
        $this->assertEquals('Jane', $result->first()['name']);
    }

    public function testsortByDescSortsDescending(): void
    {
        $collection = new Collection([
            ['name' => 'John', 'age' => 30],
            ['name' => 'Jane', 'age' => 25],
        ]);
        $result = $collection->sortByDesc('age')->values();
        $this->assertEquals('John', $result->first()['name']);
    }

    // =========================================================================
    // Group Tests
    // =========================================================================

    public function testgroupByGroupsItems(): void
    {
        $collection = new Collection([
            ['type' => 'A', 'value' => 1],
            ['type' => 'B', 'value' => 2],
            ['type' => 'A', 'value' => 3],
        ]);
        $result = $collection->groupBy('type');
        $this->assertEquals(2, $result->get('A')->count());
        $this->assertEquals(1, $result->get('B')->count());
    }

    public function testkeyByKeysByField(): void
    {
        $collection = new Collection([
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane'],
        ]);
        $result = $collection->keyBy('id');
        $this->assertEquals('John', $result->get(1)['name']);
    }

    // =========================================================================
    // Aggregation Tests
    // =========================================================================

    public function testsumSumsValues(): void
    {
        $collection = new Collection([1, 2, 3]);
        $this->assertEquals(6, $collection->sum());
    }

    public function testsumWithKey(): void
    {
        $collection = new Collection([
            ['amount' => 100],
            ['amount' => 200],
        ]);
        $this->assertEquals(300, $collection->sum('amount'));
    }

    public function testavgCalculatesAverage(): void
    {
        $collection = new Collection([1, 2, 3, 4]);
        $this->assertEquals(2.5, $collection->avg());
    }

    public function testminReturnsMinimum(): void
    {
        $collection = new Collection([3, 1, 2]);
        $this->assertEquals(1, $collection->min());
    }

    public function testmaxReturnsMaximum(): void
    {
        $collection = new Collection([3, 1, 2]);
        $this->assertEquals(3, $collection->max());
    }

    public function testmedianCalculatesMedian(): void
    {
        $collection = new Collection([1, 2, 3, 4, 5]);
        $this->assertEquals(3, $collection->median());
    }

    // =========================================================================
    // Contains Tests
    // =========================================================================

    public function testcontainsChecksValue(): void
    {
        $collection = new Collection([1, 2, 3]);
        $this->assertTrue($collection->contains(2));
        $this->assertFalse($collection->contains(4));
    }

    public function testcontainsWithCallback(): void
    {
        $collection = new Collection([
            ['name' => 'John', 'admin' => true],
            ['name' => 'Jane', 'admin' => false],
        ]);
        $this->assertTrue($collection->contains(fn($u) => $u['admin']));
    }

    // =========================================================================
    // Slice/Chunk Tests
    // =========================================================================

    public function testtakeTakesFirstNItems(): void
    {
        $collection = new Collection([1, 2, 3, 4, 5]);
        $result = $collection->take(3);
        $this->assertEquals([1, 2, 3], $result->all());
    }

    public function testtakeNegativeTakesFromEnd(): void
    {
        $collection = new Collection([1, 2, 3, 4, 5]);
        $result = $collection->take(-2);
        $this->assertEquals([4, 5], $result->values()->all());
    }

    public function testskipSkipsFirstNItems(): void
    {
        $collection = new Collection([1, 2, 3, 4, 5]);
        $result = $collection->skip(2)->values();
        $this->assertEquals([3, 4, 5], $result->all());
    }

    public function testchunkSplitsIntoChunks(): void
    {
        $collection = new Collection([1, 2, 3, 4, 5]);
        $result = $collection->chunk(2);
        $this->assertEquals(3, $result->count());
        $this->assertEquals([1, 2], $result->first()->all());
    }

    // =========================================================================
    // Unique Tests
    // =========================================================================

    public function testuniqueRemovesDuplicates(): void
    {
        $collection = new Collection([1, 2, 2, 3, 3, 3]);
        $result = $collection->unique()->values();
        $this->assertEquals([1, 2, 3], $result->all());
    }

    public function testuniqueByKey(): void
    {
        $collection = new Collection([
            ['type' => 'A', 'value' => 1],
            ['type' => 'B', 'value' => 2],
            ['type' => 'A', 'value' => 3],
        ]);
        $result = $collection->unique('type');
        $this->assertEquals(2, $result->count());
    }

    // =========================================================================
    // Merge/Combine Tests
    // =========================================================================

    public function testmergeMergesArrays(): void
    {
        $collection = new Collection([1, 2]);
        $result = $collection->merge([3, 4]);
        $this->assertEquals([1, 2, 3, 4], $result->all());
    }

    public function testcombineCombinesKeysValues(): void
    {
        $keys = new Collection(['name', 'age']);
        $result = $keys->combine(['John', 30]);
        $this->assertEquals(['name' => 'John', 'age' => 30], $result->all());
    }

    public function testunionUnionsArrays(): void
    {
        $collection = new Collection(['a' => 1, 'b' => 2]);
        $result = $collection->union(['b' => 3, 'c' => 4]);
        $this->assertEquals(['a' => 1, 'b' => 2, 'c' => 4], $result->all());
    }

    // =========================================================================
    // Diff Tests
    // =========================================================================

    public function testdiffReturnsDifference(): void
    {
        $collection = new Collection([1, 2, 3, 4, 5]);
        $result = $collection->diff([2, 4]);
        $this->assertEquals([0 => 1, 2 => 3, 4 => 5], $result->all());
    }

    public function testintersectReturnsIntersection(): void
    {
        $collection = new Collection([1, 2, 3, 4, 5]);
        $result = $collection->intersect([2, 4, 6]);
        $this->assertEquals([1 => 2, 3 => 4], $result->all());
    }

    // =========================================================================
    // Push/Pop/Shift Tests
    // =========================================================================

    public function testpushAddsItem(): void
    {
        $collection = new Collection([1, 2]);
        $collection->push(3);
        $this->assertEquals([1, 2, 3], $collection->all());
    }

    public function testpopRemovesLast(): void
    {
        $collection = new Collection([1, 2, 3]);
        $result = $collection->pop();
        $this->assertEquals(3, $result);
        $this->assertEquals([1, 2], $collection->all());
    }

    public function testshiftRemovesFirst(): void
    {
        $collection = new Collection([1, 2, 3]);
        $result = $collection->shift();
        $this->assertEquals(1, $result);
        $this->assertEquals([2, 3], $collection->all());
    }

    public function testprependAddsToBeginning(): void
    {
        $collection = new Collection([2, 3]);
        $collection->prepend(1);
        $this->assertEquals([1, 2, 3], $collection->all());
    }

    // =========================================================================
    // Flatten/Collapse Tests
    // =========================================================================

    public function testflattenFlattensArray(): void
    {
        $collection = new Collection([[1, 2], [3, [4, 5]]]);
        $result = $collection->flatten();
        $this->assertEquals([1, 2, 3, 4, 5], $result->all());
    }

    public function testflattenWithDepth(): void
    {
        $collection = new Collection([[1, [2, [3]]]]);
        $result = $collection->flatten(1);
        $this->assertEquals([1, [2, [3]]], $result->all());
    }

    public function testcollapseCollapsesArrays(): void
    {
        $collection = new Collection([[1, 2], [3, 4]]);
        $result = $collection->collapse();
        $this->assertEquals([1, 2, 3, 4], $result->all());
    }

    // =========================================================================
    // Keys/Values Tests
    // =========================================================================

    public function testkeysReturnsKeys(): void
    {
        $collection = new Collection(['a' => 1, 'b' => 2]);
        $this->assertEquals(['a', 'b'], $collection->keys()->all());
    }

    public function testvaluesReturnsValues(): void
    {
        $collection = new Collection(['a' => 1, 'b' => 2]);
        $this->assertEquals([1, 2], $collection->values()->all());
    }

    public function testflipFlipsKeysValues(): void
    {
        $collection = new Collection(['a' => 1, 'b' => 2]);
        $this->assertEquals([1 => 'a', 2 => 'b'], $collection->flip()->all());
    }

    // =========================================================================
    // Conditional Tests
    // =========================================================================

    public function testwhenAppliesCallbackIfTrue(): void
    {
        $collection = new Collection([1, 2, 3]);
        $result = $collection->when(true, fn($c) => $c->push(4));
        $this->assertEquals([1, 2, 3, 4], $result->all());
    }

    public function testwhenDoesNotApplyIfFalse(): void
    {
        $collection = new Collection([1, 2, 3]);
        $result = $collection->when(false, fn($c) => $c->push(4));
        $this->assertEquals([1, 2, 3], $result->all());
    }

    public function testeveryReturnsTrueIfAllPass(): void
    {
        $collection = new Collection([2, 4, 6]);
        $this->assertTrue($collection->every(fn($n) => $n % 2 === 0));
    }

    public function testeveryReturnsFalseIfAnyFail(): void
    {
        $collection = new Collection([2, 3, 6]);
        $this->assertFalse($collection->every(fn($n) => $n % 2 === 0));
    }

    // =========================================================================
    // JSON/Array Tests
    // =========================================================================

    public function testtoArrayReturnsArray(): void
    {
        $collection = new Collection([1, 2, 3]);
        $this->assertEquals([1, 2, 3], $collection->toArray());
    }

    public function testtoJsonReturnsJson(): void
    {
        $collection = new Collection(['name' => 'John']);
        $this->assertEquals('{"name":"John"}', $collection->toJson());
    }

    public function testimplementsJsonSerializable(): void
    {
        $collection = new Collection([1, 2, 3]);
        $this->assertEquals('[1,2,3]', json_encode($collection));
    }

    // =========================================================================
    // Interface Tests
    // =========================================================================

    public function testimplementsCountable(): void
    {
        $collection = new Collection([1, 2, 3]);
        $this->assertCount(3, $collection);
    }

    public function testimplementsArrayAccess(): void
    {
        $collection = new Collection(['a' => 1]);
        $this->assertTrue(isset($collection['a']));
        $this->assertEquals(1, $collection['a']);

        $collection['b'] = 2;
        $this->assertEquals(2, $collection['b']);

        unset($collection['a']);
        $this->assertFalse(isset($collection['a']));
    }

    public function testisIterable(): void
    {
        $collection = new Collection([1, 2, 3]);
        $sum = 0;

        foreach ($collection as $item) {
            $sum += $item;
        }

        $this->assertEquals(6, $sum);
    }

    public function testtoStringReturnsJson(): void
    {
        $collection = new Collection([1, 2, 3]);
        $this->assertEquals('[1,2,3]', (string) $collection);
    }

    // =========================================================================
    // Pipe/Tap Tests
    // =========================================================================

    public function testpipePassesCollectionToCallback(): void
    {
        $collection = new Collection([1, 2, 3]);
        $result = $collection->pipe(fn($c) => $c->sum());
        $this->assertEquals(6, $result);
    }

    public function testtapCallsCallbackAndReturnsSelf(): void
    {
        $collection = new Collection([1, 2, 3]);
        $called = false;

        $result = $collection->tap(function ($c) use (&$called) {
            $called = true;
        });

        $this->assertTrue($called);
        $this->assertEquals([1, 2, 3], $result->all());
    }

    // =========================================================================
    // Partition Tests
    // =========================================================================

    public function testpartitionSplitsByCallback(): void
    {
        $collection = new Collection([1, 2, 3, 4, 5, 6]);
        [$evens, $odds] = $collection->partition(fn($n) => $n % 2 === 0)->all();

        $this->assertEquals([2, 4, 6], $evens->values()->all());
        $this->assertEquals([1, 3, 5], $odds->values()->all());
    }

    // =========================================================================
    // Implode/Join Tests
    // =========================================================================

    public function testimplodeJoinsValues(): void
    {
        $collection = new Collection(['a', 'b', 'c']);
        $this->assertEquals('a,b,c', $collection->implode(','));
    }

    public function testjoinWithFinalGlue(): void
    {
        $collection = new Collection(['a', 'b', 'c']);
        $this->assertEquals('a, b and c', $collection->join(', ', ' and '));
    }

    // =========================================================================
    // Search Tests
    // =========================================================================

    public function testsearchFindsValue(): void
    {
        $collection = new Collection(['a', 'b', 'c']);
        $this->assertEquals(1, $collection->search('b'));
    }

    public function testsearchReturnsFalseIfNotFound(): void
    {
        $collection = new Collection(['a', 'b', 'c']);
        $this->assertFalse($collection->search('d'));
    }

    // =========================================================================
    // Random Tests
    // =========================================================================

    public function testrandomReturnsRandomItem(): void
    {
        $collection = new Collection([1, 2, 3, 4, 5]);
        $result = $collection->random();
        $this->assertContains($result, [1, 2, 3, 4, 5]);
    }

    public function testrandomWithCountReturnsCollection(): void
    {
        $collection = new Collection([1, 2, 3, 4, 5]);
        $result = $collection->random(2);
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertEquals(2, $result->count());
    }

    // =========================================================================
    // Reverse/Shuffle Tests
    // =========================================================================

    public function testreverseReversesItems(): void
    {
        $collection = new Collection([1, 2, 3]);
        $result = $collection->reverse()->values();
        $this->assertEquals([3, 2, 1], $result->all());
    }

    public function testshuffleRandomizesOrder(): void
    {
        $collection = new Collection([1, 2, 3, 4, 5]);
        $shuffled = $collection->shuffle();

        // Verify same items exist
        $this->assertEquals(
            $collection->sort()->values()->all(),
            $shuffled->sort()->values()->all()
        );
    }
}
