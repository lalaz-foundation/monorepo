<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Logging;

use Lalaz\Logging\Logger;
use Lalaz\Logging\LogLevel;
use Lalaz\Logging\LogManager;
use Lalaz\Logging\Log;
use Lalaz\Logging\Writer\NullWriter;
use Lalaz\Logging\Formatter\JsonFormatter;
use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\LoggerInterface;

#[CoversClass(Logger::class)]
/**
 * Tests for the LogManager and Log facade.
 */
final class LogManagerTest extends FrameworkUnitTestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/lalaz_logmanager_test_' . uniqid();
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
    // LogManager Tests
    // ============================================

    public function test_implements_psr3_logger_interface(): void
    {
        $manager = new LogManager();

        $this->assertInstanceOf(LoggerInterface::class, $manager);
    }

    public function testreturnsDefaultChannel(): void
    {
        $manager = new LogManager([
            'default' => 'app',
            'channels' => [
                'app' => [
                    'driver' => 'single',
                    'path' => $this->tempDir . '/app.log',
                ],
            ],
        ]);

        $channel = $manager->channel();

        $this->assertInstanceOf(LoggerInterface::class, $channel);
    }

    public function testreturnsSameChannelInstance(): void
    {
        $manager = new LogManager([
            'channels' => [
                'app' => [
                    'driver' => 'single',
                    'path' => $this->tempDir . '/app.log',
                ],
            ],
        ]);

        $first = $manager->channel('app');
        $second = $manager->channel('app');

        $this->assertSame($first, $second);
    }

    public function testallowsDifferentChannels(): void
    {
        $manager = new LogManager([
            'channels' => [
                'app' => [
                    'driver' => 'single',
                    'path' => $this->tempDir . '/app.log',
                ],
                'security' => [
                    'driver' => 'single',
                    'path' => $this->tempDir . '/security.log',
                ],
            ],
        ]);

        $app = $manager->channel('app');
        $security = $manager->channel('security');

        $this->assertNotSame($app, $security);
    }

    public function testsetsAndGetsDefaultChannel(): void
    {
        $manager = new LogManager([
            'default' => 'app',
        ]);

        $this->assertSame('app', $manager->getDefaultChannel());

        $manager->setDefaultChannel('security');

        $this->assertSame('security', $manager->getDefaultChannel());
    }

    public function testcreatesConsoleDriver(): void
    {
        $manager = new LogManager([
            'channels' => [
                'console' => [
                    'driver' => 'console',
                    'level' => LogLevel::DEBUG,
                ],
            ],
        ]);

        $channel = $manager->channel('console');

        $this->assertInstanceOf(Logger::class, $channel);
    }

    public function testsupportsJsonFormatter(): void
    {
        $manager = new LogManager([
            'channels' => [
                'json' => [
                    'driver' => 'single',
                    'path' => $this->tempDir . '/json.log',
                    'formatter' => 'json',
                ],
            ],
        ]);

        $manager->channel('json')->info('Test message');

        $content = file_get_contents($this->tempDir . '/json.log');
        $decoded = json_decode($content, true);
        $this->assertNotNull($decoded, 'Content should be valid JSON');
    }

    public function testproxiesLogCallsToDefaultChannel(): void
    {
        $manager = new LogManager([
            'default' => 'app',
            'channels' => [
                'app' => [
                    'driver' => 'single',
                    'path' => $this->tempDir . '/app.log',
                ],
            ],
        ]);

        $manager->info('Test message');
        $manager->error('Error message');

        $content = file_get_contents($this->tempDir . '/app.log');
        $this->assertStringContainsString('Test message', $content);
        $this->assertStringContainsString('Error message', $content);
    }

    public function test_supports_all_psr3_log_levels(): void
    {
        $manager = new LogManager([
            'channels' => [
                'app' => [
                    'driver' => 'single',
                    'path' => $this->tempDir . '/levels.log',
                ],
            ],
        ]);

        $manager->channel('app')->emergency('Emergency');
        $manager->channel('app')->alert('Alert');
        $manager->channel('app')->critical('Critical');
        $manager->channel('app')->error('Error');
        $manager->channel('app')->warning('Warning');
        $manager->channel('app')->notice('Notice');
        $manager->channel('app')->info('Info');
        $manager->channel('app')->debug('Debug');

        $content = file_get_contents($this->tempDir . '/levels.log');
        $this->assertStringContainsString('EMERGENCY', $content);
        $this->assertStringContainsString('ALERT', $content);
        $this->assertStringContainsString('CRITICAL', $content);
        $this->assertStringContainsString('ERROR', $content);
        $this->assertStringContainsString('WARNING', $content);
        $this->assertStringContainsString('NOTICE', $content);
        $this->assertStringContainsString('INFO', $content);
        $this->assertStringContainsString('DEBUG', $content);
    }

    public function testrespectsMinimumLogLevel(): void
    {
        $manager = new LogManager([
            'channels' => [
                'errors' => [
                    'driver' => 'single',
                    'path' => $this->tempDir . '/errors.log',
                    'level' => LogLevel::ERROR,
                ],
            ],
        ]);

        $manager->channel('errors')->debug('Debug message');
        $manager->channel('errors')->info('Info message');
        $manager->channel('errors')->error('Error message');

        $content = file_get_contents($this->tempDir . '/errors.log');
        $this->assertStringNotContainsString('Debug message', $content);
        $this->assertStringNotContainsString('Info message', $content);
        $this->assertStringContainsString('Error message', $content);
    }

    // ============================================
    // Log Facade Tests
    // ============================================

    public function testfacadeProvidesStaticAccessToLogger(): void
    {
        $writer = new NullWriter(capture: true);
        $logger = new Logger();
        $logger->pushWriter($writer);

        Log::setLogger($logger);
        Log::info('Test via facade');

        $this->assertSame(1, $writer->count());
    }

    public function testfacadeSupportsAllLogLevels(): void
    {
        $writer = new NullWriter(capture: true);
        $logger = new Logger();
        $logger->pushWriter($writer);

        Log::setLogger($logger);

        Log::emergency('E');
        Log::alert('A');
        Log::critical('C');
        Log::error('Err');
        Log::warning('W');
        Log::notice('N');
        Log::info('I');
        Log::debug('D');

        $this->assertSame(8, $writer->count());
    }

    public function testfacadeSupportsContext(): void
    {
        $writer = new NullWriter(capture: true);
        $logger = new Logger();
        $logger->pushWriter($writer);
        $logger->setFormatter(new JsonFormatter());

        Log::setLogger($logger);
        Log::error('Error occurred', ['code' => 500]);

        $decoded = json_decode($writer->getLastMessage(), true);
        $this->assertSame(500, $decoded['context']['code']);
    }

    public function testfacadeProvidesChannelAccessWhenUsingLogManager(): void
    {
        $manager = new LogManager([
            'channels' => [
                'app' => [
                    'driver' => 'single',
                    'path' => $this->tempDir . '/app.log',
                ],
            ],
        ]);

        Log::setManager($manager);
        $channel = Log::channel('app');

        $this->assertInstanceOf(LoggerInterface::class, $channel);
    }

    public function testfacadeLogsToSpecificChannels(): void
    {
        $manager = new LogManager([
            'channels' => [
                'security' => [
                    'driver' => 'single',
                    'path' => $this->tempDir . '/security.log',
                ],
            ],
        ]);

        Log::setManager($manager);
        Log::channel('security')->warning('Suspicious activity');

        $content = file_get_contents($this->tempDir . '/security.log');
        $this->assertStringContainsString('Suspicious activity', $content);
    }
}
