# Testing Guide

Strategies and utilities for testing with Lalaz Storage.

## Overview

Lalaz Storage provides excellent testing support through:

- In-memory adapter for fast, isolated tests
- Base test case classes
- Consistent interface for mocking
- No filesystem pollution

## Test Infrastructure

### Base Test Classes

The package includes base test classes in `tests/Common/`:

#### StorageUnitTestCase

Base class for unit tests:

```php
use Lalaz\Storage\Tests\Common\StorageUnitTestCase;

class MyStorageTest extends StorageUnitTestCase
{
    public function testFileStorage(): void
    {
        $adapter = $this->createMemoryAdapter();

        $adapter->put('test.txt', 'content');

        $this->assertStorageFileExists($adapter, 'test.txt');
        $this->assertStorageFileContains($adapter, 'test.txt', 'content');
    }
}
```

#### StorageIntegrationTestCase

Extended base class for integration tests:

```php
use Lalaz\Storage\Tests\Common\StorageIntegrationTestCase;

class FileUploadIntegrationTest extends StorageIntegrationTestCase
{
    public function testCompleteUploadFlow(): void
    {
        $this->configureMultiDiskManager();

        Storage::put('uploads/file.txt', 'content');

        $this->assertStorageOperationSucceeds(function () {
            return Storage::get('uploads/file.txt');
        });
    }
}
```

### Factory Methods

The base classes provide factory methods:

```php
// Create adapters
$local = $this->createLocalAdapter();
$memory = $this->createMemoryAdapter();

// Create manager
$manager = $this->createStorageManager();

// Create temporary files
$tempDir = $this->createTempDirectory();
$tempFile = $this->createTempFile('content');

// Create file in storage
$this->createStorageFile('path/file.txt', 'content');
```

### Assertions

Custom assertions for storage testing:

```php
// File existence
$this->assertStorageFileExists($storage, 'file.txt');
$this->assertStorageFileMissing($storage, 'missing.txt');

// Content assertions
$this->assertStorageFileContains($storage, 'file.txt', 'expected content');

// Size assertions
$this->assertStorageFileSize($storage, 'file.txt', 1024);

// Directory assertions
$this->assertStorageDirectoryExists('uploads');

// URL assertions
$this->assertPublicUrl($storage, 'file.txt', 'https://example.com/storage/file.txt');

// Exception assertions
$this->assertStorageExceptionThrown(function () use ($storage) {
    $storage->get('nonexistent.txt');
});
```

## Testing with Memory Adapter

### Basic Usage

The memory adapter is ideal for testing:

```php
use Lalaz\Storage\Adapters\MemoryStorageAdapter;

class FileServiceTest extends TestCase
{
    private MemoryStorageAdapter $storage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storage = new MemoryStorageAdapter([
            'public_url' => 'https://test.example.com',
        ]);
    }

    public function testFileUpload(): void
    {
        $service = new FileService($this->storage);

        $service->uploadDocument('doc.pdf', 'PDF content');

        $this->assertTrue($this->storage->exists('documents/doc.pdf'));
    }
}
```

### Advantages

1. **Speed**: No I/O operations
2. **Isolation**: Each test starts fresh
3. **No Cleanup**: Memory cleared automatically
4. **Portability**: Works anywhere PHP runs

### Complete Test Example

```php
use PHPUnit\Framework\TestCase;
use Lalaz\Storage\Adapters\MemoryStorageAdapter;
use Lalaz\Storage\Exceptions\StorageException;

class DocumentRepositoryTest extends TestCase
{
    private MemoryStorageAdapter $storage;
    private DocumentRepository $repository;

    protected function setUp(): void
    {
        $this->storage = new MemoryStorageAdapter();
        $this->repository = new DocumentRepository($this->storage);
    }

    public function testSaveDocument(): void
    {
        $document = new Document('test.pdf', 'content');

        $this->repository->save($document);

        $this->assertTrue($this->storage->exists('documents/test.pdf'));
        $this->assertEquals('content', $this->storage->get('documents/test.pdf'));
    }

    public function testFindDocument(): void
    {
        $this->storage->put('documents/test.pdf', 'content');

        $document = $this->repository->find('test.pdf');

        $this->assertEquals('content', $document->getContent());
    }

    public function testFindThrowsWhenNotFound(): void
    {
        $this->expectException(DocumentNotFoundException::class);

        $this->repository->find('nonexistent.pdf');
    }

    public function testDeleteDocument(): void
    {
        $this->storage->put('documents/test.pdf', 'content');

        $this->repository->delete('test.pdf');

        $this->assertFalse($this->storage->exists('documents/test.pdf'));
    }
}
```

## Testing with Local Adapter

For integration tests requiring filesystem:

```php
use Lalaz\Storage\Adapters\LocalStorageAdapter;

class LocalStorageIntegrationTest extends TestCase
{
    private string $tempDir;
    private LocalStorageAdapter $storage;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/storage_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);

        $this->storage = new LocalStorageAdapter([
            'path' => $this->tempDir,
            'public_url' => 'https://test.example.com',
        ]);
    }

    protected function tearDown(): void
    {
        // Clean up
        $this->deleteDirectory($this->tempDir);
    }

    public function testFileOperations(): void
    {
        $this->storage->put('test.txt', 'content');

        $this->assertFileExists($this->tempDir . '/test.txt');
        $this->assertEquals('content', file_get_contents($this->tempDir . '/test.txt'));
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file) : unlink($file);
        }

        rmdir($dir);
    }
}
```

