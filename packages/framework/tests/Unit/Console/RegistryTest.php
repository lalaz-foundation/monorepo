<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Console;

use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Lalaz\Console\Contracts\CommandInterface;
use Lalaz\Console\Registry;
use Lalaz\Console\Input;
use Lalaz\Console\Output;

class DummyCommand implements CommandInterface
{
    public function name(): string
    {
        return 'dummy';
    }

    public function description(): string
    {
        return 'Dummy command';
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
        return 0;
    }
}

class RegistryTest extends FrameworkUnitTestCase
{
    public function testregistersAndResolvesCommands(): void
    {
        $registry = new Registry();
        $command = new DummyCommand();
        $registry->add($command);

        $this->assertSame($command, $registry->resolve('dummy'));
        $this->assertNull($registry->resolve('missing'));
    }
}
