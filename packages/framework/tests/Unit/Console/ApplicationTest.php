<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Console;

use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Lalaz\Console\Application;
use Lalaz\Console\Contracts\CommandInterface;
use Lalaz\Console\Input;
use Lalaz\Console\Output;
use Lalaz\Console\Registry;
use Lalaz\Container\Container;

class StubCommand implements CommandInterface
{
    public array $called = [];

    public function name(): string
    {
        return "stub:run";
    }

    public function description(): string
    {
        return "Stub command";
    }

    public function arguments(): array
    {
        return [];
    }

    public function options(): array
    {
        return [];
    }

    public function handle(Input $input, Output $output): int
    {
        $this->called[] = $input->argument(0);
        return 0;
    }
}

class ApplicationTest extends FrameworkUnitTestCase
{
    public function testinvokesRegisteredCommand(): void
    {
        $registry = new Registry();
        $command = new StubCommand();
        $registry->add($command);
        $app = new Application($registry, new Output());

        $code = $app->run(["lalaz", "stub:run", "foo"]);

        $this->assertSame(0, $code);
        $this->assertSame(["foo"], $command->called);
    }

    public function testshowsHelpWhenCommandMissing(): void
    {
        $registry = new Registry();
        $app = new Application($registry, new Output());

        $code = $app->run(["lalaz", "missing"]);

        $this->assertSame(1, $code);
    }

    public function testwrapsCommandExecutionInsideAContainerScope(): void
    {
        $container = new class extends Container {
            public int $began = 0;
            public int $ended = 0;

            public function beginScope(): void
            {
                $this->began++;
                parent::beginScope();
            }

            public function endScope(): void
            {
                $this->ended++;
                parent::endScope();
            }
        };

        $registry = new Registry($container);
        $command = new StubCommand();
        $registry->add($command);
        $app = new Application($registry, new Output());

        $app->run(["lalaz", "stub:run", "foo"]);

        $this->assertSame(1, $container->began);
        $this->assertSame(1, $container->ended);
    }
}
