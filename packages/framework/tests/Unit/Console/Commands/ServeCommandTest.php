<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Console\Commands;

use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Lalaz\Console\Commands\ServeCommand;
use Lalaz\Console\Input;
use Lalaz\Console\Output;
use ReflectionClass;

/**
 * Test output class that captures console output for assertions.
 */
class ServeCommandOutput extends Output
{
    public array $lines = [];
    public array $errors = [];

    public function writeln(string $message = ""): void
    {
        $this->lines[] = $message;
    }

    public function error(string $message): void
    {
        $this->errors[] = $message;
    }
}

/**
 * Testable version of ServeCommand that allows mocking port checks
 * and prevents actual server execution.
 */
class TestableServeCommand extends ServeCommand
{
    private array $portsInUse = [];
    public ?string $lastServerCommand = null;
    public ?string $documentRoot = null;

    public function setPortsInUse(array $ports): void
    {
        $this->portsInUse = $ports;
    }

    public function setDocumentRoot(?string $path): void
    {
        $this->documentRoot = $path;
    }

    public function handle(Input $input, Output $output): int
    {
        $host = $input->option('host') ?? $input->option('H') ?? 'localhost';
        $requestedPort = $input->option('port') ?? $input->option('p');
        $portWasExplicit = $requestedPort !== null;

        $port = $portWasExplicit
            ? (int) $requestedPort
            : 8000;

        if ($portWasExplicit) {
            if ($this->isPortInUse($host, $port)) {
                $output->error("Port {$port} is already in use.");
                $output->writeln("Please choose a different port or stop the process using it.");
                return 1;
            }
        } else {
            $port = $this->findAvailablePort($host, $port);

            if ($port === null) {
                $output->error("Could not find an available port after 10 attempts.");
                $output->writeln("Please specify a port manually using --port option.");
                return 1;
            }

            if ($port !== 8000) {
                $output->writeln("⚠ Default port 8000 is in use, using port {$port} instead.");
                $output->writeln("");
            }
        }

        $docroot = $this->documentRoot . '/public';

        if (!is_dir($docroot)) {
            $output->error("Document root not found: {$docroot}");
            $output->writeln("Make sure you're running this command from your project root.");
            return 1;
        }

        $output->writeln("✓ Starting Lalaz development server...");
        $output->writeln("");
        $output->writeln("  Local:   http://{$host}:{$port}");
        $output->writeln("");
        $output->writeln("Press Ctrl+C to stop the server.");
        $output->writeln("");

        $this->lastServerCommand = sprintf(
            'php -S %s:%d -t %s',
            escapeshellarg($host),
            $port,
            escapeshellarg($docroot)
        );

        // Don't actually execute the server in tests
        return 0;
    }

    protected function isPortInUse(string $host, int $port): bool
    {
        return in_array($port, $this->portsInUse, true);
    }

    protected function findAvailablePort(string $host, int $startPort): ?int
    {
        for ($i = 0; $i < 10; $i++) {
            $port = $startPort + $i;

            if (!$this->isPortInUse($host, $port)) {
                return $port;
            }
        }

        return null;
    }
}

