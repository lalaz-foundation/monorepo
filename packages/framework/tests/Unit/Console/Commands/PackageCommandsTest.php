<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Console\Commands;

use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Lalaz\Console\Commands\PackageAddCommand;
use Lalaz\Console\Commands\PackageInfoCommand;
use Lalaz\Console\Commands\PackageListCommand;
use Lalaz\Console\Commands\PackageRemoveCommand;
use Lalaz\Console\Input;
use Lalaz\Console\Output;
use Lalaz\Packages\PackageManager;
use Lalaz\Runtime\Http\HttpApplication;

class PackageCommandOutput extends Output
{
    public array $lines = [];

    public function writeln(string $message = ""): void
    {
        $this->lines[] = $message;
    }

    public function error(string $message): void
    {
        $this->lines[] = $message;
    }
}

class PackageCommandsTest extends FrameworkUnitTestCase
{
    private function createTempDir(): string
    {
        $dir = sys_get_temp_dir() . "/lalaz_pkg_" . bin2hex(random_bytes(5));
        mkdir($dir, 0777, true);
        return $dir;
    }

    private function cleanupDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === "." || $item === "..") {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->cleanupDir($path);
                continue;
            }

            @unlink($path);
        }

        @rmdir($dir);
    }

    public function testpackageAddValidatesArgumentsAndForwardsDevFlag(): void
    {
        $app = new HttpApplication();
        $base = $this->createTempDir();
        mkdir($base . "/vendor/vendor/demo", 0777, true);

        $calls = [];
        $manager = new PackageManager(
            $base,
            function (
                string $action,
                string $package,
                bool $dev,
            ) use (&$calls): array {
                $calls[] = [$action, $package, $dev];
                return ["success" => true, "output" => ""];
            },
        );

        $command = new PackageAddCommand($app, $manager);
        $output = new PackageCommandOutput();
        $result = $command->handle(new Input(["lalaz", "package:add"]), $output);

        $this->assertSame(1, $result);
        $this->assertContains("Package name is required (vendor/package).", $output->lines);

        $successOutput = new PackageCommandOutput();
        $command->handle(
            new Input(["lalaz", "package:add", "vendor/demo", "--dev"]),
            $successOutput,
        );

        $this->assertContains(["require", "vendor/demo", true], $calls);
        $this->cleanupDir($base);
    }

    public function testpackageRemovePassesPurgeFlagAndPrintsMessages(): void
    {
        $app = new HttpApplication();
        $base = $this->createTempDir();
        mkdir($base . "/vendor/vendor/demo", 0777, true);
        file_put_contents(
            $base . "/vendor/vendor/demo/lalaz.json",
            json_encode(["name" => "vendor/demo"]),
        );

        $calls = [];
        $manager = new PackageManager(
            $base,
            function (
                string $action,
                string $package,
            ) use (&$calls): array {
                $calls[] = [$action, $package];
                return ["success" => true, "output" => ""];
            },
        );

        $command = new PackageRemoveCommand($app, $manager);
        $output = new PackageCommandOutput();
        $command->handle(
            new Input(["lalaz", "package:remove", "vendor/demo", "--purge"]),
            $output,
        );

        $this->assertContains(["remove", "vendor/demo"], $calls);
        $this->cleanupDir($base);
    }

    public function testpackageListOutputsInstalledManifests(): void
    {
        $app = new HttpApplication();
        $base = $this->createTempDir();
        mkdir($base . "/vendor/acme/demo", 0777, true);
        file_put_contents(
            $base . "/vendor/acme/demo/lalaz.json",
            json_encode(["name" => "acme/demo"]),
        );

        $manager = new PackageManager($base, fn() => ["success" => true, "output" => ""]);
        $command = new PackageListCommand($app, $manager);
        $output = new PackageCommandOutput();
        $command->handle(new Input(["lalaz", "package:list"]), $output);

        $this->assertStringContainsString("acme/demo", implode(" ", $output->lines));

        $this->cleanupDir($base);
    }

    public function testpackageInfoShowsManifestDetails(): void
    {
        $app = new HttpApplication();
        $base = $this->createTempDir();
        mkdir($base . "/vendor/acme/demo", 0777, true);
        file_put_contents(
            $base . "/vendor/acme/demo/lalaz.json",
            json_encode([
                "name" => "acme/demo",
                "provider" => "Acme\\Demo\\Provider",
                "install" => [
                    "env" => ["DEMO_TOKEN"],
                ],
                "post_install" => [
                    "message" => "All good",
                ],
            ]),
        );

        $manager = new PackageManager($base, fn() => ["success" => true, "output" => ""]);
        $command = new PackageInfoCommand($app, $manager);
        $output = new PackageCommandOutput();
        $command->handle(
            new Input(["lalaz", "package:info", "acme/demo"]),
            $output,
        );

        $this->assertStringContainsString("Provider", implode("\n", $output->lines));

        $this->cleanupDir($base);
    }
}
