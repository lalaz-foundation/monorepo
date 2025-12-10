<?php

declare(strict_types=1);

namespace Lalaz\Database\Tests\Unit;

use Lalaz\Database\Tests\Common\DatabaseUnitTestCase;
use Lalaz\Database\Schema\ColumnDefinition;

class ColumnDefinitionTest extends DatabaseUnitTestCase
{
    public function test_creates_column_with_name_and_type(): void
    {
        $column = new ColumnDefinition('email', 'string');

        $this->assertSame('email', $column->name);
        $this->assertSame('string', $column->type);
    }

    public function test_creates_column_with_length(): void
    {
        $column = new ColumnDefinition('phone', 'string', 20);

        $this->assertSame(20, $column->length);
    }

    public function test_nullable_returns_self_for_chaining(): void
    {
        $column = new ColumnDefinition('email', 'string');

        $result = $column->nullable();

        $this->assertSame($column, $result);
        $this->assertTrue($column->nullable);
    }

    public function test_nullable_accepts_false_parameter(): void
    {
        $column = new ColumnDefinition('email', 'string');
        $column->nullable(true);
        $column->nullable(false);

        $this->assertFalse($column->nullable);
    }

    public function test_default_sets_value(): void
    {
        $column = new ColumnDefinition('status', 'string');

        $result = $column->default('active');

        $this->assertSame($column, $result);
        $this->assertSame('active', $column->default);
    }

    public function test_default_accepts_null(): void
    {
        $column = new ColumnDefinition('status', 'string');
        $column->default(null);

        $this->assertNull($column->default);
    }

    public function test_default_accepts_integer(): void
    {
        $column = new ColumnDefinition('count', 'integer');
        $column->default(0);

        $this->assertSame(0, $column->default);
    }

    public function test_unique_returns_self_for_chaining(): void
    {
        $column = new ColumnDefinition('email', 'string');

        $result = $column->unique();

        $this->assertSame($column, $result);
        $this->assertTrue($column->unique);
    }

    public function test_unique_accepts_false_parameter(): void
    {
        $column = new ColumnDefinition('email', 'string');
        $column->unique(true);
        $column->unique(false);

        $this->assertFalse($column->unique);
    }

    public function test_primary_returns_self_for_chaining(): void
    {
        $column = new ColumnDefinition('id', 'integer');

        $result = $column->primary();

        $this->assertSame($column, $result);
        $this->assertTrue($column->primary);
    }

    public function test_primary_accepts_false_parameter(): void
    {
        $column = new ColumnDefinition('id', 'integer');
        $column->primary(true);
        $column->primary(false);

        $this->assertFalse($column->primary);
    }

    public function test_auto_increment_returns_self_for_chaining(): void
    {
        $column = new ColumnDefinition('id', 'integer');

        $result = $column->autoIncrement();

        $this->assertSame($column, $result);
        $this->assertTrue($column->autoIncrement);
    }

    public function test_unsigned_returns_self_for_chaining(): void
    {
        $column = new ColumnDefinition('count', 'integer');

        $result = $column->unsigned();

        $this->assertSame($column, $result);
        $this->assertTrue($column->unsigned);
    }

    public function test_change_returns_self_for_chaining(): void
    {
        $column = new ColumnDefinition('email', 'string');

        $result = $column->change();

        $this->assertSame($column, $result);
        $this->assertTrue($column->change);
    }

    public function test_change_accepts_false_parameter(): void
    {
        $column = new ColumnDefinition('email', 'string');
        $column->change(true);
        $column->change(false);

        $this->assertFalse($column->change);
    }

    public function test_fluent_chaining(): void
    {
        $column = new ColumnDefinition('email', 'string', 100);

        $column
            ->nullable()
            ->unique()
            ->default('user@example.com');

        $this->assertTrue($column->nullable);
        $this->assertTrue($column->unique);
        $this->assertSame('user@example.com', $column->default);
    }

    public function test_default_values_for_new_column(): void
    {
        $column = new ColumnDefinition('test', 'string');

        $this->assertFalse($column->nullable);
        $this->assertFalse($column->autoIncrement);
        $this->assertFalse($column->primary);
        $this->assertFalse($column->unique);
        $this->assertFalse($column->unsigned);
        $this->assertNull($column->default);
        $this->assertFalse($column->change);
    }

    public function test_constructor_with_all_parameters(): void
    {
        $column = new ColumnDefinition(
            'id',
            'increments',
            length: null,
            nullable: false,
            autoIncrement: true,
            primary: true,
            unique: false,
            unsigned: true,
            default: null,
            change: false
        );

        $this->assertSame('id', $column->name);
        $this->assertSame('increments', $column->type);
        $this->assertNull($column->length);
        $this->assertFalse($column->nullable);
        $this->assertTrue($column->autoIncrement);
        $this->assertTrue($column->primary);
        $this->assertFalse($column->unique);
        $this->assertTrue($column->unsigned);
        $this->assertNull($column->default);
        $this->assertFalse($column->change);
    }
}
