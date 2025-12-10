<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Support;

use Lalaz\Config\Config;
use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Config::class)]
/**
 * Tests for helper functions.
 */
final class HelpersTest extends FrameworkUnitTestCase
{
    public function testexposesEnvAndConfigHelperFunctions(): void
    {
        Config::set('APP_NAME', 'Lalaz');
        Config::setConfig('app', ['timezone' => 'UTC']);

        $this->assertSame('Lalaz', env('APP_NAME'));
        $this->assertSame('fallback', env('MISSING_KEY', 'fallback'));
        $this->assertSame('UTC', config('app.timezone'));
    }
}
