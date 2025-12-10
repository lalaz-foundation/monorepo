<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Console;

use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Lalaz\Console\Contracts\CommandInterface;
use Lalaz\Console\Input;
use Lalaz\Console\Output;
use Lalaz\Console\Registry;
use Lalaz\Container\Container;
use Lalaz\Container\ServiceProvider;

class SampleConsoleCommand implements CommandInterface
{
    public function name(): string
    {
        return 'sample:run';
    }

    public function description(): string
    {
        return 'Sample command';
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

class SampleConsoleProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->commands(SampleConsoleCommand::class);
    }
}

class ServiceProviderConsoleCommandTest extends FrameworkUnitTestCase
{
    public function testregistersCommandsWhenRegistryBound(): void
    {
        $container = new Container();
        $registry = new Registry($container);
        $container->instance(Registry::class, $registry);

        $provider = new SampleConsoleProvider($container);
        $provider->register();

        $resolved = $registry->resolve('sample:run');
        $this->assertInstanceOf(SampleConsoleCommand::class, $resolved);
    }
}
