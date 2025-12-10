<?php declare(strict_types=1);

namespace Lalaz\Storage\Tests\Unit;

use Lalaz\Storage\Adapters\MemoryStorageAdapter;
use Lalaz\Storage\Exceptions\StorageException;
use Lalaz\Storage\Tests\Common\StorageUnitTestCase;
use PHPUnit\Framework\Attributes\Test;

class MemoryStorageAdapterTest extends StorageUnitTestCase
{
    private MemoryStorageAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = $this->createMemoryAdapter();
    }

    #[Test]
    public function put_stores_contents_in_memory(): void
    {
        $result = $this->adapter->put('test.txt', 'Hello World');

        $this->assertTrue($result);
        $this->assertEquals('Hello World', $this->adapter->get('test.txt'));
    }

    #[Test]
    public function get_retrieves_stored_contents(): void
    {
        $this->adapter->put('test.txt', 'Hello World');

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
    public function exists_returns_true_for_stored_file(): void
    {
        $this->adapter->put('test.txt', 'Hello');

        $this->assertTrue($this->adapter->exists('test.txt'));
    }

    #[Test]
    public function exists_returns_false_for_missing_file(): void
    {
        $this->assertFalse($this->adapter->exists('nonexistent.txt'));
    }

    #[Test]
    public function delete_removes_file_from_memory(): void
    {
        $this->adapter->put('test.txt', 'Hello');

        $result = $this->adapter->delete('test.txt');

        $this->assertTrue($result);
        $this->assertFalse($this->adapter->exists('test.txt'));
    }

    #[Test]
    public function delete_returns_false_for_missing_file(): void
    {
        $result = $this->adapter->delete('nonexistent.txt');

        $this->assertFalse($result);
    }

    #[Test]
    public function size_returns_content_length(): void
    {
        $this->adapter->put('test.txt', 'Hello World');

        $size = $this->adapter->size('test.txt');

        $this->assertEquals(11, $size);
    }

    #[Test]
    public function lastModified_returns_timestamp(): void
    {
        $before = time();
        $this->adapter->put('test.txt', 'Hello');
        $after = time();

        $mtime = $this->adapter->lastModified('test.txt');

        $this->assertGreaterThanOrEqual($before, $mtime);
        $this->assertLessThanOrEqual($after, $mtime);
    }

    #[Test]
    public function copy_duplicates_file_in_memory(): void
    {
        $this->adapter->put('source.txt', 'Hello');

        $result = $this->adapter->copy('source.txt', 'dest.txt');

        $this->assertTrue($result);
        $this->assertEquals('Hello', $this->adapter->get('dest.txt'));
        $this->assertTrue($this->adapter->exists('source.txt'));
    }

    #[Test]
    public function move_relocates_file_in_memory(): void
    {
        $this->adapter->put('source.txt', 'Hello');

        $result = $this->adapter->move('source.txt', 'dest.txt');

        $this->assertTrue($result);
        $this->assertEquals('Hello', $this->adapter->get('dest.txt'));
        $this->assertFalse($this->adapter->exists('source.txt'));
    }

    #[Test]
    public function append_adds_to_existing_content(): void
    {
        $this->adapter->put('test.txt', 'Hello');

        $this->adapter->append('test.txt', ' World');

        $this->assertEquals('Hello World', $this->adapter->get('test.txt'));
    }

    #[Test]
    public function prepend_adds_to_beginning_of_content(): void
    {
        $this->adapter->put('test.txt', 'World');

        $this->adapter->prepend('test.txt', 'Hello ');

        $this->assertEquals('Hello World', $this->adapter->get('test.txt'));
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
        $this->assertTrue($this->adapter->exists('subdir'));
    }

    #[Test]
    public function files_lists_files(): void
    {
        $this->adapter->put('file1.txt', 'a');
        $this->adapter->put('file2.txt', 'b');
        $this->adapter->put('subdir/file3.txt', 'c');

        $files = $this->adapter->files();

        $this->assertContains('file1.txt', $files);
        $this->assertContains('file2.txt', $files);
        $this->assertNotContains('subdir/file3.txt', $files);
    }

    #[Test]
    public function files_lists_files_recursively(): void
    {
        $this->adapter->put('file1.txt', 'a');
        $this->adapter->put('subdir/file2.txt', 'b');

        $files = $this->adapter->files('', true);

        $this->assertContains('file1.txt', $files);
        $this->assertContains('subdir/file2.txt', $files);
    }

    #[Test]
    public function directories_lists_directories(): void
    {
        $this->adapter->makeDirectory('dir1');
        $this->adapter->makeDirectory('dir2');
        $this->adapter->put('file.txt', 'a');

        $dirs = $this->adapter->directories();

        $this->assertContains('dir1', $dirs);
        $this->assertContains('dir2', $dirs);
    }

    #[Test]
    public function deleteDirectory_removes_directory(): void
    {
        $this->adapter->makeDirectory('empty');

        $result = $this->adapter->deleteDirectory('empty');

        $this->assertTrue($result);
        $this->assertFalse($this->adapter->exists('empty'));
    }

    #[Test]
    public function deleteDirectory_recursive_removes_all_contents(): void
    {
        $this->adapter->put('dir/file1.txt', 'a');
        $this->adapter->put('dir/sub/file2.txt', 'b');

        $result = $this->adapter->deleteDirectory('dir', true);

        $this->assertTrue($result);
        $this->assertFalse($this->adapter->exists('dir/file1.txt'));
        $this->assertFalse($this->adapter->exists('dir/sub/file2.txt'));
    }

    #[Test]
    public function clear_removes_all_files_and_directories(): void
    {
        $this->adapter->put('file1.txt', 'a');
        $this->adapter->put('file2.txt', 'b');
        $this->adapter->makeDirectory('dir');

        $this->adapter->clear();

        $this->assertEmpty($this->adapter->getFiles());
        $this->assertEmpty($this->adapter->getDirectories());
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
    public function mimeType_returns_correct_type_for_text(): void
    {
        $this->adapter->put('test.txt', 'Hello World');

        $mimeType = $this->adapter->mimeType('test.txt');

        $this->assertEquals('text/plain', $mimeType);
    }

    #[Test]
    public function parent_directories_are_created_automatically(): void
    {
        $this->adapter->put('deep/nested/path/file.txt', 'content');

        $dirs = $this->adapter->directories('', true);

        $this->assertContains('deep', $dirs);
        $this->assertContains('deep/nested', $dirs);
        $this->assertContains('deep/nested/path', $dirs);
    }
}
