<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Exceptions;

use Lalaz\Exceptions\HttpException;
use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(HttpException::class)]
/**
 * Tests for the HttpException class.
 */
final class HttpExceptionTest extends FrameworkUnitTestCase
{
    public function testcreatesHttpExceptionWithHelpers(): void
    {
        $exception = HttpException::notFound('Missing resource', ['resource' => 'user']);

        $this->assertSame(404, $exception->getStatusCode());
        $this->assertSame([], $exception->getHeaders());
        $this->assertSame(['resource' => 'user'], $exception->getContext());
    }

    public function testmergesHeadersAndContextFluently(): void
    {
        $exception = HttpException::badRequest('Invalid input')
            ->withHeaders(['X-RateLimit' => '10'])
            ->withContext(['field' => 'email']);

        $this->assertSame(['X-RateLimit' => '10'], $exception->getHeaders());
        $this->assertSame(['field' => 'email'], $exception->getContext());
    }
}
