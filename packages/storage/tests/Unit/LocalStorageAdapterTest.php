<?php declare(strict_types=1);

namespace Lalaz\Storage\Tests\Unit;

use Lalaz\Storage\Adapters\LocalStorageAdapter;
use Lalaz\Storage\Exceptions\StorageException;
use Lalaz\Storage\Tests\Common\StorageUnitTestCase;
use PHPUnit\Framework\Attributes\Test;

class LocalStorageAdapterTest extends StorageUnitTestCase
{
    private LocalStorageAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = $this->createTempDirectory();
        $this->adapter = $this->createLocalAdapter($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->cleanupTempDirectory($this->tempDir);
        parent::tearDown();
    }

    #[Test]
    public function constructor_throws_exception_without_path(): void
    {
        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('storage.path');

        new LocalStorageAdapter([]);
    }

    #[Test]
    public function constructor_accepts_valid_configuration(): void
    {
        $adapter = new LocalStorageAdapter([
            'path' => $this->tempDir,
        ]);

        $this->assertInstanceOf(LocalStorageAdapter::class, $adapter);
    }

    #[Test]
    public function put_writes_contents_to_file(): void
    {
        $result = $this->adapter->put('test.txt', 'Hello World');

        $this->assertTrue($result);
        $this->assertEquals('Hello World', file_get_contents($this->tempDir . '/test.txt'));
    }

    #[Test]
    public function get_reads_file_contents(): void
    {
        file_put_contents($this->tempDir . '/test.txt', 'Hello World');

        $contents = $this->adapter->get('test.txt');

        $this->assertEquals('Hello World', $contents);
    }

    #[Test]
    public function get_throws_exception_for_missing_file(): void
    {
        $this->expectException(StorageException::class);

        $this->adapter->get('nonexistent.txt');
    }

    #[Test]
    public function exists_returns_true_for_existing_file(): void
    {
        file_put_contents($this->tempDir . '/test.txt', 'Hello');

        $this->assertTrue($this->adapter->exists('test.txt'));
    }

    #[Test]
    public function exists_returns_false_for_missing_file(): void
    {
        $this->assertFalse($this->adapter->exists('nonexistent.txt'));
    }

    #[Test]
    public function delete_removes_file(): void
    {
        file_put_contents($this->tempDir . '/test.txt', 'Hello');

        $result = $this->adapter->delete('test.txt');

        $this->assertTrue($result);
        $this->assertFalse(file_exists($this->tempDir . '/test.txt'));
    }

    #[Test]
    public function delete_returns_false_for_missing_file(): void
    {
        $result = $this->adapter->delete('nonexistent.txt');

        $this->assertFalse($result);
    }

    #[Test]
    public function size_returns_file_size_in_bytes(): void
    {
        file_put_contents($this->tempDir . '/test.txt', 'Hello World');

        $size = $this->adapter->size('test.txt');

        $this->assertEquals(11, $size);
    }

    #[Test]
    public function lastModified_returns_timestamp(): void
    {
        $before = time();
        file_put_contents($this->tempDir . '/test.txt', 'Hello');
        $after = time();

        $mtime = $this->adapter->lastModified('test.txt');

        $this->assertGreaterThanOrEqual($before, $mtime);
        $this->assertLessThanOrEqual($after, $mtime);
    }

    #[Test]
    public function mimeType_returns_correct_type(): void
    {
        file_put_contents($this->tempDir . '/test.txt', 'Hello World');

        $mimeType = $this->adapter->mimeType('test.txt');

        $this->assertEquals('text/plain', $mimeType);
    }

    #[Test]
    public function copy_duplicates_file(): void
    {
        file_put_contents($this->tempDir . '/source.txt', 'Hello');

        $result = $this->adapter->copy('source.txt', 'dest.txt');

        $this->assertTrue($result);
        $this->assertEquals('Hello', file_get_contents($this->tempDir . '/dest.txt'));
        $this->assertTrue(file_exists($this->tempDir . '/source.txt'));
    }

    #[Test]
    public function move_relocates_file(): void
    {
        file_put_contents($this->tempDir . '/source.txt', 'Hello');

        $result = $this->adapter->move('source.txt', 'dest.txt');

        $this->assertTrue($result);
        $this->assertEquals('Hello', file_get_contents($this->tempDir . '/dest.txt'));
        $this->assertFalse(file_exists($this->tempDir . '/source.txt'));
    }

    #[Test]
    public function append_adds_to_file(): void
    {
        file_put_contents($this->tempDir . '/test.txt', 'Hello');

        $this->adapter->append('test.txt', ' World');

        $this->assertEquals('Hello World', file_get_contents($this->tempDir . '/test.txt'));
    }

    #[Test]
    public function prepend_adds_to_beginning_of_file(): void
    {
        file_put_contents($this->tempDir . '/test.txt', 'World');

        $this->adapter->prepend('test.txt', 'Hello ');

        $this->assertEquals('Hello World', file_get_contents($this->tempDir . '/test.txt'));
    }

