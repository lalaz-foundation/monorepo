<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Console\Commands;

use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Lalaz\Config\Config;
use Lalaz\Console\Commands\ConfigCacheClearCommand;
use Lalaz\Console\Commands\ConfigCacheCommand;
use Lalaz\Console\Commands\ConfigInspectCommand;
use Lalaz\Console\Input;
use Lalaz\Console\Output;
use Lalaz\Runtime\Http\HttpApplication;

class ConfigCommandOutput extends Output
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

class ConfigCommandsTest extends FrameworkUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::clearCache();
    }

    private function recursiveRemoveDirectory(string $dir): void
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
                $this->recursiveRemoveDirectory($path);
                continue;
            }

            @unlink($path);
        }

        @rmdir($dir);
    }

    public function testconfigCacheBuildsCacheFileAndConfigCacheClearRemovesIt(): void
    {
        $base = sys_get_temp_dir() .
            "/lalaz_config_cli_" .
            bin2hex(random_bytes(5));
        @mkdir($base . "/config", 0777, true);
        @mkdir($base . "/storage/cache", 0777, true);
        file_put_contents($base . "/.env", "APP_NAME=Lalaz\nCONFIG_CACHE_ENABLED=true\n");
        file_put_contents(
            $base . "/config/app.php",
            "<?php return ['name' => 'Lalaz'];",
        );

        $app = new HttpApplication();
        $app->setBasePath($base);

        $cacheCommand = new ConfigCacheCommand($app);
        $output = new ConfigCommandOutput();
        $result = $cacheCommand->handle(
            new Input(["lalaz", "config:cache"]),
            $output,
        );

        $cacheFile = $base . "/storage/cache/config.php";

        $this->assertSame(0, $result);
        $this->assertTrue(is_file($cacheFile));
        $payload = require $cacheFile;
        $this->assertSame("Lalaz", $payload["config"]["app"]["name"] ?? null);

        $clearCommand = new ConfigCacheClearCommand($app);
        $clearCommand->handle(
            new Input(["lalaz", "config:cache:clear"]),
            new ConfigCommandOutput(),
        );

        $this->assertFalse(is_file($cacheFile));

        $this->recursiveRemoveDirectory($base);
    }

    public function testconfigInspectOutputsResolvedValuesAndErrorsOnMissingKeys(): void
    {
        Config::setConfig("app", [
            "name" => "Lalaz",
            "debug" => true,
        ]);
        Config::set("APP_ENV", "testing");

        $command = new ConfigInspectCommand();
        $output = new ConfigCommandOutput();
        $result = $command->handle(
            new Input(["lalaz", "config:inspect", "app.name"]),
            $output,
        );

        $this->assertSame(0, $result);
        $this->assertContains("app.name = Lalaz", $output->lines);

        $missing = new ConfigCommandOutput();
        $missingResult = $command->handle(
            new Input(["lalaz", "config:inspect", "missing.key"]),
            $missing,
        );

        $this->assertSame(1, $missingResult);
        $this->assertContains(
            "Configuration key 'missing.key' not found.",
            $missing->lines
        );
    }
}
