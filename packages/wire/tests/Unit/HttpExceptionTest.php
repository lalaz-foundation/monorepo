<?php

declare(strict_types=1);

namespace Lalaz\Wire\Tests\Unit;

use Lalaz\Wire\Tests\Common\WireUnitTestCase;
use Lalaz\Wire\HttpClient\HttpException;
use RuntimeException;
use Exception;

final class HttpExceptionTest extends WireUnitTestCase
{
    public function test_extends_runtime_exception(): void
    {
        $exception = new HttpException();
        $this->assertInstanceOf(RuntimeException::class, $exception);
    }

    public function test_is_an_exception(): void
    {
        $exception = new HttpException();
        $this->assertInstanceOf(Exception::class, $exception);
    }

    public function test_can_be_created_without_arguments(): void
    {
        $exception = new HttpException();

        $this->assertSame('', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function test_can_be_created_with_message(): void
    {
        $exception = new HttpException('Connection failed');

        $this->assertSame('Connection failed', $exception->getMessage());
    }

    public function test_can_be_created_with_message_and_code(): void
    {
        $exception = new HttpException('Timeout exceeded', 408);

        $this->assertSame('Timeout exceeded', $exception->getMessage());
        $this->assertSame(408, $exception->getCode());
    }

    public function test_can_be_created_with_previous_exception(): void
    {
        $previous = new Exception('Original error');
        $exception = new HttpException('HTTP error', 0, $previous);

        $this->assertSame($previous, $exception->getPrevious());
        $this->assertSame('Original error', $exception->getPrevious()->getMessage());
    }

    public function test_can_be_thrown(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Request failed');

        throw new HttpException('Request failed');
    }

    public function test_can_be_caught_as_runtime_exception(): void
    {
        try {
            throw new HttpException('Test');
        } catch (RuntimeException $e) {
            $this->assertInstanceOf(HttpException::class, $e);
            return;
        }

        $this->fail('Exception was not caught');
    }

    public function test_can_be_caught_as_exception(): void
    {
        try {
            throw new HttpException('Test');
        } catch (Exception $e) {
            $this->assertInstanceOf(HttpException::class, $e);
            return;
        }

        $this->fail('Exception was not caught');
    }

    public function test_has_stack_trace(): void
    {
        $exception = new HttpException('Test');

        $this->assertNotEmpty($exception->getTrace());
        $this->assertIsString($exception->getTraceAsString());
    }

    public function test_has_file_and_line_info(): void
    {
        $exception = new HttpException('Test');

        $this->assertStringContainsString('HttpExceptionTest.php', $exception->getFile());
        $this->assertGreaterThan(0, $exception->getLine());
    }

    public function test_common_http_error_messages(): void
    {
        $errors = [
            'Unable to initialize cURL.',
            'cURL error: Connection refused',
            'cURL error: Timeout was reached',
            'cURL error: SSL certificate problem',
            'cURL error: Could not resolve host',
        ];

        foreach ($errors as $message) {
            $exception = new HttpException($message);
            $this->assertSame($message, $exception->getMessage());
        }
    }

    public function test_with_http_status_code(): void
    {
        $exception = new HttpException('Not Found', 404);

        $this->assertSame(404, $exception->getCode());
        $this->assertSame('Not Found', $exception->getMessage());
    }

    public function test_exception_chain(): void
    {
        $curl = new Exception('cURL error');
        $transport = new HttpException('Transport failed', 0, $curl);
        $client = new HttpException('Request failed', 500, $transport);

        $this->assertSame('Request failed', $client->getMessage());
        $this->assertSame('Transport failed', $client->getPrevious()->getMessage());
        $this->assertSame('cURL error', $client->getPrevious()->getPrevious()->getMessage());
    }
}
