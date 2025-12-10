<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Support\Functions;

use Lalaz\Support\Collections\Collection;
use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;

class CollectionsTest extends FrameworkUnitTestCase
{
    public function testcollectCreatesCollection(): void
    {
        $result = collect([1, 2, 3]);
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertEquals([1, 2, 3], $result->all());
    }

    public function testcollectEmptyCreatesEmptyCollection(): void
    {
        $result = collect();
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    public function testdataGetRetrievesSimpleValue(): void
    {
        $data = ['name' => 'John'];
        $this->assertEquals('John', data_get($data, 'name'));
    }

    public function testdataGetRetrievesNestedValue(): void
    {
        $data = [
            'user' => [
                'name' => 'John',
                'address' => [
                    'city' => 'New York',
                ],
            ],
        ];

        $this->assertEquals('New York', data_get($data, 'user.address.city'));
    }

    public function testdataGetReturnsDefaultForMissing(): void
    {
        $data = ['name' => 'John'];
        $this->assertEquals('N/A', data_get($data, 'email', 'N/A'));
    }

    public function testdataGetWithNullKeyReturnsTarget(): void
    {
        $data = ['name' => 'John'];
        $this->assertEquals($data, data_get($data, null));
    }

    public function testdataGetWorksWithObjects(): void
    {
        $obj = new \stdClass();
        $obj->name = 'John';
        $obj->address = new \stdClass();
        $obj->address->city = 'New York';

        $this->assertEquals('John', data_get($obj, 'name'));
        $this->assertEquals('New York', data_get($obj, 'address.city'));
    }

    public function testdataGetWithWildcard(): void
    {
        $data = [
            ['name' => 'John'],
            ['name' => 'Jane'],
        ];

        $result = data_get($data, '*.name');
        $this->assertEquals(['John', 'Jane'], $result);
    }

    public function testdataSetSetsSimpleValue(): void
    {
        $data = [];
        data_set($data, 'name', 'John');
        $this->assertEquals(['name' => 'John'], $data);
    }

    public function testdataSetSetsNestedValue(): void
    {
        $data = [];
        data_set($data, 'user.address.city', 'New York');
        $this->assertEquals(['user' => ['address' => ['city' => 'New York']]], $data);
    }

    public function testdataSetDoesNotOverwriteWhenFalse(): void
    {
        $data = ['name' => 'John'];
        data_set($data, 'name', 'Jane', false);
        $this->assertEquals('John', $data['name']);
    }

    public function testdataFillFillsMissingValues(): void
    {
        $data = ['name' => 'John'];
        data_fill($data, 'email', 'default@example.com');
        $this->assertEquals('default@example.com', $data['email']);
    }

    public function testdataFillDoesNotOverwriteExisting(): void
    {
        $data = ['name' => 'John'];
        data_fill($data, 'name', 'Jane');
        $this->assertEquals('John', $data['name']);
    }

    public function testdataForgetRemovesKey(): void
    {
        $data = ['name' => 'John', 'email' => 'john@example.com'];
        data_forget($data, 'email');
        $this->assertEquals(['name' => 'John'], $data);
    }

    public function testdataForgetRemovesNestedKey(): void
    {
        $data = ['user' => ['name' => 'John', 'email' => 'john@example.com']];
        data_forget($data, 'user.email');
        $this->assertEquals(['user' => ['name' => 'John']], $data);
    }

    public function testvalueReturnsValueAsIs(): void
    {
        $this->assertEquals('test', value('test'));
        $this->assertEquals(123, value(123));
    }

    public function testvalueInvokesCallable(): void
    {
        $this->assertEquals('result', value(fn() => 'result'));
    }

    public function testvaluePassesArgumentsToCallable(): void
    {
        $result = value(fn($a, $b) => $a + $b, 2, 3);
        $this->assertEquals(5, $result);
    }

    public function testheadReturnsFirstElement(): void
    {
        $this->assertEquals(1, head([1, 2, 3]));
    }

    public function testlastReturnsLastElement(): void
    {
        $this->assertEquals(3, last([1, 2, 3]));
    }
}