    #[Test]
    public function upload_creates_file_with_unique_name(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'upload_');
        file_put_contents($tempFile, 'Uploaded content');

        $url = $this->adapter->upload('original.txt', $tempFile);

        $this->assertStringStartsWith('https://example.com/storage/', $url);
        $this->assertStringEndsWith('.txt', $url);

        unlink($tempFile);
    }

    #[Test]
    public function download_returns_absolute_path(): void
    {
        file_put_contents($this->tempDir . '/test.txt', 'Hello');

        $path = $this->adapter->download('test.txt');

        $this->assertEquals(realpath($this->tempDir . '/test.txt'), $path);
    }

    #[Test]
    public function getPublicUrl_generates_correct_url(): void
    {
        $url = $this->adapter->getPublicUrl('path/to/file.txt');

        $this->assertEquals('https://example.com/storage/path/to/file.txt', $url);
    }

    #[Test]
    public function makeDirectory_creates_directory(): void
    {
        $result = $this->adapter->makeDirectory('subdir');

        $this->assertTrue($result);
        $this->assertTrue(is_dir($this->tempDir . '/subdir'));
    }

    #[Test]
    public function makeDirectory_creates_nested_directories(): void
    {
        $result = $this->adapter->makeDirectory('level1/level2/level3');

        $this->assertTrue($result);
        $this->assertTrue(is_dir($this->tempDir . '/level1/level2/level3'));
    }

    #[Test]
    public function files_lists_files_in_directory(): void
    {
        file_put_contents($this->tempDir . '/file1.txt', 'a');
        file_put_contents($this->tempDir . '/file2.txt', 'b');
        mkdir($this->tempDir . '/subdir');
        file_put_contents($this->tempDir . '/subdir/file3.txt', 'c');

        $files = $this->adapter->files();

        $this->assertContains('file1.txt', $files);
        $this->assertContains('file2.txt', $files);
        $this->assertNotContains('subdir/file3.txt', $files);
    }

    #[Test]
    public function files_lists_files_recursively(): void
    {
        file_put_contents($this->tempDir . '/file1.txt', 'a');
        mkdir($this->tempDir . '/subdir');
        file_put_contents($this->tempDir . '/subdir/file2.txt', 'b');

        $files = $this->adapter->files('', true);

        $this->assertContains('file1.txt', $files);
        $this->assertContains('subdir/file2.txt', $files);
    }

    #[Test]
    public function directories_lists_directories(): void
    {
        mkdir($this->tempDir . '/dir1');
        mkdir($this->tempDir . '/dir2');
        file_put_contents($this->tempDir . '/file.txt', 'a');

        $dirs = $this->adapter->directories();

        $this->assertContains('dir1', $dirs);
        $this->assertContains('dir2', $dirs);
        $this->assertNotContains('file.txt', $dirs);
    }

    #[Test]
    public function deleteDirectory_removes_empty_directory(): void
    {
        mkdir($this->tempDir . '/empty');

        $result = $this->adapter->deleteDirectory('empty');

        $this->assertTrue($result);
        $this->assertFalse(is_dir($this->tempDir . '/empty'));
    }

    #[Test]
    public function deleteDirectory_fails_on_non_empty_directory_without_recursive(): void
    {
        mkdir($this->tempDir . '/nonempty');
        file_put_contents($this->tempDir . '/nonempty/file.txt', 'a');

        $result = $this->adapter->deleteDirectory('nonempty');

        $this->assertFalse($result);
        $this->assertTrue(is_dir($this->tempDir . '/nonempty'));
    }

    #[Test]
    public function deleteDirectory_removes_non_empty_directory_with_recursive(): void
    {
        mkdir($this->tempDir . '/nonempty/sub', 0755, true);
        file_put_contents($this->tempDir . '/nonempty/file.txt', 'a');
        file_put_contents($this->tempDir . '/nonempty/sub/file2.txt', 'b');

        $result = $this->adapter->deleteDirectory('nonempty', true);

        $this->assertTrue($result);
        $this->assertFalse(is_dir($this->tempDir . '/nonempty'));
    }

    #[Test]
    public function path_traversal_is_prevented_on_put(): void
    {
        $this->expectException(StorageException::class);

        $this->adapter->put('../outside.txt', 'content');
    }

    #[Test]
    public function path_traversal_is_prevented_on_get(): void
    {
        $this->expectException(StorageException::class);

        $this->adapter->get('../etc/passwd');
    }

    #[Test]
    public function put_creates_parent_directories_automatically(): void
    {
        $result = $this->adapter->put('deep/nested/path/file.txt', 'content');

        $this->assertTrue($result);
        $this->assertEquals('content', file_get_contents($this->tempDir . '/deep/nested/path/file.txt'));
    }
}
