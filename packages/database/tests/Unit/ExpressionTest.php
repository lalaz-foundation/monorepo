<?php

declare(strict_types=1);

namespace Lalaz\Database\Tests\Unit;

use Lalaz\Database\Tests\Common\DatabaseUnitTestCase;
use Lalaz\Database\Query\Expression;

class ExpressionTest extends DatabaseUnitTestCase
{
    public function test_creates_expression_with_value(): void
    {
        $expression = new Expression('COUNT(*)');

        $this->assertSame('COUNT(*)', $expression->getValue());
    }

    public function test_casts_to_string(): void
    {
        $expression = new Expression('NOW()');

        $this->assertSame('NOW()', (string) $expression);
    }

    public function test_handles_complex_sql(): void
    {
        $sql = 'CASE WHEN status = 1 THEN "active" ELSE "inactive" END';
        $expression = new Expression($sql);

        $this->assertSame($sql, (string) $expression);
    }

    public function test_handles_empty_string(): void
    {
        $expression = new Expression('');

        $this->assertSame('', $expression->getValue());
        $this->assertSame('', (string) $expression);
    }

    public function test_handles_aggregate_functions(): void
    {
        $expression = new Expression('SUM(amount) as total');

        $this->assertSame('SUM(amount) as total', (string) $expression);
    }

    public function test_handles_subquery(): void
    {
        $sql = '(SELECT COUNT(*) FROM comments WHERE post_id = posts.id)';
        $expression = new Expression($sql);

        $this->assertSame($sql, $expression->getValue());
    }
}
