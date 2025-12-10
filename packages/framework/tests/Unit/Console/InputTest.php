<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Console;

use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Lalaz\Console\Input;

class InputTest extends FrameworkUnitTestCase
{
    public function testparsesCommandArgumentsAndLongOptions(): void
    {
        $input = new Input([
            'lalaz',
            'craft:command',
            'App\\Console\\Foo',
            '--queue=high',
            '--force',
        ]);

        $this->assertSame('craft:command', $input->command());
        $this->assertSame('App\\Console\\Foo', $input->argument(0));
        $this->assertSame('high', $input->option('queue'));
        $this->assertTrue($input->hasFlag('force'));
    }

    public function testparsesShortOptionsWithValues(): void
    {
        $input = new Input([
            'lalaz',
            'jobs:run',
            '-q',
            'emails',
            '100',
        ]);

        $this->assertSame('jobs:run', $input->command());
        $this->assertSame('emails', $input->option('q'));
        $this->assertSame('100', $input->argument(0));
    }
}
