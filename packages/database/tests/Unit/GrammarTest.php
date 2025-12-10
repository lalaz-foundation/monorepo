<?php

declare(strict_types=1);

namespace Lalaz\Database\Tests\Unit;

use Lalaz\Database\Tests\Common\DatabaseUnitTestCase;
use Lalaz\Database\Query\Grammar;
use Lalaz\Database\Query\Grammars\MySqlGrammar;
use Lalaz\Database\Query\Grammars\PostgresGrammar;
use Lalaz\Database\Query\Grammars\SqliteGrammar;
use Lalaz\Database\Query\Expression;

class GrammarTest extends DatabaseUnitTestCase
{
    public function test_sqlite_grammar_wraps_identifiers_with_double_quotes(): void
    {
        $grammar = new SqliteGrammar();

        $this->assertSame('"users"', $grammar->wrap('users'));
        $this->assertSame('"email"', $grammar->wrap('email'));
    }

    public function test_mysql_grammar_wraps_identifiers_with_backticks(): void
    {
        $grammar = new MySqlGrammar();

        $this->assertSame('`users`', $grammar->wrap('users'));
        $this->assertSame('`email`', $grammar->wrap('email'));
    }

    public function test_postgres_grammar_wraps_identifiers_with_double_quotes(): void
    {
        $grammar = new PostgresGrammar();

        $this->assertSame('"users"', $grammar->wrap('users'));
        $this->assertSame('"email"', $grammar->wrap('email'));
    }

    public function test_wrap_handles_qualified_names(): void
    {
        $grammar = new Grammar();

        $this->assertSame('"users"."email"', $grammar->wrap('users.email'));
        $this->assertSame('"posts"."user_id"', $grammar->wrap('posts.user_id'));
    }

    public function test_wrap_handles_star_wildcard(): void
    {
        $grammar = new Grammar();

        $this->assertSame('*', $grammar->wrap('*'));
        $this->assertSame('"users".*', $grammar->wrap('users.*'));
    }

    public function test_wrap_handles_aliases(): void
    {
        $grammar = new Grammar();

        $this->assertSame('"users" as "u"', $grammar->wrap('users as u'));
        $this->assertSame('"posts" as "p"', $grammar->wrap('posts AS p'));
    }

    public function test_wrap_handles_expressions(): void
    {
        $grammar = new Grammar();
        $expression = new Expression('COUNT(*)');

        $this->assertSame('COUNT(*)', $grammar->wrap($expression));
    }

    public function test_wrap_table(): void
    {
        $grammar = new Grammar();

        $this->assertSame('"users"', $grammar->wrapTable('users'));
    }

    public function test_sqlite_share_lock(): void
    {
        $grammar = new SqliteGrammar();

        $this->assertSame('lock in share mode', $grammar->compileShareLock());
    }

    public function test_mysql_share_lock(): void
    {
        $grammar = new MySqlGrammar();

        $this->assertSame('lock in share mode', $grammar->compileShareLock());
    }

    public function test_postgres_share_lock(): void
    {
        $grammar = new PostgresGrammar();

        $this->assertSame('for share', $grammar->compileShareLock());
    }

    public function test_compile_select_builds_basic_query(): void
    {
        $connection = $this->createSqliteConnection();
        $query = $connection->table('users');

        $sql = $query->toSql();

        $this->assertSame('select * from "users"', $sql);
    }

    public function test_compile_select_with_columns(): void
    {
        $connection = $this->createSqliteConnection();
        $query = $connection->table('users')->select('id', 'name');

        $sql = $query->toSql();

        $this->assertSame('select "id", "name" from "users"', $sql);
    }

    public function test_compile_select_with_distinct(): void
    {
        $connection = $this->createSqliteConnection();
        $query = $connection->table('users')->select('status')->distinct();

        $sql = $query->toSql();

        $this->assertSame('select distinct "status" from "users"', $sql);
    }

    public function test_compile_where_clause(): void
    {
        $connection = $this->createSqliteConnection();
        $query = $connection->table('users')->where('id', '=', 1);

        $sql = $query->toSql();

        $this->assertStringContainsString('where "id" = ?', $sql);
    }

    public function test_compile_where_null(): void
    {
        $connection = $this->createSqliteConnection();
        $query = $connection->table('users')->whereNull('deleted_at');

        $sql = $query->toSql();

        $this->assertStringContainsString('where "deleted_at" is null', $sql);
    }

