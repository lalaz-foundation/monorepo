<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Logging;

use Lalaz\Logging\Logger;
use Lalaz\Logging\LogLevel;
use Lalaz\Logging\Writer\NullWriter;
use Lalaz\Logging\Formatter\TextFormatter;
use Lalaz\Logging\Formatter\JsonFormatter;
use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Stringable;

#[CoversClass(Logger::class)]
/**
 * Tests for the Logger class.
 */
final class LoggerTest extends FrameworkUnitTestCase
{
    private NullWriter $writer;
    private Logger $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->writer = new NullWriter(capture: true);
        $this->logger = new Logger(new TextFormatter(), LogLevel::DEBUG);
        $this->logger->pushWriter($this->writer);
    }

    public function testfiltersLogsBelowMinimumLevel(): void
    {
        $logger = new Logger(null, LogLevel::ERROR);
        $writer = new NullWriter(capture: true);
        $logger->pushWriter($writer);

        $logger->info('informational');
        $logger->error('boom');

        $this->assertSame(1, $writer->count());
        $this->assertStringContainsString('boom', $writer->getLastMessage());
    }

    public function testinterpolatesContextIntoMessages(): void
    {
        $this->logger->warning('Hello {name}', ['name' => 'Lalaz']);

        $this->assertStringContainsString('Hello Lalaz', $this->writer->getLastMessage());
    }

    public function testlogsMessagesAtAllLevels(): void
    {
        $this->logger->debug('debug message');
        $this->logger->info('info message');
        $this->logger->notice('notice message');
        $this->logger->warning('warning message');
        $this->logger->error('error message');
        $this->logger->critical('critical message');
        $this->logger->alert('alert message');
        $this->logger->emergency('emergency message');

        $this->assertSame(8, $this->writer->count());
    }

    public function testrespectsMinimumLogLevel(): void
    {
        $writer = new NullWriter(capture: true);
        $logger = new Logger(new TextFormatter(), LogLevel::WARNING);
        $logger->pushWriter($writer);

        $logger->debug('debug');
        $logger->info('info');
        $logger->notice('notice');
        $logger->warning('warning');
        $logger->error('error');

        $this->assertSame(2, $writer->count());
        $this->assertTrue($writer->hasMessageMatching('/WARNING/'));
        $this->assertTrue($writer->hasMessageMatching('/ERROR/'));
    }

    public function testhandlesArrayContextValues(): void
    {
        $this->logger->info('Data: {data}', [
            'data' => ['foo' => 'bar'],
        ]);

        $this->assertStringContainsString('{"foo":"bar"}', $this->writer->getLastMessage());
    }

    public function testhandlesStringableObjects(): void
    {
        $object = new class implements Stringable {
            public function __toString(): string
            {
                return 'stringable-value';
            }
        };

        $this->logger->info('Value: {value}', ['value' => $object]);

        $this->assertStringContainsString('Value: stringable-value', $this->writer->getLastMessage());
    }

    public function testusesCustomFormatter(): void
    {
        $writer = new NullWriter(capture: true);
        $logger = new Logger(new JsonFormatter(), LogLevel::DEBUG);
        $logger->pushWriter($writer);

        $logger->info('test message');

        $message = $writer->getLastMessage();
        $decoded = json_decode($message, true);
        $this->assertNotNull($decoded, 'Message should be valid JSON');
    }

    public function testsupportsMultipleWriters(): void
    {
        $writer1 = new NullWriter(capture: true);
        $writer2 = new NullWriter(capture: true);

        $logger = new Logger(new TextFormatter(), LogLevel::DEBUG);
        $logger->pushWriter($writer1);
        $logger->pushWriter($writer2);

        $logger->info('test message');

        $this->assertSame(1, $writer1->count());
        $this->assertSame(1, $writer2->count());
    }

    public function testhandlesNullContextValues(): void
    {
        $this->logger->info('Value is {value}', ['value' => null]);

        $this->assertStringContainsString('Value is ', $this->writer->getLastMessage());
    }

    public function testlogMethodAcceptsAnyLevelString(): void
    {
        $this->logger->log('warning', 'custom log call');

        $this->assertStringContainsString('WARNING', $this->writer->getLastMessage());
    }
}
