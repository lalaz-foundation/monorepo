<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Logging;

use Lalaz\Logging\Formatter\TextFormatter;
use Lalaz\Logging\Formatter\JsonFormatter;
use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(TextFormatter::class)]
/**
 * Tests for the Formatter classes.
 */
final class FormatterTest extends FrameworkUnitTestCase
{
    // ============================================
    // TextFormatter Tests
    // ============================================

    public function testtextFormatterFormatsMessageWithTimestampAndLevel(): void
    {
        $formatter = new TextFormatter();
        $result = $formatter->format('error', 'Something went wrong');

        $this->assertMatchesRegularExpression('/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] ERROR: Something went wrong$/', $result);
    }

    public function testtextFormatterIncludesContextAsJson(): void
    {
        $formatter = new TextFormatter();
        $result = $formatter->format('info', 'User action', ['user_id' => 123]);

        $this->assertStringContainsString('{"user_id":123}', $result);
    }

    public function testtextFormatterHandlesEmptyContext(): void
    {
        $formatter = new TextFormatter();
        $result = $formatter->format('debug', 'Simple message', []);

        $this->assertStringNotContainsString('{', $result);
    }

    // ============================================
    // JsonFormatter Tests
    // ============================================

    public function testjsonFormatterOutputsValidJson(): void
    {
        $formatter = new JsonFormatter();
        $result = $formatter->format('error', 'Test message');

        $decoded = json_decode($result, true);
        $this->assertNotNull($decoded, 'Result should be valid JSON');
    }

    public function testjsonFormatterIncludesAllRequiredFields(): void
    {
        $formatter = new JsonFormatter();
        $result = $formatter->format('error', 'Test message', ['key' => 'value']);
        $decoded = json_decode($result, true);

        $this->assertArrayHasKey('timestamp', $decoded);
        $this->assertArrayHasKey('level', $decoded);
        $this->assertArrayHasKey('message', $decoded);
        $this->assertArrayHasKey('context', $decoded);
        $this->assertSame('error', $decoded['level']);
        $this->assertSame('Test message', $decoded['message']);
    }

    public function testjsonFormatterFormatsExceptionsInContext(): void
    {
        $formatter = new JsonFormatter();
        $exception = new RuntimeException('Test error', 500);
        $result = $formatter->format('error', 'Exception occurred', ['exception' => $exception]);
        $decoded = json_decode($result, true);

        $this->assertArrayHasKey('class', $decoded['context']['exception']);
        $this->assertArrayHasKey('message', $decoded['context']['exception']);
        $this->assertArrayHasKey('code', $decoded['context']['exception']);
        $this->assertArrayHasKey('trace', $decoded['context']['exception']);
        $this->assertSame('RuntimeException', $decoded['context']['exception']['class']);
    }

    public function testjsonFormatterSupportsPrettyPrint(): void
    {
        $formatter = new JsonFormatter(prettyPrint: true);
        $result = $formatter->format('info', 'Test');

        $this->assertStringContainsString("\n", $result);
    }

    public function testjsonFormatterCanExcludeTimestamp(): void
    {
        $formatter = new JsonFormatter(includeTimestamp: false);
        $result = $formatter->format('info', 'Test');
        $decoded = json_decode($result, true);

        $this->assertArrayNotHasKey('timestamp', $decoded);
    }

    public function testjsonFormatterCanIncludeChannel(): void
    {
        $formatter = new JsonFormatter(channel: 'security');
        $result = $formatter->format('warning', 'Suspicious activity');
        $decoded = json_decode($result, true);

        $this->assertSame('security', $decoded['channel']);
    }

    public function testjsonFormatterWithChannelCreatesNewInstance(): void
    {
        $formatter = new JsonFormatter();
        $withChannel = $formatter->withChannel('audit');
        $result = $withChannel->format('info', 'Test');
        $decoded = json_decode($result, true);

        $this->assertSame('audit', $decoded['channel']);
    }
}
