<?php declare(strict_types=1);

namespace Lalaz\Database\Tests\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

#[Group('integration')]
class CraftMigrationCommandTest extends TestCase
{
    private string $temp;
    private string $cwd;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip: Requires framework core (Console\Input, Console\Output, Console\Contracts\CommandInterface)
        if (!interface_exists(\Lalaz\Console\Contracts\CommandInterface::class)) {
            $this->markTestSkipped('Requires lalaz/framework core package');
        }

        $this->temp = sys_get_temp_dir() . "/lalaz_migrations_" . uniqid();
        mkdir($this->temp, 0777, true);
        $this->cwd = getcwd();
        chdir($this->temp);
    }

    protected function tearDown(): void
    {
        if (isset($this->cwd)) {
            chdir($this->cwd);
        }
        if (isset($this->temp) && is_dir($this->temp)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $this->temp,
                    FilesystemIterator::SKIP_DOTS,
                ),
                RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($iterator as $file) {
                $file->isDir()
                    ? @rmdir($file->getPathname())
                    : @unlink($file->getPathname());
            }
            @rmdir($this->temp);
        }
        parent::tearDown();
    }

    public function test_craft_migration_generates_a_migration_file(): void
    {
        $command = new \Lalaz\Database\Console\CraftMigrationCommand();
        $code = $command->handle(
            new \Lalaz\Console\Input(["lalaz", "craft:migration", "create_users_table"]),
            new \Lalaz\Console\Output(),
        );

        $this->assertSame(0, $code);
        $files = glob("database/migrations/*create_users_table.php") ?: [];
        $this->assertNotEmpty($files);
    }

    public function test_craft_migration_infers_table_from_add_naming(): void
    {
        $command = new \Lalaz\Database\Console\CraftMigrationCommand();
        $code = $command->handle(
            new \Lalaz\Console\Input(["lalaz", "craft:migration", "add_avatar_to_users_table"]),
            new \Lalaz\Console\Output(),
        );

        $this->assertSame(0, $code);
        $files = glob("database/migrations/*add_avatar_to_users_table.php") ?: [];
        $this->assertNotEmpty($files);
        $content = file_get_contents($files[0]);
        $this->assertStringContainsString("table('users'", $content);
    }
}
