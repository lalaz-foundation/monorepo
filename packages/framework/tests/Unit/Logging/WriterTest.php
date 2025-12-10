<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Logging;

use Lalaz\Logging\Writer\NullWriter;
use Lalaz\Logging\Writer\FileWriter;
use Lalaz\Logging\Writer\ConsoleWriter;
use Lalaz\Logging\Contracts\WriterInterface;
use Lalaz\Logging\Formatter\TextFormatter;
use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(NullWriter::class)]
/**
 * Tests for the Writer classes.
 */
final class WriterTest extends FrameworkUnitTestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/lalaz_log_test_' . uniqid();
        @mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $files = glob($this->tempDir . '/*');
        foreach ($files as $file) {
            @unlink($file);
        }
        @rmdir($this->tempDir);
    }

    // ============================================
    // NullWriter Tests
    // ============================================

    public function testnullWriterDiscardsMessagesWhenNotCapturing(): void
    {
        $writer = new NullWriter();
        $writer->write('Test message');
        $writer->write('Another message');

        $this->assertSame(0, $writer->count());
        $this->assertSame([], $writer->getMessages());
    }

    public function testnullWriterCapturesMessagesWhenEnabled(): void
    {
        $writer = new NullWriter(capture: true);
        $writer->write('First message');
        $writer->write('Second message');

        $this->assertSame(2, $writer->count());
        $this->assertSame(['First message', 'Second message'], $writer->getMessages());
    }

    public function testnullWriterReturnsLastMessage(): void
    {
        $writer = new NullWriter(capture: true);
        $writer->write('First');
        $writer->write('Last');

        $this->assertSame('Last', $writer->getLastMessage());
    }

    public function testnullWriterReturnsNullForLastMessageWhenEmpty(): void
    {
        $writer = new NullWriter(capture: true);

        $this->assertNull($writer->getLastMessage());
    }

    public function testnullWriterClearsCapturedMessages(): void
    {
        $writer = new NullWriter(capture: true);
        $writer->write('Message');
        $writer->clear();

        $this->assertSame(0, $writer->count());
    }

    public function testnullWriterImplementsWriterInterface(): void
    {
        $writer = new NullWriter();

        $this->assertInstanceOf(WriterInterface::class, $writer);
    }

    // ============================================
    // FileWriter Tests
    // ============================================

    public function testfileWriterCreatesLogFileOnWrite(): void
    {
        $path = $this->tempDir . '/test.log';
        $writer = new FileWriter($path);
        $writer->write('Test message');

        $this->assertFileExists($path);
        $this->assertStringContainsString('Test message', file_get_contents($path));
    }

    public function testfileWriterAppendsToExistingFile(): void
    {
        $path = $this->tempDir . '/test.log';
        $writer = new FileWriter($path);
        $writer->write('First');
        $writer->write('Second');

        $content = file_get_contents($path);
        $this->assertStringContainsString('First', $content);
        $this->assertStringContainsString('Second', $content);
    }

    public function testfileWriterCreatesDirectoryIfNotExists(): void
    {
        $path = $this->tempDir . '/subdir/test.log';
        $writer = new FileWriter($path);
        $writer->write('Test');

        $this->assertFileExists($path);
    }

    public function testfileWriterRotatesWhenMaxSizeExceeded(): void
    {
        $path = $this->tempDir . '/rotate.log';
        // 50 bytes max, 3 files
        $writer = new FileWriter($path, maxSize: 50, maxFiles: 3);

        for ($i = 0; $i < 10; $i++) {
            $writer->write(str_repeat('A', 20));
        }

        $this->assertFileExists($path);
        $this->assertFileExists($path . '.1');
    }

    public function testfileWriterAcceptsFormatterInConstructor(): void
    {
        $path = $this->tempDir . '/formatted.log';
        $formatter = new TextFormatter();
        $writer = new FileWriter($path, formatter: $formatter);
        $writer->write('Pre-formatted message');

        $content = file_get_contents($path);
        $this->assertStringContainsString('Pre-formatted message', $content);
    }

    // ============================================
    // ConsoleWriter Tests
    // ============================================

    public function testconsoleWriterCanBeInstantiatedWithDefaultStream(): void
    {
        $writer = new ConsoleWriter();

        $this->assertInstanceOf(WriterInterface::class, $writer);
    }
}
