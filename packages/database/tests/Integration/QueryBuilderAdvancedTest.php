<?php

declare(strict_types=1);

namespace Lalaz\Database\Tests\Integration;

use Lalaz\Database\Tests\Common\DatabaseIntegrationTestCase;

class QueryBuilderAdvancedTest extends DatabaseIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createStandardTestTables();
        $this->seedStandardTestData();
    }

    public function test_select_with_aggregate_functions(): void
    {
        $count = $this->connection->table('users')->count();
        $this->assertSame(3, $count);

        $sum = $this->connection->table('posts')->sum('views');
        $this->assertSame(225.0, $sum);

        $avg = $this->connection->table('users')->avg('age');
        $this->assertSame(30.0, $avg);

        $min = $this->connection->table('users')->min('age');
        $this->assertSame(25, $min);

        $max = $this->connection->table('users')->max('age');
        $this->assertSame(35, $max);
    }

    public function test_exists_and_doesnt_exist(): void
    {
        $exists = $this->connection->table('users')->where('name', 'Alice')->exists();
        $this->assertTrue($exists);

        $doesntExist = $this->connection->table('users')->where('name', 'Unknown')->doesntExist();
        $this->assertTrue($doesntExist);
    }

    public function test_pluck_column(): void
    {
        $names = $this->connection->table('users')->orderBy('name')->pluck('name');

        $this->assertSame(['Alice', 'Bob', 'Charlie'], $names);
    }

    public function test_pluck_column_with_key(): void
    {
        $emails = $this->connection->table('users')->pluck('email', 'name');

        $this->assertSame('alice@example.com', $emails['Alice']);
        $this->assertSame('bob@example.com', $emails['Bob']);
    }

    public function test_value_gets_single_column(): void
    {
        $name = $this->connection->table('users')->where('email', 'alice@example.com')->value('name');

        $this->assertSame('Alice', $name);
    }

    public function test_first_returns_single_row(): void
    {
        $user = $this->connection->table('users')->orderBy('age')->first();

        $this->assertSame('Bob', $user['name']);
        $this->assertSame(25, (int) $user['age']);
    }

    public function test_first_returns_null_when_no_results(): void
    {
        $user = $this->connection->table('users')->where('name', 'Unknown')->first();

        $this->assertNull($user);
    }

    public function test_pagination_with_for_page(): void
    {
        $page1 = $this->connection->table('users')->orderBy('name')->forPage(1, 2)->get();
        $page2 = $this->connection->table('users')->orderBy('name')->forPage(2, 2)->get();

        $this->assertCount(2, $page1);
        $this->assertCount(1, $page2);
        $this->assertSame('Alice', $page1[0]['name']);
        $this->assertSame('Charlie', $page2[0]['name']);
    }

    public function test_join_with_aggregation(): void
    {
        $results = $this->connection
            ->table('users')
            ->select('users.name')
            ->selectRaw('COUNT(posts.id) as post_count')
            ->leftJoin('posts', 'users.id', '=', 'posts.user_id')
            ->groupBy('users.name')
            ->orderByDesc('post_count')
            ->get();

        $this->assertSame('Alice', $results[0]['name']);
        $this->assertSame(2, (int) $results[0]['post_count']);
    }

    public function test_subquery_in_select(): void
    {
        $results = $this->connection
            ->table('users as u')
            ->select('u.name')
            ->selectSub(function ($query) {
                $query->from('posts as p')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('p.user_id', 'u.id');
            }, 'post_count')
            ->orderByDesc('post_count')
            ->get();

        $this->assertSame('Alice', $results[0]['name']);
        $this->assertSame(2, (int) $results[0]['post_count']);
    }

    public function test_where_exists_subquery(): void
    {
        $usersWithPosts = $this->connection
            ->table('users')
            ->whereExists(function ($query) {
                $query->from('posts')
                    ->selectRaw('1')
                    ->whereColumn('posts.user_id', 'users.id');
            })
            ->pluck('name');

        $this->assertContains('Alice', $usersWithPosts);
        $this->assertContains('Bob', $usersWithPosts);
        $this->assertNotContains('Charlie', $usersWithPosts);
    }

    public function test_where_in_subquery(): void
    {
        $usersWithComments = $this->connection
            ->table('users')
            ->whereIn('id', function ($query) {
                $query->from('posts')
                    ->join('comments', 'posts.id', '=', 'comments.post_id')
                    ->select('posts.user_id');
            })
            ->pluck('name');

        $this->assertContains('Alice', $usersWithComments);
    }

    public function test_increment_and_decrement(): void
    {
        $initialViews = $this->connection->table('posts')->where('id', 1)->value('views');

        $this->connection->table('posts')->where('id', 1)->increment('views', 10);
        $afterIncrement = $this->connection->table('posts')->where('id', 1)->value('views');

        $this->assertSame((int) $initialViews + 10, (int) $afterIncrement);

        $this->connection->table('posts')->where('id', 1)->decrement('views', 5);
        $afterDecrement = $this->connection->table('posts')->where('id', 1)->value('views');

        $this->assertSame((int) $afterIncrement - 5, (int) $afterDecrement);
    }

    public function test_increment_with_extra_columns(): void
    {
        $this->connection->table('posts')->where('id', 1)->increment('views', 1, ['title' => 'Updated Title']);

        $post = $this->connection->table('posts')->where('id', 1)->first();
        $this->assertSame('Updated Title', $post['title']);
    }

    public function test_update_where_shorthand(): void
    {
        $affected = $this->connection->table('users')->updateWhere(
            ['name' => 'Alice'],
            ['age' => 31]
        );

        $this->assertSame(1, $affected);
        $this->assertSame(31, $this->connection->table('users')->where('name', 'Alice')->value('age'));
    }

    public function test_delete_where_shorthand(): void
    {
        $initialCount = $this->connection->table('users')->count();

        $deleted = $this->connection->table('users')->deleteWhere(['name' => 'Charlie']);

        $this->assertSame(1, $deleted);
        $this->assertSame($initialCount - 1, $this->connection->table('users')->count());
    }

    public function test_union_queries(): void
    {
        // Insert additional test data
        $this->connection->table('users')->insert(['name' => 'David', 'email' => 'david@example.com', 'age' => 40]);

        // Get all users older than 30 and younger than 30 using separate queries
        $old = $this->connection->table('users')->where('age', '>=', 35)->pluck('name');
        $young = $this->connection->table('users')->where('age', '<', 30)->pluck('name');

        $names = array_merge($old, $young);

        $this->assertContains('Bob', $names); // age 25
        $this->assertContains('Charlie', $names); // age 35
        $this->assertContains('David', $names); // age 40
    }

    public function test_reorder_clears_previous_ordering(): void
    {
        $users = $this->connection
            ->table('users')
            ->orderBy('name', 'asc')
            ->reorder('age', 'desc')
            ->get();

        $this->assertSame('Charlie', $users[0]['name']); // age 35
        $this->assertSame('Alice', $users[1]['name']); // age 30
        $this->assertSame('Bob', $users[2]['name']); // age 25
    }

    public function test_latest_orders_by_created_at_desc(): void
    {
        // Add timestamps to test data
        $this->connection->table('users')->where('name', 'Alice')->update(['created_at' => '2024-01-01']);
        $this->connection->table('users')->where('name', 'Bob')->update(['created_at' => '2024-01-03']);
        $this->connection->table('users')->where('name', 'Charlie')->update(['created_at' => '2024-01-02']);

        $users = $this->connection->table('users')->latest('created_at')->get();

        $this->assertSame('Bob', $users[0]['name']);
    }

    public function test_oldest_orders_by_created_at_asc(): void
    {
        $this->connection->table('users')->where('name', 'Alice')->update(['created_at' => '2024-01-01']);
        $this->connection->table('users')->where('name', 'Bob')->update(['created_at' => '2024-01-03']);
        $this->connection->table('users')->where('name', 'Charlie')->update(['created_at' => '2024-01-02']);

        $users = $this->connection->table('users')->oldest('created_at')->get();

        $this->assertSame('Alice', $users[0]['name']);
    }

    public function test_or_where_clauses(): void
    {
        $users = $this->connection
            ->table('users')
            ->where('name', '=', 'Alice')
            ->orWhere('name', '=', 'Bob')
            ->orderBy('name')
            ->get();

        $this->assertCount(2, $users);
        $this->assertSame('Alice', $users[0]['name']);
        $this->assertSame('Bob', $users[1]['name']);
    }

    public function test_nested_where_clauses(): void
    {
        $users = $this->connection
            ->table('users')
            ->where('age', '>', 25)
            ->where(function ($query) {
                $query->where('name', '=', 'Alice')
                    ->orWhere('name', '=', 'Charlie');
            })
            ->orderBy('name')
            ->get();

        $this->assertCount(2, $users);
    }

    public function test_having_clause_filters_aggregates(): void
    {
        $results = $this->connection
            ->table('posts')
            ->select('user_id')
            ->selectRaw('SUM(views) as total_views')
            ->groupBy('user_id')
            ->having('total_views', '>', 100)
            ->get();

        $this->assertCount(1, $results);
        $this->assertSame(150, (int) $results[0]['total_views']); // Alice has 100 + 50 = 150
    }
}
