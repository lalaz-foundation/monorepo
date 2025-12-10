<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Support\Functions;

use InvalidArgumentException;
use RuntimeException;
use Exception;
use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;

class TryCatchTest extends FrameworkUnitTestCase
{
    public function test_tryCatch_returns_result_on_success(): void
    {
        [$result, $exception] = tryCatch(fn() => 'success');

        $this->assertEquals('success', $result);
        $this->assertNull($exception);
    }

    public function test_tryCatch_returns_exception_on_failure(): void
    {
        [$result, $exception] = tryCatch(fn() => throw new Exception('Test error'));

        $this->assertNull($result);
        $this->assertInstanceOf(Exception::class, $exception);
        $this->assertEquals('Test error', $exception->getMessage());
    }

    public function test_tryCatch_with_typed_exception_handler(): void
    {
        $handlerCalled = false;

        [$result, $exception] = tryCatch(
            fn() => throw new InvalidArgumentException('Invalid'),
            [
                InvalidArgumentException::class => function ($e) use (&$handlerCalled) {
                    $handlerCalled = true;
                    return 'handled';
                },
            ]
        );

        $this->assertTrue($handlerCalled);
        $this->assertEquals('handled', $result);
        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
    }

    public function test_tryCatch_typed_handler_matches_specific_type(): void
    {
        $invalidHandler = false;
        $runtimeHandler = false;

        [$result, $exception] = tryCatch(
            fn() => throw new RuntimeException('Runtime error'),
            [
                InvalidArgumentException::class => function () use (&$invalidHandler) {
                    $invalidHandler = true;
                    return 'invalid';
                },
                RuntimeException::class => function () use (&$runtimeHandler) {
                    $runtimeHandler = true;
                    return 'runtime';
                },
            ]
        );

        $this->assertFalse($invalidHandler);
        $this->assertTrue($runtimeHandler);
        $this->assertEquals('runtime', $result);
    }

    public function test_tryCatch_falls_back_to_default_handler(): void
    {
        $defaultCalled = false;

        [$result, $exception] = tryCatch(
            fn() => throw new RuntimeException('Runtime'),
            [
                InvalidArgumentException::class => fn() => 'invalid',
            ],
            function ($e) use (&$defaultCalled) {
                $defaultCalled = true;
                return 'default';
            }
        );

        $this->assertTrue($defaultCalled);
        $this->assertEquals('default', $result);
    }

    public function test_tryCatch_rethrows_when_enabled(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Rethrow me');

        tryCatch(
            fn() => throw new RuntimeException('Rethrow me'),
            [],
            null,
            false,
            true // rethrow
        );
    }

    public function test_tryCatch_does_not_rethrow_by_default(): void
    {
        [$result, $exception] = tryCatch(
            fn() => throw new RuntimeException('No rethrow')
        );

        $this->assertNull($result);
        $this->assertInstanceOf(RuntimeException::class, $exception);
    }

    public function test_tryOr_returns_result_on_success(): void
    {
        $result = tryOr(fn() => 'success', 'default');
        $this->assertEquals('success', $result);
    }

    public function test_tryOr_returns_default_on_failure(): void
    {
        $result = tryOr(fn() => throw new Exception('Error'), 'default');
        $this->assertEquals('default', $result);
    }

    public function test_tryOr_with_callable_default(): void
    {
        $result = tryOr(
            fn() => throw new Exception('Error'),
            fn($e) => "Handled: {$e->getMessage()}"
        );
        $this->assertEquals('Handled: Error', $result);
    }

    public function test_tryOr_with_null_default(): void
    {
        $result = tryOr(fn() => throw new Exception('Error'), null);
        $this->assertNull($result);
    }

    public function test_tryOnce_returns_result_on_success(): void
    {
        $result = tryOnce(fn() => 'success');
        $this->assertEquals('success', $result);
    }

    public function test_tryOnce_returns_null_on_failure(): void
    {
        $result = tryOnce(fn() => throw new Exception('Error'));
        $this->assertNull($result);
    }

    public function testrescueReturnsResultOnSuccess(): void
    {
        $result = rescue(fn() => 'success', 'fallback', false);
        $this->assertEquals('success', $result);
    }

    public function testrescueReturnsFallbackOnFailure(): void
    {
        $result = rescue(fn() => throw new Exception('Error'), 'fallback', false);
        $this->assertEquals('fallback', $result);
    }

    public function test_throwIf_throws_when_condition_true(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Condition met');

        throwIf(true, new RuntimeException('Condition met'));
    }

    public function test_throwIf_does_not_throw_when_condition_false(): void
    {
        throwIf(false, new RuntimeException('Should not throw'));
        $this->assertTrue(true);
    }

    public function test_throwUnless_throws_when_condition_false(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Condition not met');

        throwUnless(false, new RuntimeException('Condition not met'));
    }

    public function test_throwUnless_does_not_throw_when_condition_true(): void
    {
        throwUnless(true, new RuntimeException('Should not throw'));
        $this->assertTrue(true);
    }

    public function test_tryCatch_handler_receives_exception(): void
    {
        $receivedException = null;

        tryCatch(
            fn() => throw new InvalidArgumentException('Test'),
            [
                InvalidArgumentException::class => function ($e) use (&$receivedException) {
                    $receivedException = $e;
                    return null;
                },
            ]
        );

        $this->assertNotNull($receivedException);
        $this->assertEquals('Test', $receivedException->getMessage());
    }

    public function test_tryCatch_with_complex_return(): void
    {
        $data = ['user' => ['name' => 'John']];

        [$result, $exception] = tryCatch(fn() => $data);

        $this->assertEquals($data, $result);
        $this->assertNull($exception);
    }

    public function test_tryCatch_handles_nested_exceptions(): void
    {
        $innerCalled = false;

        [$result, $exception] = tryCatch(
            function () {
                try {
                    throw new InvalidArgumentException('Inner');
                } catch (InvalidArgumentException $e) {
                    throw new RuntimeException('Outer', 0, $e);
                }
            },
            [
                RuntimeException::class => function ($e) use (&$innerCalled) {
                    $innerCalled = true;
                    return $e->getPrevious()?->getMessage();
                },
            ]
        );

        $this->assertTrue($innerCalled);
        $this->assertEquals('Inner', $result);
    }

    public function test_tryCatch_with_no_handlers_and_no_default(): void
    {
        [$result, $exception] = tryCatch(
            fn() => throw new RuntimeException('Unhandled')
        );

        $this->assertNull($result);
        $this->assertInstanceOf(RuntimeException::class, $exception);
    }
}