    public function test_compile_where_not_null(): void
    {
        $connection = $this->createSqliteConnection();
        $query = $connection->table('users')->whereNotNull('email');

        $sql = $query->toSql();

        $this->assertStringContainsString('where "email" is not null', $sql);
    }

    public function test_compile_where_in(): void
    {
        $connection = $this->createSqliteConnection();
        $query = $connection->table('users')->whereIn('id', [1, 2, 3]);

        $sql = $query->toSql();

        $this->assertStringContainsString('where "id" in (?, ?, ?)', $sql);
    }

    public function test_compile_where_not_in(): void
    {
        $connection = $this->createSqliteConnection();
        $query = $connection->table('users')->whereNotIn('status', ['banned', 'deleted']);

        $sql = $query->toSql();

        $this->assertStringContainsString('where "status" not in (?, ?)', $sql);
    }

    public function test_compile_where_between(): void
    {
        $connection = $this->createSqliteConnection();
        $query = $connection->table('orders')->whereBetween('amount', [100, 500]);

        $sql = $query->toSql();

        $this->assertStringContainsString('where "amount" between ? and ?', $sql);
    }

    public function test_compile_order_by(): void
    {
        $connection = $this->createSqliteConnection();
        $query = $connection->table('users')->orderBy('created_at', 'desc');

        $sql = $query->toSql();

        $this->assertStringContainsString('order by "created_at" DESC', $sql);
    }

    public function test_compile_limit_and_offset(): void
    {
        $connection = $this->createSqliteConnection();
        $query = $connection->table('users')->limit(10)->offset(20);

        $sql = $query->toSql();

        $this->assertStringContainsString('limit 10', $sql);
        $this->assertStringContainsString('offset 20', $sql);
    }

    public function test_compile_group_by(): void
    {
        $connection = $this->createSqliteConnection();
        $query = $connection->table('orders')->select('status')->groupBy('status');

        $sql = $query->toSql();

        $this->assertStringContainsString('group by "status"', $sql);
    }

    public function test_compile_having(): void
    {
        $connection = $this->createSqliteConnection();
        $query = $connection
            ->table('orders')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('status')
            ->having('total', '>', 10);

        $sql = $query->toSql();

        $this->assertStringContainsString('having "total" > ?', $sql);
    }

    public function test_compile_join(): void
    {
        $connection = $this->createSqliteConnection();
        $query = $connection
            ->table('posts')
            ->join('users', 'posts.user_id', '=', 'users.id')
            ->select('posts.title', 'users.name');

        $sql = $query->toSql();

        $this->assertStringContainsStringIgnoringCase('inner join "users" on "posts"."user_id" = "users"."id"', $sql);
    }

    public function test_compile_left_join(): void
    {
        $connection = $this->createSqliteConnection();
        $query = $connection
            ->table('posts')
            ->leftJoin('comments', 'posts.id', '=', 'comments.post_id');

        $sql = $query->toSql();

        $this->assertStringContainsStringIgnoringCase('left join "comments" on "posts"."id" = "comments"."post_id"', $sql);
    }

    public function test_compile_insert(): void
    {
        $connection = $this->createSqliteConnection();
        $query = $connection->table('users');
        $grammar = $query->grammar();

        $sql = $grammar->compileInsert($query, [['name' => 'John', 'email' => 'john@example.com']]);

        $this->assertStringContainsString('insert into "users"', $sql);
        $this->assertStringContainsString('"name"', $sql);
        $this->assertStringContainsString('"email"', $sql);
    }

    public function test_compile_update(): void
    {
        $connection = $this->createSqliteConnection();
        $query = $connection->table('users')->where('id', '=', 1);
        $grammar = $query->grammar();

        $sql = $grammar->compileUpdate($query, ['name' => 'Jane']);

        $this->assertStringContainsString('update "users" set "name" = ?', $sql);
        $this->assertStringContainsString('where "id" = ?', $sql);
    }

    public function test_compile_delete(): void
    {
        $connection = $this->createSqliteConnection();
        $query = $connection->table('users')->where('id', '=', 1);
        $grammar = $query->grammar();

        $sql = $grammar->compileDelete($query);

        $this->assertStringContainsString('delete from "users"', $sql);
        $this->assertStringContainsString('where "id" = ?', $sql);
    }
}