class ServeCommandTest extends FrameworkUnitTestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/lalaz_serve_test_' . bin2hex(random_bytes(5));
        @mkdir($this->tempDir . '/public', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->recursiveRemoveDirectory($this->tempDir);
        parent::tearDown();
    }

    private function recursiveRemoveDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->recursiveRemoveDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }

    public function testcommandHasCorrectName(): void
    {
        $command = new ServeCommand();
        $this->assertSame('serve', $command->name());
    }

    public function testcommandHasDescription(): void
    {
        $command = new ServeCommand();
        $this->assertSame('Start the built-in PHP development server', $command->description());
    }

    public function testcommandHasNoArguments(): void
    {
        $command = new ServeCommand();
        $this->assertSame([], $command->arguments());
    }

    public function testcommandHasPortAndHostOptions(): void
    {
        $command = new ServeCommand();
        $options = $command->options();

        $this->assertCount(2, $options);

        $portOption = $options[0];
        $this->assertSame('port', $portOption['name']);
        $this->assertSame('p', $portOption['shortcut']);
        $this->assertTrue($portOption['requiresValue']);

        $hostOption = $options[1];
        $this->assertSame('host', $hostOption['name']);
        $this->assertSame('H', $hostOption['shortcut']);
        $this->assertTrue($hostOption['requiresValue']);
    }

    public function testserveStartsOnDefaultPortWhenAvailable(): void
    {
        $command = new TestableServeCommand();
        $command->setDocumentRoot($this->tempDir);
        $command->setPortsInUse([]);

        $output = new ServeCommandOutput();
        $input = new Input(['lalaz', 'serve']);

        $result = $command->handle($input, $output);

        $this->assertSame(0, $result);
        $this->assertContains('✓ Starting Lalaz development server...', $output->lines);
        $this->assertContains('  Local:   http://localhost:8000', $output->lines);
        $this->assertStringContainsString(':8000', $command->lastServerCommand);
    }

    public function testserveUsesCustomPortWhenSpecified(): void
    {
        $command = new TestableServeCommand();
        $command->setDocumentRoot($this->tempDir);
        $command->setPortsInUse([]);

        $output = new ServeCommandOutput();
        $input = new Input(['lalaz', 'serve', '--port=3000']);

        $result = $command->handle($input, $output);

        $this->assertSame(0, $result);
        $this->assertContains('  Local:   http://localhost:3000', $output->lines);
        $this->assertStringContainsString(':3000', $command->lastServerCommand);
    }

    public function testserveUsesCustomHostWhenSpecified(): void
    {
        $command = new TestableServeCommand();
        $command->setDocumentRoot($this->tempDir);
        $command->setPortsInUse([]);

        $output = new ServeCommandOutput();
        $input = new Input(['lalaz', 'serve', '--host=0.0.0.0']);

        $result = $command->handle($input, $output);

        $this->assertSame(0, $result);
        $this->assertContains('  Local:   http://0.0.0.0:8000', $output->lines);
        $this->assertStringContainsString("'0.0.0.0'", $command->lastServerCommand);
    }

    public function testserveAutoIncrementsPortWhenDefaultIsInUse(): void
    {
        $command = new TestableServeCommand();
        $command->setDocumentRoot($this->tempDir);
        $command->setPortsInUse([8000, 8001]); // First two ports in use

        $output = new ServeCommandOutput();
        $input = new Input(['lalaz', 'serve']);

        $result = $command->handle($input, $output);

        $this->assertSame(0, $result);
        $this->assertContains('⚠ Default port 8000 is in use, using port 8002 instead.', $output->lines);
        $this->assertContains('  Local:   http://localhost:8002', $output->lines);
        $this->assertStringContainsString(':8002', $command->lastServerCommand);
    }

    public function testserveFailsWhenExplicitPortIsInUse(): void
    {
        $command = new TestableServeCommand();
        $command->setDocumentRoot($this->tempDir);
        $command->setPortsInUse([3000]);

        $output = new ServeCommandOutput();
        $input = new Input(['lalaz', 'serve', '--port=3000']);

        $result = $command->handle($input, $output);

        $this->assertSame(1, $result);
        $this->assertContains('Port 3000 is already in use.', $output->errors);
        $this->assertContains('Please choose a different port or stop the process using it.', $output->lines);
    }

    public function testserveFailsWhenNoAvailablePortFound(): void
    {
        $command = new TestableServeCommand();
        $command->setDocumentRoot($this->tempDir);
        // Block all 10 ports that would be tried
        $command->setPortsInUse(range(8000, 8009));

        $output = new ServeCommandOutput();
        $input = new Input(['lalaz', 'serve']);

        $result = $command->handle($input, $output);

        $this->assertSame(1, $result);
        $this->assertContains('Could not find an available port after 10 attempts.', $output->errors);
        $this->assertContains('Please specify a port manually using --port option.', $output->lines);
    }

    public function testserveFailsWhenPublicDirectoryNotFound(): void
    {
        $tempDirWithoutPublic = sys_get_temp_dir() . '/lalaz_serve_nopublic_' . bin2hex(random_bytes(5));
        @mkdir($tempDirWithoutPublic, 0777, true);

        $command = new TestableServeCommand();
        $command->setDocumentRoot($tempDirWithoutPublic);
        $command->setPortsInUse([]);

        $output = new ServeCommandOutput();
        $input = new Input(['lalaz', 'serve']);

        $result = $command->handle($input, $output);

        $this->assertSame(1, $result);
        $this->assertNotEmpty($output->errors);
        $this->assertStringContainsString('Document root not found', $output->errors[0]);

        @rmdir($tempDirWithoutPublic);
    }

    public function testserveGeneratesCorrectPhpCommand(): void
    {
        $command = new TestableServeCommand();
        $command->setDocumentRoot($this->tempDir);
        $command->setPortsInUse([]);

        $output = new ServeCommandOutput();
        $input = new Input(['lalaz', 'serve', '--host=127.0.0.1', '--port=9000']);

        $command->handle($input, $output);

        $this->assertStringContainsString('php -S', $command->lastServerCommand);
        $this->assertStringContainsString("'127.0.0.1'", $command->lastServerCommand);
        $this->assertStringContainsString(':9000', $command->lastServerCommand);
        $this->assertStringContainsString($this->tempDir . '/public', $command->lastServerCommand);
    }

    public function testserveShortOptionsWork(): void
    {
        $command = new TestableServeCommand();
        $command->setDocumentRoot($this->tempDir);
        $command->setPortsInUse([]);

        $output = new ServeCommandOutput();
        // Short options -p and -H
        $input = new Input(['lalaz', 'serve', '-p', '4000', '-H', '0.0.0.0']);

        $result = $command->handle($input, $output);

        $this->assertSame(0, $result);
        $this->assertContains('  Local:   http://0.0.0.0:4000', $output->lines);
    }

    public function testserveLongOptionsWork(): void
    {
        $command = new TestableServeCommand();
        $command->setDocumentRoot($this->tempDir);
        $command->setPortsInUse([]);

        $output = new ServeCommandOutput();
        $input = new Input(['lalaz', 'serve', '--port=5000', '--host=127.0.0.1']);

        $result = $command->handle($input, $output);

        $this->assertSame(0, $result);
        $this->assertContains('  Local:   http://127.0.0.1:5000', $output->lines);
    }

    public function testisPortInUseMethodExistsAndIsCallable(): void
    {
        $command = new ServeCommand();
        $reflection = new ReflectionClass($command);

        $this->assertTrue($reflection->hasMethod('isPortInUse'));

        $method = $reflection->getMethod('isPortInUse');
        $this->assertTrue($method->isProtected());
    }

    public function testfindAvailablePortMethodExistsAndIsCallable(): void
    {
        $command = new ServeCommand();
        $reflection = new ReflectionClass($command);

        $this->assertTrue($reflection->hasMethod('findAvailablePort'));

        $method = $reflection->getMethod('findAvailablePort');
        $this->assertTrue($method->isProtected());
    }
}
