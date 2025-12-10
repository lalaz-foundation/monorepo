<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Console\Commands;

use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Lalaz\Config\Config;
use Lalaz\Console\Commands\RoutesListCommand;
use Lalaz\Console\Commands\RouteCacheCommand;
use Lalaz\Console\Commands\RouteCacheClearCommand;
use Lalaz\Console\Input;
use Lalaz\Console\Output;
use Lalaz\Runtime\Http\HttpApplication;
use Lalaz\Web\Http\Response;

class SpyOutput extends Output
{
    public array $lines = [];

    public function writeln(string $message = ""): void
    {
        $this->lines[] = $message;
    }
}

class RoutesListTestController
{
    public function store(): void {}
}

class RouteCommandsTest extends FrameworkUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::setConfig("router", []);
    }

    public function testroutesListPrintsRegisteredRoutes(): void
    {
        $app = new HttpApplication();
        $app->get("/cli", function (Response $response): void {
            $response->json(["ok" => true]);
        });

        $command = new RoutesListCommand($app);
        $output = new SpyOutput();
        $command->handle(new Input(["lalaz", "routes:list"]), $output);

        $this->assertStringContainsString("/cli", implode(PHP_EOL, $output->lines));
    }

    public function testrouteCacheGeneratesCacheFile(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), "lalaz_routes_");
        $this->assertNotFalse($tmp);
        @unlink($tmp);
        Config::setConfig("router", [
            "cache" => [
                "enabled" => true,
                "file" => $tmp,
            ],
        ]);

        $app = new HttpApplication();
        $app->get(
            "/cached",
            fn(Response $response) => $response->json(["ok" => true]),
        );

        $command = new RouteCacheCommand($app);
        $output = new SpyOutput();
        $result = $command->handle(
            new Input(["lalaz", "route:cache"]),
            $output,
        );

        $this->assertSame(0, $result);
        $this->assertTrue(is_file($tmp));

        $clear = new RouteCacheClearCommand($app);
        $clear->handle(
            new Input(["lalaz", "route:cache:clear"]),
            new SpyOutput(),
        );
        $this->assertFalse(is_file($tmp));
    }

    public function testroutesListSupportsFiltersAndAlternativeFormats(): void
    {
        $app = new HttpApplication();
        $app->get("/cli", fn() => null);
        $controller = new RoutesListTestController();
        $app->post("/submit", [$controller, "store"], ["auth", fn() => null]);

        $command = new RoutesListCommand($app);
        $output = new SpyOutput();

        $command->handle(
            new Input([
                "lalaz",
                "routes:list",
                "--method=POST",
                "--path=/sub",
                "--controller=RoutesListTestController",
                "--middleware=auth",
                "--format=json",
            ]),
            $output,
        );

        $payload = implode(PHP_EOL, $output->lines);
        $data = json_decode($payload, true);

        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertSame("/submit", $data[0]["path"] ?? null);
        $this->assertStringContainsString(
            "RoutesListTestController@store",
            $data[0]["handler"] ?? ""
        );
        $this->assertContains("auth", $data[0]["middlewares"] ?? []);
    }

    public function testroutesListNotifiesWhenNoRoutesMatchFilters(): void
    {
        $app = new HttpApplication();
        $app->get("/cli", fn() => null);

        $command = new RoutesListCommand($app);
        $output = new SpyOutput();

        $command->handle(
            new Input(["lalaz", "routes:list", "--method=POST"]),
            $output,
        );

        $this->assertContains(
            "No routes matched the provided filters.",
            $output->lines
        );
    }
}
