<?php declare(strict_types=1);

namespace Lalaz\Cache\Tests\Unit;

use Lalaz\Cache\Tests\Common\CacheUnitTestCase;
use Lalaz\Cache\CacheException;
use RuntimeException;
use Exception;

class CacheExceptionTest extends CacheUnitTestCase
{
    // =========================================================================
    // Inheritance
    // =========================================================================

    public function test_extends_runtime_exception(): void
    {
        $exception = new CacheException();
        $this->assertInstanceOf(RuntimeException::class, $exception);
    }

    public function test_is_an_exception(): void
    {
        $exception = new CacheException();
        $this->assertInstanceOf(Exception::class, $exception);
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function test_can_be_created_without_arguments(): void
    {
        $exception = new CacheException();

        $this->assertSame('', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function test_can_be_created_with_message(): void
    {
        $exception = new CacheException('Cache error occurred');

        $this->assertSame('Cache error occurred', $exception->getMessage());
    }

    public function test_can_be_created_with_message_and_code(): void
    {
        $exception = new CacheException('Cache error occurred', 500);

        $this->assertSame('Cache error occurred', $exception->getMessage());
        $this->assertSame(500, $exception->getCode());
    }

    public function test_can_be_created_with_previous_exception(): void
    {
        $previous = new RuntimeException('Previous error');
        $exception = new CacheException('Cache error', 0, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    // =========================================================================
    // Throwable
    // =========================================================================

    public function test_can_be_thrown(): void
    {
        $this->expectException(CacheException::class);
        $this->expectExceptionMessage('Test cache exception');

        throw new CacheException('Test cache exception');
    }

    public function test_can_be_caught_as_runtime_exception(): void
    {
        $caught = false;

        try {
            throw new CacheException('Cache error');
        } catch (RuntimeException $e) {
            $caught = true;
            $this->assertInstanceOf(CacheException::class, $e);
        }

        $this->assertTrue($caught);
    }

    public function test_can_be_caught_as_exception(): void
    {
        $caught = false;

        try {
            throw new CacheException('Cache error');
        } catch (Exception $e) {
            $caught = true;
            $this->assertInstanceOf(CacheException::class, $e);
        }

        $this->assertTrue($caught);
    }

    // =========================================================================
    // Stack Trace
    // =========================================================================

    public function test_has_stack_trace(): void
    {
        $exception = new CacheException('Cache error');

        $this->assertNotEmpty($exception->getTrace());
        $this->assertNotEmpty($exception->getTraceAsString());
    }

    public function test_has_file_and_line_info(): void
    {
        $exception = new CacheException('Cache error');

        $this->assertSame(__FILE__, $exception->getFile());
        $this->assertIsInt($exception->getLine());
    }
}
