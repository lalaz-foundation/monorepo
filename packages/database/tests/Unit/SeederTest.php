<?php declare(strict_types=1);

namespace Lalaz\Database\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Lalaz\Container\Container;
use Lalaz\Database\Seeding\Seeder;
use Lalaz\Database\Seeding\SeederRunner;

class DemoSeeder extends Seeder
{
    public bool $ran = false;

    public function run(): void
    {
        $this->ran = true;
    }
}

class NestedSeeder extends Seeder
{
    public array $called = [];

    public function run(): void
    {
        $this->called[] = "root";
        $this->call([ChildSeeder::class]);
    }
}

class ChildSeeder extends Seeder
{
    public static bool $ran = false;

    public function run(): void
    {
        self::$ran = true;
    }
}

class SeederTest extends TestCase
{
    public function test_seeder_runner_resolves_and_runs_a_specific_class(): void
    {
        $runner = new SeederRunner();
        $runner->run(DemoSeeder::class);
        $this->assertTrue(true); // success if no exception
    }

    public function test_seeder_can_call_nested_seeders(): void
    {
        ChildSeeder::$ran = false;
        $runner = new SeederRunner();
        $runner->run(NestedSeeder::class);

        $this->assertTrue(ChildSeeder::$ran);
    }
}
