<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Support\Functions;

use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;

class LoggingTest extends FrameworkUnitTestCase
{
    public function testemergencyFunctionExists(): void
    {
        $this->assertTrue(function_exists('emergency'));
    }

    public function testalertFunctionExists(): void
    {
        $this->assertTrue(function_exists('alert'));
    }

    public function testcriticalFunctionExists(): void
    {
        $this->assertTrue(function_exists('critical'));
    }

    public function testerrorFunctionExists(): void
    {
        $this->assertTrue(function_exists('error'));
    }

    public function testwarningFunctionExists(): void
    {
        $this->assertTrue(function_exists('warning'));
    }

    public function testnoticeFunctionExists(): void
    {
        $this->assertTrue(function_exists('notice'));
    }

    public function testinfoFunctionExists(): void
    {
        $this->assertTrue(function_exists('info'));
    }

    public function testdebugFunctionExists(): void
    {
        $this->assertTrue(function_exists('debug'));
    }

    /**
     * Note: To fully test logging functions, you would need to:
     * 1. Mock the Log facade
     * 2. Or configure a test logger
     * 3. Or use integration tests with the full framework
     *
     * These unit tests just verify the functions exist and are callable.
     * Full integration tests would be in a separate test suite.
     */
}
