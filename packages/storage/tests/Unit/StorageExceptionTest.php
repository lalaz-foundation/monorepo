<?php declare(strict_types=1);

namespace Lalaz\Storage\Tests\Unit;

use Lalaz\Storage\Exceptions\StorageException;
use Lalaz\Storage\Tests\Common\StorageUnitTestCase;
use PHPUnit\Framework\Attributes\Test;

class StorageExceptionTest extends StorageUnitTestCase
{
    #[Test]
    public function missingConfiguration_creates_correct_exception(): void
    {
        $exception = StorageException::missingConfiguration('storage.path');

        $this->assertInstanceOf(StorageException::class, $exception);
        $this->assertStringContainsString('storage.path', $exception->getMessage());
    }

    #[Test]
    public function invalidPath_creates_exception_with_reason(): void
    {
        $exception = StorageException::invalidPath('/some/path', 'file not found');

        $this->assertStringContainsString('/some/path', $exception->getMessage());
        $this->assertStringContainsString('file not found', $exception->getMessage());
    }

    #[Test]
    public function invalidPath_creates_exception_without_reason(): void
    {
        $exception = StorageException::invalidPath('/some/path');

        $this->assertStringContainsString('/some/path', $exception->getMessage());
    }

    #[Test]
    public function fileNotFound_creates_correct_exception(): void
    {
        $exception = StorageException::fileNotFound('test.txt');

        $this->assertStringContainsString('test.txt', $exception->getMessage());
        $this->assertStringContainsString('not found', $exception->getMessage());
    }

    #[Test]
    public function directoryNotFound_creates_correct_exception(): void
    {
        $exception = StorageException::directoryNotFound('/path/to/dir');

        $this->assertStringContainsString('/path/to/dir', $exception->getMessage());
        $this->assertStringContainsString('Directory', $exception->getMessage());
    }

    #[Test]
    public function pathTraversal_creates_correct_exception(): void
    {
        $exception = StorageException::pathTraversal('../etc/passwd');

        $this->assertStringContainsString('../etc/passwd', $exception->getMessage());
        $this->assertStringContainsString('traversal', $exception->getMessage());
    }

    #[Test]
    public function uploadFailed_creates_exception_with_reason(): void
    {
        $exception = StorageException::uploadFailed('/tmp/file', '/storage/file', 'permission denied');

        $this->assertStringContainsString('/tmp/file', $exception->getMessage());
        $this->assertStringContainsString('/storage/file', $exception->getMessage());
        $this->assertStringContainsString('permission denied', $exception->getMessage());
    }

    #[Test]
    public function uploadFailed_creates_exception_without_reason(): void
    {
        $exception = StorageException::uploadFailed('/tmp/file', '/storage/file');

        $this->assertStringContainsString('/tmp/file', $exception->getMessage());
        $this->assertStringContainsString('/storage/file', $exception->getMessage());
    }

    #[Test]
    public function writeFailed_creates_correct_exception(): void
    {
        $exception = StorageException::writeFailed('/path/to/file');

        $this->assertStringContainsString('/path/to/file', $exception->getMessage());
        $this->assertStringContainsString('write', $exception->getMessage());
    }

    #[Test]
    public function readFailed_creates_correct_exception(): void
    {
        $exception = StorageException::readFailed('/path/to/file', 'file locked');

        $this->assertStringContainsString('/path/to/file', $exception->getMessage());
        $this->assertStringContainsString('file locked', $exception->getMessage());
    }

    #[Test]
    public function deleteFailed_creates_correct_exception(): void
    {
        $exception = StorageException::deleteFailed('/path/to/file');

        $this->assertStringContainsString('/path/to/file', $exception->getMessage());
        $this->assertStringContainsString('delete', $exception->getMessage());
    }

    #[Test]
    public function copyFailed_creates_correct_exception(): void
    {
        $exception = StorageException::copyFailed('/source', '/dest', 'disk full');

        $this->assertStringContainsString('/source', $exception->getMessage());
        $this->assertStringContainsString('/dest', $exception->getMessage());
        $this->assertStringContainsString('disk full', $exception->getMessage());
    }

    #[Test]
    public function moveFailed_creates_correct_exception(): void
    {
        $exception = StorageException::moveFailed('/source', '/dest');

        $this->assertStringContainsString('/source', $exception->getMessage());
        $this->assertStringContainsString('/dest', $exception->getMessage());
        $this->assertStringContainsString('move', $exception->getMessage());
    }

    #[Test]
    public function unknownDriver_creates_correct_exception(): void
    {
        $exception = StorageException::unknownDriver('s3');

        $this->assertStringContainsString('s3', $exception->getMessage());
        $this->assertStringContainsString('Unknown', $exception->getMessage());
    }

    #[Test]
    public function exception_extends_RuntimeException(): void
    {
        $exception = StorageException::fileNotFound('test.txt');

        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }
}