## Testing with Storage Facade

### Setting Up

```php
use Lalaz\Storage\Storage;
use Lalaz\Storage\StorageManager;

class ControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::setManager(new StorageManager([
            'default' => 'memory',
            'disks' => [
                'memory' => ['driver' => 'memory'],
            ],
        ]));
    }

    protected function tearDown(): void
    {
        Storage::reset();
        parent::tearDown();
    }
}
```

### Testing Controllers

```php
class FileUploadControllerTest extends TestCase
{
    protected function setUp(): void
    {
        Storage::setManager(new StorageManager([
            'default' => 'memory',
            'disks' => [
                'memory' => [
                    'driver' => 'memory',
                    'public_url' => 'https://test.example.com',
                ],
            ],
        ]));
    }

    protected function tearDown(): void
    {
        Storage::reset();
    }

    public function testUploadEndpoint(): void
    {
        $controller = new FileUploadController();

        $response = $controller->upload([
            'name' => 'document.pdf',
            'content' => 'PDF content',
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertTrue(Storage::exists('uploads/document.pdf'));
    }
}
```

## Mocking Storage

### Interface Mocking

```php
use Lalaz\Storage\Contracts\StorageInterface;
use PHPUnit\Framework\MockObject\MockObject;

class ServiceTest extends TestCase
{
    public function testWithMockedStorage(): void
    {
        /** @var StorageInterface&MockObject $storage */
        $storage = $this->createMock(StorageInterface::class);

        $storage->expects($this->once())
            ->method('put')
            ->with('file.txt', 'content')
            ->willReturn(true);

        $service = new FileService($storage);
        $service->saveFile('file.txt', 'content');
    }

    public function testExceptionHandling(): void
    {
        $storage = $this->createMock(StorageInterface::class);

        $storage->method('get')
            ->willThrowException(StorageException::fileNotFound('file.txt'));

        $service = new FileService($storage);

        $this->expectException(FileNotFoundException::class);
        $service->loadFile('file.txt');
    }
}
```

## Test Organization

### Directory Structure

```
tests/
├── bootstrap.php
├── Common/
│   ├── StorageUnitTestCase.php
│   └── StorageIntegrationTestCase.php
├── Unit/
│   ├── LocalStorageAdapterTest.php
│   ├── MemoryStorageAdapterTest.php
│   ├── StorageExceptionTest.php
│   ├── StorageFacadeTest.php
│   └── StorageManagerTest.php
└── Integration/
    ├── StorageFlowIntegrationTest.php
    ├── StorageAdaptersIntegrationTest.php
    └── StorageManagerIntegrationTest.php
```

### PHPUnit Configuration

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
    </testsuites>

    <source>
        <include>
            <directory>src</directory>
        </include>
    </source>
</phpunit>
```

### Running Tests

```bash
# Run all tests
./vendor/bin/phpunit

# Run unit tests only
./vendor/bin/phpunit --testsuite Unit

# Run integration tests only
./vendor/bin/phpunit --testsuite Integration

# Run specific test file
./vendor/bin/phpunit tests/Unit/LocalStorageAdapterTest.php

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage
```

## Best Practices

### 1. Prefer Memory Adapter

Use memory adapter for most tests:

```php
// Fast and isolated
$storage = new MemoryStorageAdapter();
```

### 2. Clean Up Resources

Always clean up in tearDown:

```php
protected function tearDown(): void
{
    Storage::reset();
    $this->cleanupTempDirectory();
    parent::tearDown();
}
```

### 3. Test Edge Cases

```php
public function testEmptyFile(): void
{
    $this->storage->put('empty.txt', '');
    $this->assertEquals('', $this->storage->get('empty.txt'));
    $this->assertEquals(0, $this->storage->size('empty.txt'));
}

public function testSpecialCharactersInFilename(): void
{
    $this->storage->put('file with spaces.txt', 'content');
    $this->assertTrue($this->storage->exists('file with spaces.txt'));
}

public function testDeepNestedPaths(): void
{
    $this->storage->put('a/b/c/d/e/f/deep.txt', 'content');
    $this->assertTrue($this->storage->exists('a/b/c/d/e/f/deep.txt'));
}
```

### 4. Test Security Scenarios

```php
public function testPathTraversalBlocked(): void
{
    $this->expectException(StorageException::class);
    $this->storage->get('../../../etc/passwd');
}

public function testAbsolutePathBlocked(): void
{
    $this->expectException(StorageException::class);
    $this->storage->put('/etc/passwd', 'malicious');
}
```

### 5. Use Data Providers

```php
/**
 * @dataProvider mimeTypeProvider
 */
public function testMimeTypeDetection(string $filename, string $expected): void
{
    $this->storage->put($filename, 'content');
    $this->assertEquals($expected, $this->storage->mimeType($filename));
}

public static function mimeTypeProvider(): array
{
    return [
        ['file.txt', 'text/plain'],
        ['file.html', 'text/html'],
        ['file.json', 'application/json'],
        ['file.pdf', 'application/pdf'],
    ];
}
```

## Related Topics

- [API Reference](api-reference.md) - Complete method documentation
- [Concepts](concepts.md) - Architecture overview
