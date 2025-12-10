<?php

declare(strict_types=1);

namespace Lalaz\Console\Commands;

use Lalaz\Console\Contracts\CommandInterface;
use Lalaz\Console\Input;
use Lalaz\Console\Output;

/**
 * Command to start the built-in PHP development server.
 *
 * This command provides a convenient way to start a local development
 * server without manually invoking PHP's built-in server. It supports
 * custom port configuration and automatic port detection if the default
 * port is already in use.
 *
 * Usage:
 * ```bash
 * php lalaz serve           # Starts on port 8000 (or next available)
 * php lalaz serve --port=3000  # Starts on port 3000 (fails if in use)
 * php lalaz serve -p 3000      # Short form for port
 * ```
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
class ServeCommand implements CommandInterface
{
    private const DEFAULT_PORT = 8000;
    private const DEFAULT_HOST = 'localhost';
    private const MAX_PORT_ATTEMPTS = 10;

    /**
     * @inheritDoc
     */
    public function name(): string
    {
        return 'serve';
    }

    /**
     * @inheritDoc
     */
    public function description(): string
    {
        return 'Start the built-in PHP development server';
    }

    /**
     * @inheritDoc
     */
    public function arguments(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function options(): array
    {
        return [
            [
                'name' => 'port',
                'shortcut' => 'p',
                'description' => 'The port to serve the application on (default: 8000)',
                'requiresValue' => true,
            ],
            [
                'name' => 'host',
                'shortcut' => 'H',
                'description' => 'The host address to bind to (default: localhost)',
                'requiresValue' => true,
            ],
        ];
    }

    /**
     * @inheritDoc
     */
    public function handle(Input $input, Output $output): int
    {
        $host = $input->option('host') ?? $input->option('H') ?? self::DEFAULT_HOST;
        $requestedPort = $input->option('port') ?? $input->option('p');
        $portWasExplicit = $requestedPort !== null;

        $port = $portWasExplicit
            ? (int) $requestedPort
            : self::DEFAULT_PORT;

        if ($portWasExplicit) {
            if ($this->isPortInUse($host, $port)) {
                $output->error("Port {$port} is already in use.");
                $output->writeln('Please choose a different port or stop the process using it.');
                return 1;
            }
        } else {
            $port = $this->findAvailablePort($host, $port);

            if ($port === null) {
                $output->error('Could not find an available port after ' . self::MAX_PORT_ATTEMPTS . ' attempts.');
                $output->writeln('Please specify a port manually using --port option.');
                return 1;
            }

            if ($port !== self::DEFAULT_PORT) {
                $output->writeln('⚠ Default port ' . self::DEFAULT_PORT . " is in use, using port {$port} instead.");
                $output->writeln('');
            }
        }

        $docroot = getcwd() . '/public';

        if (!is_dir($docroot)) {
            $output->error("Document root not found: {$docroot}");
            $output->writeln("Make sure you're running this command from your project root.");
            return 1;
        }

        $output->writeln('✓ Starting Lalaz development server...');
        $output->writeln('');
        $output->writeln("  <info>Local:</info>   http://{$host}:{$port}");
        $output->writeln('');
        $output->writeln('Press Ctrl+C to stop the server.');
        $output->writeln('');

        $command = sprintf(
            'php -S %s:%d -t %s',
            escapeshellarg($host),
            $port,
            escapeshellarg($docroot)
        );

        passthru($command, $exitCode);

        return $exitCode;
    }

    /**
     * Check if a port is already in use.
     *
     * @param string $host The host to check.
     * @param int $port The port to check.
     * @return bool True if port is in use, false otherwise.
     */
    protected function isPortInUse(string $host, int $port): bool
    {
        $connection = @fsockopen($host, $port, $errno, $errstr, 1);

        if ($connection !== false) {
            fclose($connection);
            return true;
        }

        return false;
    }

    /**
     * Find an available port starting from the given port.
     *
     * @param string $host The host to check.
     * @param int $startPort The port to start checking from.
     * @return int|null The first available port, or null if none found.
     */
    protected function findAvailablePort(string $host, int $startPort): ?int
    {
        for ($i = 0; $i < self::MAX_PORT_ATTEMPTS; $i++) {
            $port = $startPort + $i;

            if (!$this->isPortInUse($host, $port)) {
                return $port;
            }
        }

        return null;
    }
}
