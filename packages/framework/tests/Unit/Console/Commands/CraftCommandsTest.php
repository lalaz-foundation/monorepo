<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Console\Commands;

use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Lalaz\Console\Commands\CraftCommandCommand;
use Lalaz\Console\Commands\CraftControllerCommand;
use Lalaz\Console\Commands\CraftMiddlewareCommand;
use Lalaz\Console\Commands\CraftProviderCommand;
use Lalaz\Console\Commands\CraftModelCommand;
use Lalaz\Console\Commands\CraftRouteCommand;
use Lalaz\Console\Input;
use Lalaz\Console\Output;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

class CraftCommandsTest extends FrameworkUnitTestCase
{
    private string $temp;
    private string $cwd;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temp = sys_get_temp_dir() . "/lalaz_cli_" . uniqid();
        mkdir($this->temp, 0777, true);
        $this->cwd = getcwd();
        chdir($this->temp);
        mkdir("routes", 0777, true);
        file_put_contents("routes/web.php", "<?php\nreturn function () {};\n");
    }

    protected function tearDown(): void
    {
        chdir($this->cwd);
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

        parent::tearDown();
    }

    public function testcraftControllerGeneratesFile(): void
    {
        $command = new CraftControllerCommand();
        $code = $command->handle(
            new Input(["lalaz", "craft:controller", "HomeController"]),
            new Output(),
        );

        $this->assertSame(0, $code);
        $this->assertNotEmpty(glob("app/Controllers/*HomeController.php"));
    }

    public function testcraftMiddlewareGeneratesFile(): void
    {
        $command = new CraftMiddlewareCommand();
        $code = $command->handle(
            new Input([
                "lalaz",
                "craft:middleware",
                "AuthMiddleware",
            ]),
            new Output(),
        );

        $this->assertSame(0, $code);
        $this->assertNotEmpty(glob("app/Middleware/*AuthMiddleware.php"));
    }

    public function testcraftProviderGeneratesFile(): void
    {
        $command = new CraftProviderCommand();
        $code = $command->handle(
            new Input([
                "lalaz",
                "craft:provider",
                "DemoProvider",
            ]),
            new Output(),
        );

        $this->assertSame(0, $code);
        $this->assertNotEmpty(glob("app/Providers/*Provider.php"));
    }

    public function testcraftCommandGeneratesFile(): void
    {
        $command = new CraftCommandCommand();
        $code = $command->handle(
            new Input(["lalaz", "craft:command", "DemoCommand"]),
            new Output(),
        );

        $this->assertSame(0, $code);
        $this->assertNotEmpty(glob("app/Console/Commands/*DemoCommand.php"));
    }

    public function testcraftRouteAppendsToRoutesFile(): void
    {
        $command = new CraftRouteCommand();
        $code = $command->handle(
            new Input(["lalaz", "craft:route", "/status", "--method=GET"]),
            new Output(),
        );

        $this->assertSame(0, $code);
        $this->assertStringContainsString("/status", file_get_contents("routes/web.php"));
    }

    public function testcraftModelGeneratesFile(): void
    {
        $command = new CraftModelCommand();
        $code = $command->handle(
            new Input(["lalaz", "craft:model", "User"]),
            new Output(),
        );

        $this->assertSame(0, $code);
        $this->assertNotEmpty(glob("app/Models/User.php"));
    }
}
